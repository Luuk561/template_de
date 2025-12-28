<?php

namespace App\Console\Commands\Maintenance;

use App\Models\BlogPost;
use Illuminate\Console\Command;

class FixDuplicateBlogs extends Command
{
    protected $signature = 'blogs:fix-duplicates {--dry-run : Show what would be changed without actually changing it}';
    protected $description = 'Fix duplicate blog titles and meta descriptions by adding unique identifiers';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('Running in DRY RUN mode - no changes will be made');
            $this->newLine();
        }

        $totalUpdated = 0;

        // Fix duplicate titles
        $this->info('Checking for duplicate blog titles...');
        $duplicateTitles = BlogPost::selectRaw('title, COUNT(*) as count')
            ->where('status', 'published')
            ->groupBy('title')
            ->having('count', '>', 1)
            ->pluck('title');

        if ($duplicateTitles->isNotEmpty()) {
            $this->warn("Found {$duplicateTitles->count()} titles with duplicates");
            $this->newLine();

            foreach ($duplicateTitles as $duplicateTitle) {
                $blogs = BlogPost::where('title', $duplicateTitle)->where('status', 'published')->get();

                $this->line("Title: {$duplicateTitle} ({$blogs->count()} duplicates)");

                foreach ($blogs as $blog) {
                    $originalTitle = $blog->title;
                    $newTitle = null;

                    if ($blog->product && $blog->product->brand) {
                        $newTitle = $originalTitle . ' - ' . $blog->product->brand;
                    } elseif ($blog->id) {
                        // Fallback: add blog ID
                        $newTitle = $originalTitle . ' (Blog #' . $blog->id . ')';
                    }

                    if ($newTitle && $newTitle !== $originalTitle) {
                        $this->line("  - Blog #{$blog->id}: {$originalTitle} → {$newTitle}");

                        if (!$dryRun) {
                            $blog->title = $newTitle;
                            $blog->save();
                            $totalUpdated++;
                        }
                    }
                }

                $this->newLine();
            }
        } else {
            $this->info('No duplicate blog titles found!');
        }

        // Fix duplicate meta descriptions
        $this->newLine();
        $this->info('Checking for duplicate blog meta descriptions...');

        $duplicateDescs = BlogPost::selectRaw('meta_description, COUNT(*) as count')
            ->where('status', 'published')
            ->whereNotNull('meta_description')
            ->where('meta_description', '!=', '')
            ->groupBy('meta_description')
            ->having('count', '>', 1)
            ->pluck('meta_description');

        if ($duplicateDescs->isNotEmpty()) {
            $this->warn("Found {$duplicateDescs->count()} meta descriptions with duplicates");
            $this->newLine();

            foreach ($duplicateDescs as $duplicateDesc) {
                $blogs = BlogPost::where('meta_description', $duplicateDesc)->where('status', 'published')->get();

                foreach ($blogs as $blog) {
                    $originalDesc = $blog->meta_description;
                    $newDesc = null;

                    if ($blog->product && $blog->product->brand) {
                        $newDesc = $originalDesc . ' - ' . $blog->product->brand;
                    } elseif ($blog->id) {
                        $newDesc = $originalDesc . ' (ID: ' . $blog->id . ')';
                    }

                    if ($newDesc && $newDesc !== $originalDesc) {
                        if (!$dryRun) {
                            $blog->meta_description = $newDesc;
                            $blog->save();
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
            $this->info("DRY RUN complete. {$totalUpdated} blogs would be updated.");
            $this->info("Run without --dry-run to apply changes.");
        } else {
            $this->info("Successfully updated {$totalUpdated} blogs!");
        }

        return 0;
    }
}
