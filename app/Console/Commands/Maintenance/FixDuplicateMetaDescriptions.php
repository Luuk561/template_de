<?php

namespace App\Console\Commands\Maintenance;

use App\Models\Product;
use App\Models\Review;
use App\Models\BlogPost;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class FixDuplicateMetaDescriptions extends Command
{
    protected $signature = 'seo:fix-duplicate-descriptions {--dry-run : Show what would be changed without actually changing it}';
    protected $description = 'Fix duplicate meta descriptions by adding unique identifiers';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('Running in DRY RUN mode - no changes will be made');
            $this->newLine();
        }

        $totalUpdated = 0;

        // Fix Products
        $this->info('Fixing Product meta descriptions...');
        $totalUpdated += $this->fixProducts($dryRun);
        $this->newLine();

        // Fix Reviews
        $this->info('Fixing Review meta descriptions...');
        $totalUpdated += $this->fixReviews($dryRun);
        $this->newLine();

        // Fix Blog Posts
        $this->info('Fixing Blog Post meta descriptions...');
        $totalUpdated += $this->fixBlogs($dryRun);
        $this->newLine();

        if ($dryRun) {
            $this->info("DRY RUN complete. {$totalUpdated} items would be updated.");
            $this->info("Run without --dry-run to apply changes.");
        } else {
            $this->info("Successfully updated {$totalUpdated} meta descriptions!");
        }

        return 0;
    }

    private function fixProducts($dryRun)
    {
        $updated = 0;

        // Find duplicates based on meta_description, ai_summary, or description
        $products = Product::all();
        $descriptionGroups = [];

        foreach ($products as $product) {
            $baseDesc = $product->meta_description
                       ?? $product->ai_summary
                       ?? Str::limit(strip_tags($product->source_description ?: $product->description), 150);

            if (!isset($descriptionGroups[$baseDesc])) {
                $descriptionGroups[$baseDesc] = [];
            }
            $descriptionGroups[$baseDesc][] = $product;
        }

        // Only process groups with duplicates
        foreach ($descriptionGroups as $desc => $productGroup) {
            if (count($productGroup) <= 1) {
                continue;
            }

            $this->warn("Description: " . Str::limit($desc, 80) . " ({" . count($productGroup) . "} duplicates)");

            foreach ($productGroup as $product) {
                // Add EAN suffix if not already present
                if ($product->ean && stripos($desc, $product->ean) === false) {
                    $maxLength = 155 - strlen($product->ean) - strlen(' (EAN: )');
                    $newDesc = Str::limit($desc, $maxLength, '');
                    $newDesc .= ' (EAN: ' . $product->ean . ')';

                    $this->line("  - Product #{$product->id}: Adding EAN suffix");

                    if (!$dryRun) {
                        $product->meta_description = $newDesc;
                        $product->save();
                        $updated++;
                    }
                }
            }
        }

        return $updated;
    }

    private function fixReviews($dryRun)
    {
        $updated = 0;

        $reviews = Review::with('product')->get();
        $descriptionGroups = [];

        foreach ($reviews as $review) {
            $baseDesc = $review->meta_description ?? $review->excerpt;

            if (!isset($descriptionGroups[$baseDesc])) {
                $descriptionGroups[$baseDesc] = [];
            }
            $descriptionGroups[$baseDesc][] = $review;
        }

        foreach ($descriptionGroups as $desc => $reviewGroup) {
            if (count($reviewGroup) <= 1) {
                continue;
            }

            $this->warn("Description: " . Str::limit($desc, 80) . " ({" . count($reviewGroup) . "} duplicates)");

            foreach ($reviewGroup as $review) {
                if ($review->product && stripos($desc, $review->product->title) === false) {
                    $suffix = ' - ' . Str::limit($review->product->title, 50, '');
                    $maxLength = 155 - strlen($suffix);
                    $newDesc = Str::limit($desc, $maxLength, '');
                    $newDesc .= $suffix;

                    $this->line("  - Review #{$review->id}: Adding product title suffix");

                    if (!$dryRun) {
                        $review->meta_description = $newDesc;
                        $review->save();
                        $updated++;
                    }
                }
            }
        }

        return $updated;
    }

    private function fixBlogs($dryRun)
    {
        $updated = 0;

        $blogs = BlogPost::all();
        $descriptionGroups = [];

        foreach ($blogs as $blog) {
            $baseDesc = $blog->meta_description ?? $blog->excerpt;

            if (!isset($descriptionGroups[$baseDesc])) {
                $descriptionGroups[$baseDesc] = [];
            }
            $descriptionGroups[$baseDesc][] = $blog;
        }

        foreach ($descriptionGroups as $desc => $blogGroup) {
            if (count($blogGroup) <= 1) {
                continue;
            }

            $this->warn("Description: " . Str::limit($desc, 80) . " ({" . count($blogGroup) . "} duplicates)");

            foreach ($blogGroup as $blog) {
                // Add slug or ID suffix
                if (stripos($desc, $blog->slug) === false) {
                    $suffix = ' (ID: ' . $blog->id . ')';
                    $maxLength = 155 - strlen($suffix);
                    $newDesc = Str::limit($desc, $maxLength, '');
                    $newDesc .= $suffix;

                    $this->line("  - Blog #{$blog->id}: Adding ID suffix");

                    if (!$dryRun) {
                        $blog->meta_description = $newDesc;
                        $blog->save();
                        $updated++;
                    }
                }
            }
        }

        return $updated;
    }
}
