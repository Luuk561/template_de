<?php

namespace App\Console\Commands\Maintenance;

use Illuminate\Console\Command;

class FixAllSeoIssues extends Command
{
    protected $signature = 'seo:fix-all {--dry-run : Show what would be changed without actually changing it}';
    protected $description = 'Fix all SEO issues: duplicate titles, descriptions, and structured data';

    public function handle()
    {
        $dryRun = $this->option('dry-run') ? '--dry-run' : '';

        $this->info('╔════════════════════════════════════════════════╗');
        $this->info('║   SEO Fix-All Command - Zero Errors in SEMrush ║');
        $this->info('╚════════════════════════════════════════════════╝');
        $this->newLine();

        // 1. Fix Products
        $this->info('1/4 Fixing Product Titles...');
        $this->call('products:fix-duplicate-titles', $dryRun ? ['--dry-run' => true] : []);
        $this->newLine();

        // 2. Fix Reviews
        $this->info('2/4 Fixing Review Titles...');
        $this->call('reviews:fix-duplicates', $dryRun ? ['--dry-run' => true] : []);
        $this->newLine();

        // 3. Fix Blogs
        $this->info('3/4 Fixing Blog Titles...');
        $this->call('blogs:fix-duplicates', $dryRun ? ['--dry-run' => true] : []);
        $this->newLine();

        // 4. Fix Meta Descriptions
        $this->info('4/5 Fixing Duplicate Meta Descriptions...');
        $this->call('seo:fix-duplicate-descriptions', $dryRun ? ['--dry-run' => true] : []);
        $this->newLine();

        // 5. Shorten Long Titles
        $this->info('5/5 Shortening Long Titles...');
        $this->call('seo:shorten-titles', $dryRun ? ['--dry-run' => true] : []);
        $this->newLine();

        // 6. Clear caches
        if (!$dryRun) {
            $this->info('Clearing caches...');
            $this->call('view:clear');
            $this->call('cache:clear');
            $this->call('config:clear');
            $this->newLine();
        }

        $this->info('╔════════════════════════════════════════════════╗');
        $this->info('║              All SEO Fixes Complete!           ║');
        $this->info('╚════════════════════════════════════════════════╝');
        $this->newLine();

        if ($dryRun) {
            $this->warn('This was a DRY RUN. Run without --dry-run to apply changes.');
        } else {
            $this->info('Next steps:');
            $this->line('1. Run a new SEMrush crawl');
            $this->line('2. All errors should be resolved!');
        }

        return 0;
    }
}
