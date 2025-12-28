<?php

namespace App\Console\Commands\Forge;

use App\Services\ForgeService;
use Illuminate\Console\Command;

class InstallSSL extends Command
{
    protected $signature = 'forge:install-ssl {domain : The domain to install SSL for}';
    protected $description = 'Install SSL certificate for a domain via Forge API';

    public function handle(ForgeService $forgeService): int
    {
        $domain = $this->argument('domain');

        $this->info("Installing SSL certificate for: {$domain}");
        $this->newLine();

        try {
            // Get site ID from domain
            $siteId = $forgeService->getSiteIdByDomain($domain);

            if (!$siteId) {
                $this->error("Site not found for domain: {$domain}");
                return Command::FAILURE;
            }

            $this->info("Found site ID: {$siteId}");
            $this->newLine();

            // Install SSL
            $this->info("Requesting SSL certificate from Let's Encrypt...");
            $result = $forgeService->installSSLPublic($siteId, $domain);

            if ($result) {
                $this->newLine();
                $this->info("✓ SSL certificate successfully installed!");
                $this->info("Visit: https://{$domain}");
                return Command::SUCCESS;
            } else {
                $this->newLine();
                $this->error("✗ SSL certificate installation failed.");
                $this->warn("You can install SSL manually via Forge UI.");
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
