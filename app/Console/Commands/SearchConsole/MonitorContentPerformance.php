<?php

namespace App\Console\Commands\SearchConsole;

use Illuminate\Console\Command;
use App\Models\BlogPost;
use App\Models\SearchConsoleData;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MonitorContentPerformance extends Command
{
    protected $signature = 'content:monitor 
                            {--days=30 : Aantal dagen om performance te analyseren}
                            {--min-impressions=5 : Minimum impressions om relevant te zijn}
                            {--auto-cleanup : Automatisch slechte content archiveren}
                            {--dry-run : Toon alleen wat er zou gebeuren}';

    protected $description = 'Monitor content performance en identificeer slechte content voor cleanup';

    public function handle()
    {
        $days = (int) $this->option('days');
        $minImpressions = (int) $this->option('min-impressions');
        $autoCleanup = $this->option('auto-cleanup');
        $dryRun = $this->option('dry-run');

        $this->info("🔍 Content Performance Monitoring");
        $this->info("📅 Periode: {$days} dagen");
        $this->info("📊 Minimum impressions: {$minImpressions}");
        $this->newLine();

        // Analyseer GSC-gegenereerde content
        $gscContent = $this->analyzeGscContent($days, $minImpressions);
        
        // Toon resultaten
        $this->displayPerformanceResults($gscContent);
        
        // Cleanup indien gewenst
        if ($autoCleanup && !$dryRun) {
            $this->performContentCleanup($gscContent);
        } elseif ($dryRun) {
            $this->showCleanupPlan($gscContent);
        }

        return 0;
    }

    private function analyzeGscContent(int $days, int $minImpressions): Collection
    {
        $startDate = Carbon::now()->subDays($days);
        $endDate = Carbon::now();

        // Haal alle GSC-gegenereerde blogs op
        $gscBlogs = BlogPost::where('type', 'gsc_opportunity')
            ->where('created_at', '>=', $startDate)
            ->get();

        $this->info("📝 Gevonden GSC blogs: {$gscBlogs->count()}");

        // Analyseer performance per blog
        $performanceData = $gscBlogs->map(function ($blog) use ($startDate, $endDate, $minImpressions) {
            // Zoek gerelateerde GSC data
            $gscMetrics = $this->findRelatedGscData($blog, $startDate, $endDate);
            
            $totalImpressions = $gscMetrics->sum('impressions');
            $totalClicks = $gscMetrics->sum('clicks');
            $avgPosition = $gscMetrics->avg('position') ?? 100;
            $avgCtr = $totalImpressions > 0 ? ($totalClicks / $totalImpressions) : 0;

            return [
                'blog' => $blog,
                'impressions' => $totalImpressions,
                'clicks' => $totalClicks,
                'avg_position' => round($avgPosition, 1),
                'ctr' => round($avgCtr * 100, 2),
                'performance_score' => $this->calculatePerformanceScore($totalImpressions, $totalClicks, $avgPosition),
                'age_days' => $blog->created_at->diffInDays(Carbon::now()),
                'status' => $this->determineContentStatus($totalImpressions, $totalClicks, $avgPosition, $minImpressions),
            ];
        });

        return $performanceData->sortByDesc('performance_score');
    }

    private function findRelatedGscData($blog, $startDate, $endDate): Collection
    {
        // Extract keywords uit blog title en content
        $keywords = $this->extractKeywordsFromBlog($blog);
        
        if (empty($keywords)) {
            return collect();
        }

        // Zoek matching GSC data
        return SearchConsoleData::whereIn('query', $keywords)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('status', '!=', 'archived')
            ->get();
    }

    private function extractKeywordsFromBlog($blog): array
    {
        $keywords = [];
        
        // Probeer uit JSON content
        $content = json_decode($blog->content, true);
        if ($content && isset($content['title'])) {
            $title = strtolower($content['title']);
            // Simpele keyword extractie - kan verfijnd worden
            $words = explode(' ', $title);
            $meaningfulWords = array_filter($words, fn($word) => strlen($word) >= 4);
            $keywords[] = implode(' ', array_slice($meaningfulWords, 0, 3));
        }
        
        // Fallback naar blog title
        if (empty($keywords)) {
            $titleWords = explode(' ', strtolower($blog->title));
            $meaningfulWords = array_filter($titleWords, fn($word) => strlen($word) >= 4);
            if (!empty($meaningfulWords)) {
                $keywords[] = implode(' ', array_slice($meaningfulWords, 0, 3));
            }
        }

        return array_filter($keywords);
    }

    private function calculatePerformanceScore(int $impressions, int $clicks, float $position): float
    {
        // Simpele performance score
        $impressionScore = min(log($impressions + 1) * 2, 20); // Max 20 punten
        $clickScore = min($clicks * 2, 30); // Max 30 punten  
        $positionScore = max(0, (100 - $position) / 2); // Betere positie = hogere score

        return round($impressionScore + $clickScore + $positionScore, 1);
    }

    private function determineContentStatus(int $impressions, int $clicks, float $position, int $minImpressions): string
    {
        if ($impressions < $minImpressions) {
            return 'no_visibility'; // Geen zichtbaarheid
        }
        
        if ($position > 50 && $clicks < 2) {
            return 'poor_performance'; // Slechte performance
        }
        
        if ($position > 20 && $clicks < 5) {
            return 'needs_improvement'; // Verbetering nodig
        }
        
        if ($clicks >= 10 && $position <= 20) {
            return 'good_performance'; // Goede performance
        }
        
        return 'average_performance'; // Gemiddeld
    }

    private function displayPerformanceResults(Collection $performanceData): void
    {
        $this->newLine();
        $this->info("📊 Content Performance Overzicht:");
        
        $statusCounts = $performanceData->groupBy('status')->map(fn($group) => $group->count());
        
        foreach ($statusCounts as $status => $count) {
            $emoji = match($status) {
                'good_performance' => '✅',
                'average_performance' => '⚠️',
                'needs_improvement' => '🔄',
                'poor_performance' => '❌',
                'no_visibility' => '👻',
                default => '❓'
            };
            
            $label = match($status) {
                'good_performance' => 'Goede performance',
                'average_performance' => 'Gemiddelde performance', 
                'needs_improvement' => 'Verbetering nodig',
                'poor_performance' => 'Slechte performance',
                'no_visibility' => 'Geen zichtbaarheid',
                default => 'Onbekend'
            };
            
            $this->line("{$emoji} {$label}: {$count} blogs");
        }

        $this->newLine();
        $this->info("🔥 Top performers:");
        $this->table(
            ['Titel', 'Impressions', 'Clicks', 'Position', 'CTR%', 'Score'],
            $performanceData->where('status', 'good_performance')
                ->take(3)
                ->map(fn($item) => [
                    \Str::limit($item['blog']->title, 40),
                    $item['impressions'],
                    $item['clicks'],
                    $item['avg_position'],
                    $item['ctr'] . '%',
                    $item['performance_score'],
                ])
        );

        $this->newLine();
        $this->warn("⚠️ Content met problemen:");
        $problemContent = $performanceData->whereIn('status', ['poor_performance', 'no_visibility']);
        
        if ($problemContent->isNotEmpty()) {
            $this->table(
                ['Titel', 'Status', 'Impressions', 'Clicks', 'Leeftijd', 'Score'],
                $problemContent->take(5)->map(fn($item) => [
                    \Str::limit($item['blog']->title, 40),
                    $item['status'],
                    $item['impressions'],
                    $item['clicks'],
                    $item['age_days'] . 'd',
                    $item['performance_score'],
                ])
            );
        } else {
            $this->info("🎉 Geen problematische content gevonden!");
        }
    }

    private function performContentCleanup(Collection $performanceData): void
    {
        $toArchive = $performanceData->where('status', 'no_visibility')
            ->where('age_days', '>', 14); // Alleen content ouder dan 2 weken

        $toDraft = $performanceData->where('status', 'poor_performance')
            ->where('age_days', '>', 7); // Content ouder dan 1 week

        if ($toArchive->isNotEmpty()) {
            $this->info("\n🗑️ Archiveren van content zonder zichtbaarheid:");
            foreach ($toArchive as $item) {
                $item['blog']->update(['status' => 'archived']);
                $this->line("   Gearchiveerd: " . \Str::limit($item['blog']->title, 50));
            }
        }

        if ($toDraft->isNotEmpty()) {
            $this->info("\n📝 Terug naar draft: slechte performance:");
            foreach ($toDraft as $item) {
                $item['blog']->update(['status' => 'draft']);
                $this->line("   Draft: " . \Str::limit($item['blog']->title, 50));
            }
        }

        $this->newLine();
        $this->info("✅ Content cleanup voltooid!");
        $this->info("📊 Gearchiveerd: {$toArchive->count()}, Draft: {$toDraft->count()}");
    }

    private function showCleanupPlan(Collection $performanceData): void
    {
        $toArchive = $performanceData->where('status', 'no_visibility')
            ->where('age_days', '>', 14);

        $toDraft = $performanceData->where('status', 'poor_performance')
            ->where('age_days', '>', 7);

        $this->newLine();
        $this->info("🔍 Cleanup Plan (--dry-run mode):");
        $this->info("Zouden archiveren: {$toArchive->count()} blogs");
        $this->info("Zouden naar draft: {$toDraft->count()} blogs");
        
        if ($toArchive->isNotEmpty() || $toDraft->isNotEmpty()) {
            $this->newLine();
            $this->line("Run zonder --dry-run om daadwerkelijk op te ruimen.");
        }
    }
}