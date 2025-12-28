<?php

namespace App\Console\Commands\System;

use App\Models\BlogPost;
use App\Models\Review;
use App\Models\SearchConsoleData;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class ContentAudit extends Command
{
    protected $signature = 'content:audit 
                            {--type=blogs : Content type: blogs of reviews}
                            {--min-age-days=30 : Minimum leeftijd content in dagen}
                            {--keep-percentage=70 : Percentage content om te behouden}
                            {--grace-period-days=60 : Grace period voor nieuwe content}
                            {--days=90 : Dagen GSC data om te analyseren}
                            {--dry-run : Toon alleen resultaten zonder wijzigingen}
                            {--execute : Voer daadwerkelijk noindex acties uit}
                            {--force : Geen bevestiging vragen (voor scheduler)}';

    protected $description = 'Audit blog content op basis van GSC performance en relatieve scoring';

    public function handle()
    {
        $type = $this->option('type');
        $minAgeDays = (int) $this->option('min-age-days');
        $keepPercentage = (int) $this->option('keep-percentage');
        $gracePeriodDays = (int) $this->option('grace-period-days');
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $execute = $this->option('execute');

        if (!in_array($type, ['blogs', 'reviews'])) {
            $this->error('❌ Type moet "blogs" of "reviews" zijn');
            return 1;
        }

        if (!$dryRun && !$execute) {
            $this->info('💡 Gebruik --dry-run om te testen of --execute om uit te voeren');
            return 0;
        }

        $this->info('🔍 Content Audit starten...');
        $this->info("📊 Instellingen:");
        $this->line("   • Content type: {$type}");
        $this->line("   • Minimum leeftijd: {$minAgeDays} dagen");
        $this->line("   • Behoud percentage: {$keepPercentage}%");
        $this->line("   • Grace period: {$gracePeriodDays} dagen");
        $this->line("   • GSC data periode: {$days} dagen");
        $this->line("   • Mode: " . ($dryRun ? 'Dry run (geen wijzigingen)' : 'Uitvoeren'));

        // Stap 1: Haal blogs op die oud genoeg zijn voor audit (momenteel alleen blogs)
        $eligibleBlogs = $this->getEligibleBlogs($minAgeDays);
        
        if ($eligibleBlogs->isEmpty()) {
            $this->warn('⚠️ Geen blogs gevonden die geschikt zijn voor audit (alle blogs zijn mogelijk al ge-noindexed of te nieuw)');
            return 0;
        }

        $this->info("✅ {$eligibleBlogs->count()} blogs geschikt voor audit");

        // Stap 2: Haal GSC performance data op
        $performanceData = $this->getPerformanceData($eligibleBlogs, $days);

        // Stap 3: Bereken performance scores
        $scoredBlogs = $this->calculatePerformanceScores($eligibleBlogs, $performanceData);

        // Stap 4: Categoriseer blogs op basis van relatieve performance
        $categories = $this->categorizeBlogs($scoredBlogs, $keepPercentage, $gracePeriodDays);

        // Stap 5: Toon resultaten
        $this->showResults($categories);

        // Stap 6: Voer acties uit (indien niet dry-run)
        if ($execute && !$dryRun) {
            $this->executeActions($categories);
        }

        return 0;
    }

    private function getEligibleBlogs(int $minAgeDays): Collection
    {
        return BlogPost::where('type', 'general')
            ->where('status', 'published')
            ->where('created_at', '<', Carbon::now()->subDays($minAgeDays))
            ->where(function ($query) {
                $query->whereNull('meta_robots')
                      ->orWhere('meta_robots', '!=', 'noindex,nofollow');
            })
            ->get();
    }

    private function getPerformanceData(Collection $blogs, int $days): Collection
    {
        $siteUrl = $this->getCurrentSiteUrl();
        if (!$siteUrl) {
            $this->error('❌ Geen site URL gevonden');
            return collect();
        }

        $endDate = Carbon::now();
        $startDate = $endDate->copy()->subDays($days);

        // Haal GSC data op voor blog URLs
        $blogUrls = $blogs->map(function ($blog) use ($siteUrl) {
            return rtrim($siteUrl, '/') . '/blogs/' . $blog->slug;
        })->toArray();

        return SearchConsoleData::forSite($siteUrl)
            ->forDateRange($startDate, $endDate)
            ->whereIn('page', $blogUrls)
            ->get()
            ->groupBy('page')
            ->map(function ($pageData) {
                return [
                    'total_clicks' => $pageData->sum('clicks'),
                    'total_impressions' => $pageData->sum('impressions'),
                    'avg_ctr' => $pageData->avg('ctr'),
                    'avg_position' => $pageData->avg('position'),
                    'queries_count' => $pageData->count(),
                ];
            });
    }

    private function calculatePerformanceScores(Collection $blogs, Collection $performanceData): Collection
    {
        return $blogs->map(function ($blog) use ($performanceData) {
            $siteUrl = $this->getCurrentSiteUrl();
            $blogUrl = rtrim($siteUrl, '/') . '/blogs/' . $blog->slug;
            
            $performance = $performanceData->get($blogUrl, [
                'total_clicks' => 0,
                'total_impressions' => 0,
                'avg_ctr' => 0,
                'avg_position' => 100,
                'queries_count' => 0,
            ]);

            // Performance score algorithm
            $score = 
                ($performance['total_clicks'] * 10) +           // Clicks zwaarst gewogen
                ($performance['total_impressions'] * 0.5) +     // Impressions = zichtbaarheid
                ($performance['avg_ctr'] * 100) +               // CTR = relevantie
                (100 - min($performance['avg_position'], 100)); // Positie (omgekeerd)

            return [
                'blog' => $blog,
                'performance' => $performance,
                'score' => round($score, 2),
                'age_days' => $blog->created_at->diffInDays(Carbon::now()),
            ];
        })->sortByDesc('score');
    }

    private function categorizeBlogs(Collection $scoredBlogs, int $keepPercentage, int $gracePeriodDays): array
    {
        $total = $scoredBlogs->count();
        $keepCount = (int) ceil($total * ($keepPercentage / 100));
        
        $winners = collect();
        $candidates = collect();
        $losers = collect();
        
        $scoredBlogs->each(function ($blogData, $index) use ($keepCount, $gracePeriodDays, &$winners, &$candidates, &$losers) {
            // Grace period check - content tussen 30-60 dagen krijgt bonus behandeling
            $inGracePeriod = $blogData['age_days'] <= $gracePeriodDays;
            
            if ($index < $keepCount) {
                $winners->push($blogData);
            } elseif ($inGracePeriod && $blogData['performance']['total_impressions'] > 0) {
                // Grace period content met impressions wordt kandidaat
                $candidates->push($blogData);
            } elseif ($blogData['performance']['total_impressions'] > 2 || $blogData['performance']['total_clicks'] > 0) {
                // Content met enige performance wordt kandidaat
                $candidates->push($blogData);
            } else {
                $losers->push($blogData);
            }
        });

        return compact('winners', 'candidates', 'losers');
    }

    private function showResults(array $categories): void
    {
        $this->newLine();
        $this->info('📊 Audit Resultaten:');

        // Winners tabel
        $this->line('🏆 Winners (behouden + optimaliseren):');
        $this->table(
            ['Titel', 'Leeftijd', 'Clicks', 'Impressions', 'CTR%', 'Pos', 'Score'],
            $categories['winners']->take(10)->map(fn($item) => [
                \Str::limit($item['blog']->title, 40),
                $item['age_days'] . 'd',
                $item['performance']['total_clicks'],
                $item['performance']['total_impressions'],
                round($item['performance']['avg_ctr'] * 100, 1),
                round($item['performance']['avg_position'], 1),
                $item['score'],
            ])
        );

        $this->line("🥈 Kandidaten (verbeteren): {$categories['candidates']->count()} blogs");
        $this->line("❌ Verliezers (noindex overwegen): {$categories['losers']->count()} blogs");

        // Performance samenvatting
        $totalWinners = $categories['winners']->count();
        $totalCandidates = $categories['candidates']->count(); 
        $totalLosers = $categories['losers']->count();
        $total = $totalWinners + $totalCandidates + $totalLosers;

        $this->newLine();
        $this->info("📈 Samenvatting:");
        $this->line("   • Totaal geanalyseerd: {$total} blogs");
        $this->line("   • Winners: {$totalWinners} (" . round(($totalWinners/$total)*100, 1) . "%)");
        $this->line("   • Kandidaten: {$totalCandidates} (" . round(($totalCandidates/$total)*100, 1) . "%)");
        $this->line("   • Verliezers: {$totalLosers} (" . round(($totalLosers/$total)*100, 1) . "%)");
    }

    private function executeActions(array $categories): void
    {
        if ($categories['losers']->isEmpty()) {
            $this->info('✅ Geen acties nodig - geen verliezers gevonden');
            return;
        }

        $this->newLine();
        $this->warn("⚠️ UITVOERING: Noindex toepassen op {$categories['losers']->count()} blogs");
        
        if (!$this->option('force') && !$this->confirm('Weet je zeker dat je deze wijzigingen wilt doorvoeren?')) {
            $this->info('❌ Geannuleerd door gebruiker');
            return;
        }

        $noindexed = 0;
        foreach ($categories['losers'] as $blogData) {
            $blog = $blogData['blog'];
            
            // Voeg noindex meta tag toe (dit hangt af van je meta_description implementatie)
            $blog->update([
                'meta_robots' => 'noindex,nofollow'
            ]);
            
            $noindexed++;
            $this->line("   ✅ Noindex: {$blog->title}");
        }

        $this->info("🎉 {$noindexed} blogs succesvol op noindex gezet!");
    }

    private function getCurrentSiteUrl(): ?string
    {
        // Gebruik dezelfde logica als in FetchSearchConsoleData command
        $siteUrl = env('GSC_SITE_URL');
        
        if (!$siteUrl) {
            $appName = env('APP_NAME', '');
            if (str_contains($appName, '.')) {
                $siteUrl = 'https://' . rtrim($appName, '/') . '/';
            }
        }

        if (!$siteUrl) {
            $appUrl = env('APP_URL', '');
            if ($appUrl && !str_contains($appUrl, '127.0.0.1') && !str_contains($appUrl, 'localhost')) {
                $siteUrl = rtrim($appUrl, '/') . '/';
            }
        }

        return $siteUrl;
    }
}