<?php

namespace App\Console\Commands\Site;

use App\Services\SiteGeneratorService;
use Illuminate\Console\Command;

class GenerateProductBlogTemplatesForSite extends Command
{
    protected $signature = 'site:generate-product-blog-templates
                            {--niche= : Override site niche (uses site_niche setting by default)}';

    protected $description = 'Generate product blog templates for existing sites (migration helper)';

    protected SiteGeneratorService $generator;

    public function __construct(SiteGeneratorService $generator)
    {
        parent::__construct();
        $this->generator = $generator;
    }

    public function handle()
    {
        $this->info('Product Blog Template Generator');
        $this->info('================================');
        $this->newLine();

        // Get niche from option or site_niche setting
        $niche = $this->option('niche') ?: \App\Models\Setting::where('key', 'site_niche')->value('value');

        if (!$niche) {
            $this->error('No niche found!');
            $this->warn('Either pass --niche option or ensure site_niche setting exists.');
            return Command::FAILURE;
        }

        $this->info("Niche: {$niche}");
        $this->newLine();

        // Check if templates already exist
        $existingCount = \App\Models\ProductBlogTemplate::where('niche', $niche)->count();

        if ($existingCount > 0) {
            $this->warn("Found {$existingCount} existing product blog templates for this niche.");
            $this->newLine();

            if (!$this->confirm('Do you want to REPLACE them with new templates?', false)) {
                $this->info('Aborted. No changes made.');
                return Command::SUCCESS;
            }

            $this->newLine();
        }

        // Generate templates via OpenAI
        $this->info('Generating 20 product blog templates via OpenAI...');
        $this->info('(This may take 60-90 seconds)');
        $this->newLine();

        try {
            $count = $this->generator->generateProductBlogTemplates($niche);

            if ($count > 0) {
                $this->newLine();
                $this->info("Success! Generated {$count} product blog templates.");
                $this->newLine();

                $this->line('These templates will be used for:');
                $this->line('  - php artisan app:generate-product-blog {product_id}');
                $this->line('  - php artisan app:generate-popular-product-blogs');
                $this->newLine();

                $this->info('You can now generate product-focused blogs for your products!');

                return Command::SUCCESS;

            } else {
                $this->warn('No templates generated (unexpected).');
                $this->warn('Check logs for details.');
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error('Failed to generate product blog templates:');
            $this->error($e->getMessage());
            $this->newLine();
            $this->warn('This may be due to:');
            $this->warn('  - OpenAI API issues (check your API key and quota)');
            $this->warn('  - Database connection issues');
            $this->warn('  - Invalid niche format');
            return Command::FAILURE;
        }
    }
}
