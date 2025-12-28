<?php

namespace App\Console\Commands\Forge;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ResetNginx extends Command
{
    protected $signature = 'forge:reset-nginx {domain : The domain to reset Nginx config for}';
    protected $description = 'Reset Nginx configuration to Forge default for a domain';

    public function handle(): int
    {
        $domain = $this->argument('domain');
        $apiToken = config('forge.api_token');
        $organization = config('forge.organization');
        $serverId = config('forge.server_id');
        $baseUrl = 'https://forge.laravel.com/api';

        $this->info("Resetting Nginx config for: {$domain}");
        $this->newLine();

        // Get site ID
        $response = Http::withToken($apiToken)
            ->get("{$baseUrl}/v1/servers/{$serverId}/sites");

        if ($response->failed()) {
            $this->error("Failed to get sites");
            return Command::FAILURE;
        }

        $sites = $response->json('sites', []);
        $siteId = null;

        foreach ($sites as $site) {
            if ($site['name'] === $domain) {
                $siteId = $site['id'];
                break;
            }
        }

        if (!$siteId) {
            $this->error("Site not found: {$domain}");
            return Command::FAILURE;
        }

        $this->info("Found site ID: {$siteId}");
        $this->newLine();

        // Reset Nginx config to default
        $this->info("Resetting Nginx configuration to Forge default...");

        $resetResponse = Http::withToken($apiToken)
            ->post("{$baseUrl}/orgs/{$organization}/servers/{$serverId}/sites/{$siteId}/nginx");

        if ($resetResponse->failed()) {
            $this->error("Failed to reset Nginx config: " . $resetResponse->body());
            return Command::FAILURE;
        }

        $this->newLine();
        $this->info("Nginx configuration successfully reset to default!");
        $this->info("You can now request SSL via Forge UI or API");

        return Command::SUCCESS;
    }
}
