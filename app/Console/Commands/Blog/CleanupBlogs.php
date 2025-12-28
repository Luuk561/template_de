<?php

namespace App\Console\Commands\Blog;

use Illuminate\Console\Command;
use App\Models\BlogPost;
use App\Models\SearchConsoleData;
use Carbon\Carbon;

class CleanupBlogs extends Command
{
    protected $signature = 'blogs:cleanup
                            {--top=20 : Aantal blogs om te behouden}
                            {--days=0 : Aantal dagen GSC data (0 = all time)}
                            {--dry-run : Alleen tonen, niets wijzigen}
                            {--force : Geen bevestiging vragen}';

    protected $description = 'Behoud alleen de best presterende blogs, zet de rest naar draft';

    public function handle()
    {
        $topCount = (int) $this->option('top');
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        // Haal alle gepubliceerde blogs op
        $blogs = BlogPost::where('status', 'published')->get();

        if ($blogs->count() <= $topCount) {
            $this->info("Je hebt slechts {$blogs->count()} blogs - geen cleanup nodig.");
            return 0;
        }

        // Bereken scores
        $ranked = $this->calculateScores($blogs, $days);

        // Toon top blogs
        $this->info("Top {$topCount} blogs (blijven published):");
        $ranked->take($topCount)->each(function($item, $i) {
            $this->line(($i + 1) . ". " . $item['blog']->title);
        });

        // Toon aantal dat naar draft gaat
        $toDraftCount = $blogs->count() - $topCount;
        $this->info("\n{$toDraftCount} blogs gaan naar draft.");

        // Bevestiging
        if (!$force && !$dryRun) {
            $confirmation = $this->ask("Typ 'JA' om door te gaan:");
            if (strtoupper($confirmation) !== 'JA') {
                $this->info("Geannuleerd.");
                return 0;
            }
        }

        // Uitvoeren
        if (!$dryRun) {
            $toDraft = $ranked->skip($topCount);
            foreach ($toDraft as $item) {
                $item['blog']->update(['status' => 'draft']);
            }
            $this->info("Klaar.");
        } else {
            $this->info("Dry-run mode - niets gewijzigd.");
        }

        return 0;
    }

    private function calculateScores($blogs, $days)
    {
        $startDate = $days > 0 ? Carbon::now()->subDays($days) : null;

        return $blogs->map(function($blog) use ($startDate) {
            // GSC score proberen
            $gscScore = $this->getGscScore($blog, $startDate);

            // Fallback score
            $fallbackScore = $this->getFallbackScore($blog);

            // Final score: GSC heeft voorrang
            $finalScore = $gscScore > 0 ? $gscScore : $fallbackScore;

            return [
                'blog' => $blog,
                'score' => $finalScore,
            ];
        })->sortByDesc('score')->values();
    }

    private function getGscScore($blog, $startDate)
    {
        // Extract keywords uit titel
        $keywords = explode(' ', strtolower($blog->title));
        $meaningfulWords = array_filter($keywords, fn($w) => strlen($w) >= 4);

        if (empty($meaningfulWords)) {
            return 0;
        }

        $searchTerm = implode(' ', array_slice($meaningfulWords, 0, 3));

        $query = SearchConsoleData::where('query', 'like', "%{$searchTerm}%");

        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }

        $gscData = $query->get();

        if ($gscData->isEmpty()) {
            return 0;
        }

        $clicks = $gscData->sum('clicks');
        $impressions = $gscData->sum('impressions');
        $avgPosition = $gscData->avg('position') ?? 100;
        $ctr = $impressions > 0 ? ($clicks / $impressions) : 0;

        // Simple scoring
        return ($clicks * 10) + ($impressions * 0.1) + (max(0, 100 - $avgPosition)) + ($ctr * 100);
    }

    private function getFallbackScore($blog)
    {
        // Content lengte score
        $contentLength = strlen(strip_tags($blog->content));
        $contentScore = min(50, $contentLength / 50);

        // Versheid score
        $ageDays = $blog->created_at->diffInDays(Carbon::now());
        $freshnessScore = max(0, 100 - $ageDays);

        return $contentScore + $freshnessScore;
    }
}