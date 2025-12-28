<?php

namespace App\Console\Commands\Site;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SetupAfterDeployment extends Command
{
    protected $signature = 'site:setup-after-deployment
                            {--niche= : Site niche}
                            {--domain= : Site domain}
                            {--primary-color= : Primary color hex}
                            {--bol-site-id= : Bol.com affiliate site ID}
                            {--bol-category-id= : Bol.com category ID}
                            {--force : Force overwrite existing content}
                            {--fetch-products : Fetch products from Bol.com}';

    protected $description = 'Run after Forge deployment to generate all content and fetch products';

    public function handle()
    {
        $this->info('========================================');
        $this->info('  POST-DEPLOYMENT SETUP');
        $this->info('========================================');
        $this->newLine();

        // Get inputs from command options (already set in .env by Forge)
        $niche = $this->option('niche') ?: env('SITE_NICHE', 'producten');
        $domain = $this->option('domain') ?: env('APP_URL', 'example.nl');
        $domain = str_replace(['http://', 'https://'], '', $domain);
        $primaryColor = $this->option('primary-color') ?: env('PRIMARY_COLOR', '#7c3aed');
        $bolSiteId = $this->option('bol-site-id') ?: env('BOL_SITE_ID');
        $bolCategoryId = $this->option('bol-category-id') ?: env('BOL_CATEGORY_ID');

        $this->info("Niche: {$niche}");
        $this->info("Domain: {$domain}");
        $this->newLine();

        // Run site:generate WITHOUT --deploy-to-forge flag
        $this->info('Step 1/2: Generating all content...');
        $this->newLine();

        $params = [
            '--niche' => $niche,
            '--domain' => $domain,
            '--primary-color' => $primaryColor,
        ];

        if ($bolSiteId) {
            $params['--bol-site-id'] = $bolSiteId;
        }

        if ($bolCategoryId) {
            $params['--bol-category-id'] = $bolCategoryId;
        }

        if ($this->option('force')) {
            $params['--force'] = true;
        }

        // Always use --no-interaction for automated deployments
        $params['--no-interaction'] = true;

        // Skip seed blogs but KEEP blog templates (they're essential for content generation)
        $params['--skip-seed-blogs'] = true;

        $this->info('Generating content (settings, content blocks, team, favicon, blog templates)...');
        $this->info('This may take 2-3 minutes due to OpenAI API calls...');
        Artisan::call('site:generate', $params, $this->output);

        // Note: Information pages are already generated in site:generate (step 11/14)
        // No need to generate them again here

        // Step 2/2: Trigger product fetching in truly background manner
        if ($bolCategoryId && $this->option('fetch-products')) {
            $this->newLine();
            $this->info('Step 2/2: Starting product fetching in background...');

            // Use process forking that works reliably on Laravel Forge
            // Chain commands: fetch products THEN update popularity scores
            $command = sprintf(
                'cd %s && php artisan app:fetch-bol-category-products %s && php artisan bol:update-popularity-scores %s > storage/logs/product-fetch.log 2>&1 &',
                base_path(),
                escapeshellarg($bolCategoryId),
                escapeshellarg($bolCategoryId)
            );

            shell_exec($command);
            $this->info('→ Product fetching started (check storage/logs/product-fetch.log)');
            $this->info('→ Popularity scores will be updated after products are fetched');
        }

        $this->newLine();
        $this->info('========================================');
        $this->info('  SETUP COMPLETE!');
        $this->info('========================================');
        $this->newLine();
        $this->info("Site is live at: https://{$domain}");
        $this->info("Content: Settings, content blocks, team, favicon, blog templates, information pages");
        if ($bolCategoryId && $this->option('fetch-products')) {
            $this->info("Products: Fetching in background (~10-15 min)");
        }
        $this->newLine();

        return Command::SUCCESS;
    }
}
