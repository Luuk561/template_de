<?php

namespace App\Console\Commands\Maintenance;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixDuplicateTitles extends Command
{
    protected $signature = 'maintenance:fix-duplicate-titles {--dry-run : Only show what would be fixed}';

    protected $description = 'Find and report duplicate title tags across the site';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN - Showing duplicate title issues');
            $this->newLine();
        }

        $issues = [];

        // 1. Check products with duplicate titles
        $this->info('Checking products...');
        $productDuplicates = DB::table('products')
            ->select('title', DB::raw('COUNT(*) as count'))
            ->groupBy('title')
            ->having('count', '>', 1)
            ->get();

        foreach ($productDuplicates as $duplicate) {
            $products = DB::table('products')
                ->where('title', $duplicate->title)
                ->get(['id', 'slug', 'title']);

            $issues[] = [
                'type' => 'Product',
                'title' => $duplicate->title,
                'count' => $duplicate->count,
                'urls' => $products->map(fn($p) => '/producten/' . $p->slug)->toArray(),
            ];
        }

        // 2. Check reviews with duplicate titles
        $this->info('Checking reviews...');
        $reviewDuplicates = DB::table('reviews')
            ->select('title', DB::raw('COUNT(*) as count'))
            ->groupBy('title')
            ->having('count', '>', 1)
            ->get();

        foreach ($reviewDuplicates as $duplicate) {
            $reviews = DB::table('reviews')
                ->where('title', $duplicate->title)
                ->get(['id', 'slug', 'title']);

            $issues[] = [
                'type' => 'Review',
                'title' => $duplicate->title,
                'count' => $duplicate->count,
                'urls' => $reviews->map(fn($r) => '/reviews/' . $r->slug)->toArray(),
            ];
        }

        // 3. Check blogs with duplicate titles
        $this->info('Checking blogs...');
        $blogDuplicates = DB::table('blog_posts')
            ->select('title', DB::raw('COUNT(*) as count'))
            ->groupBy('title')
            ->having('count', '>', 1)
            ->get();

        foreach ($blogDuplicates as $duplicate) {
            $blogs = DB::table('blog_posts')
                ->where('title', $duplicate->title)
                ->get(['id', 'slug', 'title']);

            $issues[] = [
                'type' => 'Blog',
                'title' => $duplicate->title,
                'count' => $duplicate->count,
                'urls' => $blogs->map(fn($b) => '/blogs/' . $b->slug)->toArray(),
            ];
        }

        // 4. Check team members with empty/missing names
        $this->info('Checking team members...');
        $teamIssues = DB::table('team_members')
            ->whereNull('name')
            ->orWhere('name', '')
            ->get(['id', 'slug', 'name']);

        foreach ($teamIssues as $member) {
            $issues[] = [
                'type' => 'Team Member',
                'title' => '(empty or null)',
                'count' => 1,
                'urls' => ['/team/' . $member->slug],
            ];
        }

        $this->newLine();

        if (empty($issues)) {
            $this->info('No duplicate or missing title issues found!');
            return 0;
        }

        $this->warn('Found ' . count($issues) . ' title issue(s):');
        $this->newLine();

        foreach ($issues as $issue) {
            $this->line("<fg=yellow>{$issue['type']}</>: \"{$issue['title']}\" ({$issue['count']} occurrence(s))");
            foreach ($issue['urls'] as $url) {
                $this->line("  - {$url}");
            }
            $this->newLine();
        }

        if (!$dryRun) {
            $this->comment('Note: This command only reports issues. To fix:');
            $this->comment('- Run: php artisan maintenance:fix-duplicate-slugs (for duplicate product slugs)');
            $this->comment('- Manually review and update duplicate titles in the database');
        }

        return count($issues) > 0 ? 1 : 0;
    }
}
