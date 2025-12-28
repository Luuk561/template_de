<?php

namespace App\Console\Commands\Maintenance;

use App\Models\BlogPost;
use App\Models\Review;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class RepublishDraftContent extends Command
{
    protected $signature = 'content:republish-drafts
                            {--type=all : Type of content to republish (all, blogs, reviews)}
                            {--limit= : Maximum number of items to republish (leave empty for all)}
                            {--dry-run : Show what would be republished without actually doing it}
                            {--before= : Only republish content created before this date (YYYY-MM-DD)}
                            {--csv= : Path to Google Search Console CSV export to filter by URLs with actual traffic}';

    protected $description = 'Republish draft blogs and reviews that were previously published (optionally filtered by GSC traffic data)';

    public function handle()
    {
        $type = $this->option('type');
        $limit = $this->option('limit');
        $dryRun = $this->option('dry-run');
        $before = $this->option('before');
        $csvPath = $this->option('csv');

        $this->info('Analyzing draft content...');
        $this->newLine();

        // Parse CSV if provided
        $targetSlugs = null;
        if ($csvPath) {
            $targetSlugs = $this->parseCsvForSlugs($csvPath);

            if (empty($targetSlugs)) {
                $this->error('No blog or review URLs found in CSV file.');
                return 1;
            }
        }

        // Prepare queries
        $blogsQuery = BlogPost::where('status', 'draft');
        $reviewsQuery = Review::where('status', 'draft');

        // Apply CSV filter if provided
        if ($targetSlugs) {
            $blogsQuery->whereIn('slug', $targetSlugs);
            $reviewsQuery->whereIn('slug', $targetSlugs);
        }

        // Apply date filter if specified
        if ($before) {
            $blogsQuery->where('created_at', '<', $before);
            $reviewsQuery->where('created_at', '<', $before);
        }

        // Get counts
        $draftBlogsCount = $blogsQuery->count();
        $draftReviewsCount = $reviewsQuery->count();

        // Show overview
        $this->table(
            ['Content Type', 'Draft Count'],
            [
                ['Blogs', $draftBlogsCount],
                ['Reviews', $draftReviewsCount],
                ['Total', $draftBlogsCount + $draftReviewsCount],
            ]
        );

        if ($draftBlogsCount === 0 && $draftReviewsCount === 0) {
            $this->warn('No draft content found to republish.');
            return 0;
        }

        $this->newLine();

        // Process based on type
        $republishedCount = 0;

        if (in_array($type, ['all', 'blogs']) && $draftBlogsCount > 0) {
            $republishedCount += $this->republishContent(
                $blogsQuery,
                'BlogPost',
                $limit && $type === 'blogs' ? $limit : ($limit && $type === 'all' ? floor($limit / 2) : null),
                $dryRun
            );
        }

        if (in_array($type, ['all', 'reviews']) && $draftReviewsCount > 0) {
            $republishedCount += $this->republishContent(
                $reviewsQuery,
                'Review',
                $limit && $type === 'reviews' ? $limit : ($limit && $type === 'all' ? floor($limit / 2) : null),
                $dryRun
            );
        }

        $this->newLine();

        if ($dryRun) {
            $this->info("Dry run completed. Would have republished {$republishedCount} items.");
            $this->comment('Run without --dry-run to actually republish the content.');
        } else {
            $this->info("Successfully republished {$republishedCount} items!");
            $this->comment('Tip: Monitor Google Search Console over the next 1-2 weeks to see traffic recovery.');
        }

        return 0;
    }

    private function republishContent($query, $modelName, $limit, $dryRun)
    {
        // Order by created_at (oldest first, as they're most likely to have had traffic)
        $items = $query->orderBy('created_at', 'asc');

        if ($limit) {
            $items = $items->limit($limit);
        }

        $items = $items->get();
        $count = $items->count();

        if ($count === 0) {
            return 0;
        }

        $this->info("Processing {$count} {$modelName}(s)...");

        $progressBar = $this->output->createProgressBar($count);
        $progressBar->start();

        foreach ($items as $item) {
            if (!$dryRun) {
                // Update status to published and touch updated_at
                $item->status = 'published';
                $item->updated_at = now();
                $item->save();
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        return $count;
    }

    private function parseCsvForSlugs($csvPath)
    {
        if (!File::exists($csvPath)) {
            $this->error("CSV file not found: {$csvPath}");
            return [];
        }

        $this->info("Parsing CSV file: {$csvPath}");

        $slugs = [];
        $totalUrls = 0;
        $blogReviewUrls = 0;

        // Read CSV file
        $file = fopen($csvPath, 'r');
        $headers = fgetcsv($file);

        // Find the column index for URLs (usually "Top pages" or "Page")
        $urlColumnIndex = array_search('Top pages', $headers);
        if ($urlColumnIndex === false) {
            $urlColumnIndex = array_search('Page', $headers);
        }
        if ($urlColumnIndex === false) {
            $urlColumnIndex = 0; // Default to first column
        }

        while (($row = fgetcsv($file)) !== false) {
            if (!isset($row[$urlColumnIndex])) {
                continue;
            }

            $url = $row[$urlColumnIndex];
            $totalUrls++;

            // Filter for blog or review URLs
            if (preg_match('#/(blogs?|reviews?)/([^/?]+)#i', $url, $matches)) {
                $slug = $matches[2];
                $slugs[] = $slug;
                $blogReviewUrls++;
            }
        }

        fclose($file);

        $slugs = array_unique($slugs);

        $this->info("Found {$totalUrls} total URLs in CSV");
        $this->info("Filtered to {$blogReviewUrls} blog/review URLs");
        $this->info("Unique slugs to match: " . count($slugs));
        $this->newLine();

        return $slugs;
    }
}
