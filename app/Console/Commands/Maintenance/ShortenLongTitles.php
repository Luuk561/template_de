<?php

namespace App\Console\Commands\Maintenance;

use App\Models\Product;
use App\Models\Review;
use App\Models\BlogPost;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ShortenLongTitles extends Command
{
    protected $signature = 'seo:shorten-titles {--dry-run : Show what would be changed without actually changing it} {--max=60 : Maximum title length}';
    protected $description = 'Shorten overly long titles by keeping first N words that fit';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $maxLength = (int) $this->option('max');

        if ($dryRun) {
            $this->info('Running in DRY RUN mode - no changes will be made');
            $this->newLine();
        }

        $totalUpdated = 0;

        // Shorten Products
        $this->info('Shortening Product titles...');
        $totalUpdated += $this->shortenProducts($dryRun, $maxLength);
        $this->newLine();

        // Shorten Reviews
        $this->info('Shortening Review titles...');
        $totalUpdated += $this->shortenReviews($dryRun, $maxLength);
        $this->newLine();

        // Shorten Blogs
        $this->info('Shortening Blog titles...');
        $totalUpdated += $this->shortenBlogs($dryRun, $maxLength);
        $this->newLine();

        if ($dryRun) {
            $this->info("DRY RUN complete. {$totalUpdated} titles would be shortened.");
            $this->info("Run without --dry-run to apply changes.");
        } else {
            $this->info("Successfully shortened {$totalUpdated} titles!");
        }

        return 0;
    }

    private function shortenProducts($dryRun, $maxLength)
    {
        $updated = 0;
        $products = Product::all();

        foreach ($products as $product) {
            $originalTitle = $product->title;

            // Remove EAN suffix if present (added by previous command)
            $cleanTitle = preg_replace('/\s*\(EAN:\s*[^\)]*\)\s*$/', '', $originalTitle);
            $eanSuffix = '';

            if ($cleanTitle !== $originalTitle && $product->ean) {
                // Store EAN suffix to re-add later
                $eanSuffix = ' (EAN: ' . $product->ean . ')';
            }

            // Check if clean title needs shortening
            $projectedLength = mb_strlen($cleanTitle) + mb_strlen($eanSuffix);

            if ($projectedLength <= $maxLength) {
                continue;
            }

            // Shorten the clean title (without EAN), leaving room for EAN suffix
            $targetLength = $maxLength - mb_strlen($eanSuffix);
            $newTitle = $this->smartShorten($cleanTitle, $targetLength, $product->brand);

            // Re-add EAN suffix if it was there
            $finalTitle = trim($newTitle) . $eanSuffix;

            if ($finalTitle !== $originalTitle) {
                $this->warn("Product #{$product->id}:");
                $this->line("  OLD (" . mb_strlen($originalTitle) . " chars): {$originalTitle}");
                $this->line("  NEW (" . mb_strlen($finalTitle) . " chars): {$finalTitle}");
                $this->newLine();

                if (!$dryRun) {
                    $product->title = $finalTitle;
                    $product->save();
                    $updated++;
                }
            }
        }

        return $updated;
    }

    private function shortenReviews($dryRun, $maxLength)
    {
        $updated = 0;
        $reviews = Review::all();

        foreach ($reviews as $review) {
            if (mb_strlen($review->title) <= $maxLength) {
                continue;
            }

            $newTitle = $this->smartShorten($review->title, $maxLength);

            if ($newTitle !== $review->title) {
                $this->warn("Review #{$review->id}:");
                $this->line("  OLD (" . mb_strlen($review->title) . " chars): {$review->title}");
                $this->line("  NEW (" . mb_strlen($newTitle) . " chars): {$newTitle}");
                $this->newLine();

                if (!$dryRun) {
                    $review->title = $newTitle;
                    $review->save();
                    $updated++;
                }
            }
        }

        return $updated;
    }

    private function shortenBlogs($dryRun, $maxLength)
    {
        $updated = 0;
        $blogs = BlogPost::all();

        foreach ($blogs as $blog) {
            if (mb_strlen($blog->title) <= $maxLength) {
                continue;
            }

            $newTitle = $this->smartShorten($blog->title, $maxLength);

            if ($newTitle !== $blog->title) {
                $this->warn("Blog #{$blog->id}:");
                $this->line("  OLD (" . mb_strlen($blog->title) . " chars): {$blog->title}");
                $this->line("  NEW (" . mb_strlen($newTitle) . " chars): {$newTitle}");
                $this->newLine();

                if (!$dryRun) {
                    $blog->title = $newTitle;
                    $blog->save();
                    $updated++;
                }
            }
        }

        return $updated;
    }

    private function smartShorten($title, $maxLength, $brand = null)
    {
        // Normalize problematic UTF-8 characters to avoid encoding errors
        $title = str_replace(
            [
                "\xE2\x80\x98", "\xE2\x80\x99", "\xE2\x80\x9C", "\xE2\x80\x9D", // Smart quotes
                "\xE2\x80\x93", "\xE2\x80\x94", // En/em dashes
                "\xE2\x82\xAC", // Euro symbol €
                "\xC2\xAE", // ® registered
                "\xE2\x84\xA2", // ™ trademark
                "\xE2\x80\x8E", "\xE2\x80\x8F", // Invisible direction marks
            ],
            ["'", "'", '"', '"', '-', '-', 'EUR', '', '', '', ''],
            $title
        );

        // Clean up any broken UTF-8 sequences
        $title = mb_convert_encoding($title, 'UTF-8', 'UTF-8');

        // If already fits, return as-is
        if (mb_strlen($title) <= $maxLength) {
            return $title;
        }

        // Simple strategy: Keep first N words that fit within maxLength
        // This preserves brand, product type, model - the most important parts
        $words = preg_split('/\s+/', $title);
        $shortened = '';

        foreach ($words as $word) {
            $test = trim($shortened . ' ' . $word);

            if (mb_strlen($test) <= $maxLength) {
                $shortened = $test;
            } else {
                // If we haven't added any words yet, at least take first word truncated
                if (empty($shortened)) {
                    $shortened = mb_substr($word, 0, $maxLength);
                }
                break;
            }
        }

        return trim($shortened);
    }
}
