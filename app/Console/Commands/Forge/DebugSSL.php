<?php

namespace App\Console\Commands\Forge;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DebugSSL extends Command
{
    protected $signature = 'forge:debug-ssl {domain : The domain to debug}';
    protected $description = 'Debug SSL certificate status for a domain';

    public function handle(): int
    {
        $domain = $this->argument('domain');
        $apiToken = config('forge.api_token');
        $serverId = config('forge.server_id');
        $baseUrl = 'https://forge.laravel.com/api';

        $this->info("Debugging SSL for: {$domain}");
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

        $this->info("Site ID: {$siteId}");
        $this->newLine();

        // Get all certificates
        $certResponse = Http::withToken($apiToken)
            ->get("{$baseUrl}/v1/servers/{$serverId}/sites/{$siteId}/certificates");

        if ($certResponse->failed()) {
            $this->error("Failed to get certificates");
            return Command::FAILURE;
        }

        $certificates = $certResponse->json('certificates', []);

        if (empty($certificates)) {
            $this->warn("No certificates found for this site");
            return Command::SUCCESS;
        }

        $this->info("Found " . count($certificates) . " certificate(s):");
        $this->newLine();

        foreach ($certificates as $cert) {
            $this->line("Certificate ID: " . ($cert['id'] ?? 'unknown'));
            $this->line("  Domain: " . ($cert['domain'] ?? 'unknown'));
            $this->line("  Type: " . ($cert['type'] ?? 'unknown'));
            $this->line("  Status: " . ($cert['status'] ?? 'unknown'));
            $this->line("  Request Status: " . ($cert['request_status'] ?? 'unknown'));
            $this->line("  Active: " . (($cert['active'] ?? false) ? 'Yes' : 'No'));
            $this->line("  Existing: " . (($cert['existing'] ?? false) ? 'Yes' : 'No'));

            if (isset($cert['validation_error'])) {
                $this->error("  Validation Error: " . $cert['validation_error']);
            }

            if (isset($cert['created_at'])) {
                $this->line("  Created: " . date('Y-m-d H:i:s', $cert['created_at']));
            }

            $this->newLine();
        }

        // Check Nginx config
        $nginxResponse = Http::withToken($apiToken)
            ->get("{$baseUrl}/orgs/" . config('forge.organization') . "/servers/{$serverId}/sites/{$siteId}/nginx");

        if ($nginxResponse->successful()) {
            $nginxConfig = $nginxResponse->json('data.attributes.content');

            $this->info("Nginx Configuration Check:");
            if (strpos($nginxConfig, '/.well-known/acme-challenge') !== false) {
                $this->info("  .well-known: CONFIGURED");
            } else {
                $this->error("  .well-known: NOT CONFIGURED");
            }

            if (strpos($nginxConfig, 'listen 443') !== false) {
                $this->info("  SSL Listen: CONFIGURED");
            } else {
                $this->warn("  SSL Listen: NOT CONFIGURED (normal before SSL install)");
            }
        }

        return Command::SUCCESS;
    }
}
