<?php

namespace App\Console\Commands\Review;

use Illuminate\Console\Command;
use App\Models\Review;
use App\Models\SearchConsoleData;
use Carbon\Carbon;

class CleanupReviews extends Command
{
    protected $signature = 'reviews:cleanup
                            {--top=10 : Aantal reviews om te behouden}
                            {--days=0 : Aantal dagen GSC data (0 = all time)}
                            {--dry-run : Alleen tonen, niets wijzigen}
                            {--force : Geen bevestiging vragen}';

    protected $description = 'Behoud alleen de best presterende reviews, zet de rest naar draft';

    public function handle()
    {
        $topCount = (int) $this->option('top');
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        // Haal alle gepubliceerde reviews op
        $reviews = Review::where('status', 'published')->get();

        if ($reviews->count() <= $topCount) {
            $this->info("Je hebt slechts {$reviews->count()} reviews - geen cleanup nodig.");
            return 0;
        }

        // Bereken scores
        $ranked = $this->calculateScores($reviews, $days);

        // Toon top reviews
        $this->info("Top {$topCount} reviews (blijven published):");
        $ranked->take($topCount)->each(function($item, $i) {
            $this->line(($i + 1) . ". " . $item['review']->title);
        });

        // Toon aantal dat naar draft gaat
        $toDraftCount = $reviews->count() - $topCount;
        $this->info("\n{$toDraftCount} reviews gaan naar draft.");

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
                $item['review']->update(['status' => 'draft']);
            }
            $this->info("Klaar.");
        } else {
            $this->info("Dry-run mode - niets gewijzigd.");
        }

        return 0;
    }

    private function calculateScores($reviews, $days)
    {
        $startDate = $days > 0 ? Carbon::now()->subDays($days) : null;

        return $reviews->map(function($review) use ($startDate) {
            // GSC score proberen
            $gscScore = $this->getGscScore($review, $startDate);

            // Fallback score (aangepast voor reviews)
            $fallbackScore = $this->getFallbackScore($review);

            // Final score: GSC heeft voorrang
            $finalScore = $gscScore > 0 ? $gscScore : $fallbackScore;

            return [
                'review' => $review,
                'score' => $finalScore,
            ];
        })->sortByDesc('score')->values();
    }

    private function getGscScore($review, $startDate)
    {
        // Extract keywords uit titel
        $keywords = explode(' ', strtolower($review->title));
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

    private function getFallbackScore($review)
    {
        // Content lengte score
        $contentLength = strlen(strip_tags($review->content));
        $contentScore = min(50, $contentLength / 50);

        // Versheid score
        $ageDays = $review->created_at->diffInDays(Carbon::now());
        $freshnessScore = max(0, 100 - $ageDays);

        // Review-specifieke bonussen
        $reviewBonus = 0;

        // Rating bonus (hogere rating = betere review)
        if ($review->rating) {
            $reviewBonus += ($review->rating / 5) * 20; // Max 20 punten voor 5-star
        }

        // Product connectie bonus
        if ($review->product_id) {
            $reviewBonus += 15;

            // Extra bonus voor dure producten (hogere conversie waarde)
            $product = $review->product;
            if ($product && ($product->price ?? 0) > 200) {
                $reviewBonus += 10;
            }
        }

        // Complete review bonus (heeft alle secties)
        if ($review->intro && $review->experience && $review->positives && $review->conclusion) {
            $reviewBonus += 15;
        }

        return $contentScore + $freshnessScore + $reviewBonus;
    }
}