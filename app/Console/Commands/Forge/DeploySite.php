<?php

namespace App\Console\Commands\Forge;

use App\Services\ForgeService;
use Illuminate\Console\Command;

class DeploySite extends Command
{
    protected $signature = 'forge:deploy-site
                            {domain : The domain for the site}
                            {--niche= : The niche for the site}
                            {--site-name= : The site name (defaults to domain)}
                            {--bol-site-id= : Bol.com site ID}
                            {--bol-category-id= : Bol.com category ID}
                            {--primary-color=#7c3aed : Primary color for the site}';

    protected $description = 'Deploy a complete affiliate site on Forge (site, database, scheduler, content)';

    public function handle(ForgeService $forgeService): int
    {
        $domain = $this->argument('domain');
        $niche = $this->option('niche');
        $siteName = $this->option('site-name') ?? $domain;
        $bolSiteId = $this->option('bol-site-id');
        $bolCategoryId = $this->option('bol-category-id');
        $primaryColor = $this->option('primary-color');

        if (!$niche) {
            $this->error('The --niche option is required');
            return Command::FAILURE;
        }

        $this->info("===========================================");
        $this->info("  FORGE DEPLOYMENT");
        $this->info("===========================================");
        $this->info("Domain: {$domain}");
        $this->info("Niche: {$niche}");
        $this->info("Site Name: {$siteName}");
        if ($bolSiteId) {
            $this->info("Bol Site ID: {$bolSiteId}");
        }
        if ($bolCategoryId) {
            $this->info("Bol Category ID: {$bolCategoryId}");
        }
        $this->info("Primary Color: {$primaryColor}");
        $this->info("===========================================");
        $this->newLine();

        try {
            $result = $forgeService->createSite([
                'domain' => $domain,
                'niche' => $niche,
                'site_name' => $siteName,
                'bol_site_id' => $bolSiteId,
                'bol_category_id' => $bolCategoryId,
                'primary_color' => $primaryColor,
            ]);

            $this->newLine();
            $this->info("===========================================");
            $this->info("  DEPLOYMENT COMPLETE!");
            $this->info("===========================================");
            $this->info("Site ID: {$result['site_id']}");
            $this->info("Domain: {$result['domain']}");
            $this->info("Database: {$result['database']['name']}");
            $this->info("SSL: Install manually via Forge UI");
            $this->info("Content: " . ($result['content_generated'] ? 'Generated' : 'Failed'));
            $this->info("===========================================");
            $this->newLine();
            $this->warn("Remember to install SSL certificate manually via Forge UI!");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Deployment failed: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
