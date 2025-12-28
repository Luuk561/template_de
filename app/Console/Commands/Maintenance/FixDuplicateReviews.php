<?php

namespace App\Console\Commands\Maintenance;

use App\Models\Review;
use Illuminate\Console\Command;

class FixDuplicateReviews extends Command
{
    protected $signature = 'reviews:fix-duplicates {--dry-run : Show what would be changed without actually changing it}';
    protected $description = 'Fix duplicate review titles and meta descriptions by adding unique identifiers';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('Running in DRY RUN mode - no changes will be made');
            $this->newLine();
        }

        $totalUpdated = 0;

        // Fix duplicate titles
        $this->info('Checking for duplicate review titles...');
        $duplicateTitles = Review::selectRaw('title, COUNT(*) as count')
            ->groupBy('title')
            ->having('count', '>', 1)
            ->pluck('title');

        if ($duplicateTitles->isNotEmpty()) {
            $this->warn("Found {$duplicateTitles->count()} titles with duplicates");
            $this->newLine();

            foreach ($duplicateTitles as $duplicateTitle) {
                $reviews = Review::where('title', $duplicateTitle)->get();

                $this->line("Title: {$duplicateTitle} ({$reviews->count()} duplicates)");

                foreach ($reviews as $review) {
                    $originalTitle = $review->title;
                    $newTitle = null;

                    if ($review->product) {
                        // Use product's unique title + Review suffix
                        $newTitle = $review->product->seo_title . ' - Review';
                    } elseif ($review->id) {
                        // Fallback: add review ID
                        $newTitle = $originalTitle . ' (Review #' . $review->id . ')';
                    }

                    if ($newTitle && $newTitle !== $originalTitle) {
                        $this->line("  - Review #{$review->id}: {$originalTitle} → {$newTitle}");

                        if (!$dryRun) {
                            $review->title = $newTitle;
                            $review->save();
                            $totalUpdated++;
                        }
                    }
                }

                $this->newLine();
            }
        } else {
            $this->info('No duplicate review titles found!');
        }

        // Fix duplicate meta descriptions
        $this->newLine();
        $this->info('Checking for duplicate review meta descriptions...');

        $duplicateDescs = Review::selectRaw('meta_description, COUNT(*) as count')
            ->whereNotNull('meta_description')
            ->where('meta_description', '!=', '')
            ->groupBy('meta_description')
            ->having('count', '>', 1)
            ->pluck('meta_description');

        if ($duplicateDescs->isNotEmpty()) {
            $this->warn("Found {$duplicateDescs->count()} meta descriptions with duplicates");
            $this->newLine();

            foreach ($duplicateDescs as $duplicateDesc) {
                $reviews = Review::where('meta_description', $duplicateDesc)->get();

                foreach ($reviews as $review) {
                    $originalDesc = $review->meta_description;
                    $newDesc = null;

                    if ($review->product && $review->product->brand) {
                        $newDesc = $originalDesc . ' - ' . $review->product->brand;
                    } elseif ($review->product) {
                        $newDesc = $originalDesc . ' (Review #' . $review->id . ')';
                    }

                    if ($newDesc && $newDesc !== $originalDesc) {
                        if (!$dryRun) {
                            $review->meta_description = $newDesc;
                            $review->save();
                            $totalUpdated++;
                        }
                    }
                }
            }

            $this->info("Fixed {$duplicateDescs->count()} duplicate meta descriptions");
        } else {
            $this->info('No duplicate meta descriptions found!');
        }

        $this->newLine();
        if ($dryRun) {
            $this->info("DRY RUN complete. {$totalUpdated} reviews would be updated.");
            $this->info("Run without --dry-run to apply changes.");
        } else {
            $this->info("Successfully updated {$totalUpdated} reviews!");
        }

        return 0;
    }
}
