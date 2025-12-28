<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * ForgeService
 *
 * Service for interacting with Laravel Forge API to automatically
 * deploy new affiliate sites.
 *
 * Based on official Forge API documentation:
 * https://forge.laravel.com/docs/api-reference
 */
class ForgeService
{
    protected string $apiToken;
    protected string $organization;
    protected string $serverId;
    protected string $baseUrl = 'https://forge.laravel.com/api';

    public function __construct()
    {
        $this->apiToken = config('forge.api_token');
        $this->organization = config('forge.organization');
        $this->serverId = config('forge.server_id');

        if (!$this->apiToken || !$this->serverId || !$this->organization) {
            throw new \Exception('Forge API token, organization, and server ID are required. Check your .env file.');
        }
    }

    /**
     * Get site ID by domain name
     */
    public function getSiteIdByDomain(string $domain): ?string
    {
        // Use v1 endpoint as per Forge API docs
        $response = Http::withToken($this->apiToken)
            ->get("{$this->baseUrl}/v1/servers/{$this->serverId}/sites");

        if ($response->failed()) {
            return null;
        }

        $data = $response->json();
        $sites = $data['sites'] ?? [];

        foreach ($sites as $site) {
            if ($site['name'] === $domain) {
                return (string) $site['id'];
            }
        }

        return null;
    }

    /**
     * Install SSL certificate (public method for command usage)
     */
    public function installSSLPublic(string $siteId, string $domain): bool
    {
        return $this->installSSL($siteId, $domain);
    }

    /**
     * Create a complete site on Forge with database, SSL, and deployment
     */
    public function createSite(array $siteConfig): array
    {
        $domain = $siteConfig['domain'];
        $niche = $siteConfig['niche'];
        $bolSiteId = $siteConfig['bol_site_id'] ?? null;
        $bolCategoryId = $siteConfig['bol_category_id'] ?? null;
        $primaryColor = $siteConfig['primary_color'] ?? '#7c3aed';
        $siteName = $siteConfig['site_name'] ?? $domain;

        \Log::info("Starting Forge deployment for {$domain}");

        // Step 1: Create site with Git repository
        \Log::info("Step 1: Creating site on Forge with Git repository");
        $site = $this->createForgedSite($domain);
        $siteId = $site['id'];
        \Log::info("Site created with ID: {$siteId}");

        // Step 2: Create database (Forge creates credentials automatically)
        \Log::info("Step 3: Creating database");
        $database = $this->createDatabase($domain);

        // Step 4: Update environment variables
        \Log::info("Step 4: Updating environment variables");
        $this->updateEnvironmentVariables($siteId, [
            'domain' => $domain,
            'site_name' => $siteName,
            'database' => $database,
            'bol_site_id' => $bolSiteId,
            'bol_category_id' => $bolCategoryId,
            'primary_color' => $primaryColor,
            'niche' => $niche,
        ]);

        // Step 5: Update deployment script
        \Log::info("Step 4: Updating deployment script");
        $this->updateDeploymentScript($siteId);

        // Step 6: Create Laravel scheduler (cron job)
        \Log::info("Step 5: Creating Laravel scheduler");
        $this->createScheduler($domain);

        // Step 7: Deploy site
        \Log::info("Step 6: Deploying site");
        $this->deploySite($siteId);

        // Step 7a: Update environment variables AGAIN after deployment
        // (deployment copies .env.example which overwrites our settings)
        \Log::info("Step 7: Re-updating environment variables after deployment");
        $this->updateEnvironmentVariables($siteId, [
            'domain' => $domain,
            'site_name' => $siteName,
            'database' => $database,
            'bol_site_id' => $bolSiteId,
            'bol_category_id' => $bolCategoryId,
            'primary_color' => $primaryColor,
            'niche' => $niche,
        ]);

        // Step 7b: Clear config cache so new env vars are loaded
        \Log::info("Step 7b: Clearing config cache to load new environment variables");
        $this->executeSiteCommand($siteId, "cd /home/forge/{$domain} && php artisan config:clear");

        // Step 8: Reset Nginx to default (to ensure .well-known works for SSL)
        \Log::info("Step 8: Resetting Nginx to default configuration");
        $this->resetNginxConfig($siteId);

        // Step 9: Skip SSL installation - do manually via Forge UI
        \Log::info("Step 9: Skipping SSL installation (install manually via Forge UI)");

        // Step 10: Run migrations explicitly (deployment script only runs on git pull, not on new sites)
        \Log::info("Step 10: Running migrations");
        $this->executeSiteCommand($siteId, "cd /home/forge/{$domain} && php artisan migrate --force");
        \Log::info("Migrations completed successfully");

        \Log::info("Step 11: Generating all site content (settings, content blocks, blogs, etc.)");
        $generateCommand = "cd /home/forge/{$domain} && php artisan site:setup-after-deployment"
            . " --niche=\"{$niche}\""
            . " --domain=\"{$domain}\""
            . " --primary-color=\"{$primaryColor}\"";

        if ($bolSiteId) {
            $generateCommand .= " --bol-site-id=\"{$bolSiteId}\"";
        }

        if ($bolCategoryId) {
            $generateCommand .= " --bol-category-id=\"{$bolCategoryId}\" --fetch-products";
            // Product fetching happens in background via SetupAfterDeployment
        }

        // Always use --force and --no-interaction for automated deployments
        $generateCommand .= " --force --no-interaction";
        // SetupAfterDeployment will:
        // 1. Generate content (settings, content blocks, team, favicon, blog templates) - ~2-3 min
        // 2. Trigger background: information pages + product fetching
        // Total SSH command time: ~2-3 minutes (blog templates via OpenAI)

        $contentGenerated = $this->executeSiteCommand($siteId, $generateCommand);

        \Log::info("Forge deployment completed for {$domain}");
        \Log::info("");
        \Log::info("===========================================");
        \Log::info("  DEPLOYMENT COMPLETE!");
        \Log::info("  Site: https://{$domain}");
        \Log::info("  SSL: Install manually via Forge UI");
        \Log::info("  Content: " . ($contentGenerated ? "Generated" : "Failed (run manually)"));
        \Log::info("===========================================");

        return [
            'site_id' => $siteId,
            'domain' => $domain,
            'database' => $database,
            'ssl_installed' => false,
            'content_generated' => $contentGenerated,
        ];
    }

    /**
     * Create a new site on Forge
     */
    protected function createForgedSite(string $domain): array
    {
        $repoUrl = config('forge.git.repository');
        $branch = config('forge.git.branch', 'main');

        // Extract repo name (e.g., "Luuk561/template" from URL)
        $repoName = $this->extractRepoName($repoUrl);

        $response = Http::withToken($this->apiToken)
            ->post("{$this->baseUrl}/orgs/{$this->organization}/servers/{$this->serverId}/sites", [
                'domain_mode' => 'custom',
                'name' => $domain,
                'type' => 'php',
                'php_version' => config('forge.php_version', 'php84'),
                'web_directory' => '/public',
                'www_redirect_type' => 'none',
                'allow_wildcard_subdomains' => false,
                'source_control_provider' => 'github',
                'repository' => $repoName,
                'branch' => $branch,
            ]);

        if ($response->failed()) {
            throw new \Exception("Failed to create site: " . $response->body());
        }

        $data = $response->json();
        \Log::info("Site created successfully", ['response' => $data]);

        $siteId = $data['data']['id'] ?? null;

        // Wait for site to finish creating (status should be 'installed' or not 'creating')
        // This can take a while because Git repository needs to be installed
        $this->waitForSiteCreation($siteId, 180);

        // Forge returns data in JSON:API format: data.id and data.attributes
        return [
            'id' => $siteId,
            'name' => $data['data']['attributes']['name'] ?? null,
            'domain' => $data['data']['attributes']['name'] ?? $domain,
        ];
    }

    /**
     * Wait for site to finish creating
     */
    protected function waitForSiteCreation(string $siteId, int $maxWaitSeconds): void
    {
        $waited = 0;
        $checkInterval = 5;

        \Log::info("Waiting for site and repository installation to complete", ['site_id' => $siteId]);

        while ($waited < $maxWaitSeconds) {
            $response = Http::withToken($this->apiToken)
                ->get("{$this->baseUrl}/orgs/{$this->organization}/sites/{$siteId}");

            if ($response->successful()) {
                $attributes = $response->json('data.attributes');
                $siteStatus = $attributes['status'] ?? 'unknown';
                $repoStatus = $attributes['repository']['status'] ?? 'unknown';

                \Log::info("Site status check", [
                    'site_status' => $siteStatus,
                    'repo_status' => $repoStatus,
                    'waited' => $waited
                ]);

                // Site is ready when repository is installed (repo installation takes longer)
                // OR when site status is not 'creating' anymore
                if ($repoStatus === 'installed' || ($siteStatus !== 'creating' && $siteStatus !== 'unknown')) {
                    \Log::info("Site and repository installation completed");
                    return;
                }
            } else {
                \Log::error("Failed to check site status", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'waited' => $waited
                ]);
            }

            sleep($checkInterval);
            $waited += $checkInterval;
        }

        throw new \Exception("Site creation timed out after {$maxWaitSeconds} seconds");
    }

    /**
     * Install Git repository on the site
     */
    protected function installGitRepository(string $siteId): array
    {
        $repoUrl = config('forge.git.repository');
        $branch = config('forge.git.branch', 'main');

        // Extract repo name (e.g., "Luuk561/template" from URL)
        $repoName = $this->extractRepoName($repoUrl);

        $response = Http::withToken($this->apiToken)
            ->post("{$this->baseUrl}/orgs/{$this->organization}/servers/{$this->serverId}/sites/{$siteId}/source-control", [
                'provider' => 'github',
                'repository' => $repoName,
                'branch' => $branch,
            ]);

        if ($response->failed()) {
            throw new \Exception("Failed to install Git repository: " . $response->body());
        }

        return $response->json();
    }

    /**
     * Create a new database for the site
     * Forge will automatically create secure credentials
     */
    protected function createDatabase(string $domain): array
    {
        // Create database name from domain (e.g., duofryer.nl -> duofryer_nl)
        $databaseName = str_replace(['.', '-'], '_', $domain);

        $response = Http::withToken($this->apiToken)
            ->post("{$this->baseUrl}/orgs/{$this->organization}/servers/{$this->serverId}/database/schemas", [
                'name' => $databaseName,
                // Don't specify user/password - let Forge create them
            ]);

        if ($response->failed()) {
            throw new \Exception("Failed to create database: " . $response->body());
        }

        $databaseData = $response->json('data.attributes');

        \Log::info("Database created", ['database_data' => $databaseData]);

        // Wait a moment for database to be fully created
        sleep(2);

        // Forge creates the database with auto-generated credentials
        // The password should be in the response
        return [
            'name' => $databaseName,
            'user' => $databaseData['username'] ?? 'forge',
            'password' => $databaseData['password'] ?? $this->getForgeUserPassword(),
        ];
    }

    /**
     * Get the database password - retrieve from created database
     */
    protected function getDatabasePassword(string $databaseName): string
    {
        // Try to get database details which should include credentials
        $response = Http::withToken($this->apiToken)
            ->get("{$this->baseUrl}/orgs/{$this->organization}/servers/{$this->serverId}/database/schemas");

        if ($response->successful()) {
            $databases = $response->json('data');

            foreach ($databases as $db) {
                if ($db['attributes']['name'] === $databaseName) {
                    \Log::info("Found database", ['db' => $db['attributes']]);

                    // The password might be in the database attributes or we need to use forge user password
                    return $db['attributes']['password'] ?? $this->getForgeUserPassword();
                }
            }
        }

        \Log::warning("Could not retrieve database password, using forge user password");
        return $this->getForgeUserPassword();
    }

    /**
     * Get the forge user's MySQL password from server
     */
    protected function getForgeUserPassword(): string
    {
        // First try config (user can manually set it)
        $configPassword = config('forge.database_password');
        if ($configPassword) {
            \Log::info("Using database password from config");
            return $configPassword;
        }

        // Try to get from Forge API
        $response = Http::withToken($this->apiToken)
            ->get("{$this->baseUrl}/orgs/{$this->organization}/servers/{$this->serverId}");

        if ($response->successful()) {
            $server = $response->json('data.attributes');
            \Log::info("Server credentials check", [
                'has_db_password' => isset($server['database_password']),
                'has_mysql_password' => isset($server['mysql_password']),
            ]);

            return $server['database_password']
                ?? $server['mysql_password']
                ?? 'FORGE_PASSWORD_NOT_FOUND';
        }

        return 'FORGE_PASSWORD_NOT_FOUND';
    }

    /**
     * Update environment variables for the site
     */
    protected function updateEnvironmentVariables(string $siteId, array $config): void
    {
        // Generate APP_KEY
        $appKey = 'base64:' . base64_encode(random_bytes(32));

        // Build environment file
        $env = $this->buildEnvironmentFile($config, $appKey);

        \Log::info("Updating environment variables", [
            'site_id' => $siteId,
            'env_length' => strlen($env),
            'database_password' => $config['database']['password'] ?? 'not set',
        ]);

        $response = Http::withToken($this->apiToken)
            ->put("{$this->baseUrl}/orgs/{$this->organization}/servers/{$this->serverId}/sites/{$siteId}/environment", [
                'environment' => $env,
            ]);

        if ($response->failed()) {
            \Log::error("Failed to update environment", [
                'status' => $response->status(),
                'body' => $response->body(),
                'env_preview' => substr($env, 0, 500),
            ]);
            throw new \Exception("Failed to update environment variables: " . $response->body());
        }

        \Log::info("Environment variables updated successfully");
    }

    /**
     * Build the .env file content
     */
    protected function buildEnvironmentFile(array $config, string $appKey): string
    {
        $domain = $config['domain'];
        $siteName = $config['site_name'];
        $database = $config['database'];
        $bolSiteId = $config['bol_site_id'] ?? '';
        $bolCategoryId = $config['bol_category_id'] ?? '';

        $sharedCredentials = config('forge.shared_credentials');
        $defaultEnv = config('forge.default_env');

        // Debug: Log shared credentials to verify they're being read
        \Log::info("Building environment file", [
            'has_bol_client_id' => !empty($sharedCredentials['bol_client_id']),
            'has_bol_client_secret' => !empty($sharedCredentials['bol_client_secret']),
            'has_openai_key' => !empty($sharedCredentials['openai_api_key']),
            'bol_site_id' => $bolSiteId,
            'bol_category_id' => $bolCategoryId,
        ]);

        $lines = [
            "APP_NAME=\"{$siteName}\"",
            "APP_ENV={$defaultEnv['APP_ENV']}",
            "APP_KEY={$appKey}",
            "APP_DEBUG={$defaultEnv['APP_DEBUG']}",
            "APP_URL=https://{$domain}",
            "",
            "APP_LOCALE={$defaultEnv['APP_LOCALE']}",
            "APP_FALLBACK_LOCALE={$defaultEnv['APP_FALLBACK_LOCALE']}",
            "APP_FAKER_LOCALE=nl_NL",
            "",
            "APP_MAINTENANCE_DRIVER={$defaultEnv['APP_MAINTENANCE_DRIVER']}",
            "# APP_MAINTENANCE_STORE=database",
            "",
            "PHP_CLI_SERVER_WORKERS=4",
            "",
            "BCRYPT_ROUNDS={$defaultEnv['BCRYPT_ROUNDS']}",
            "",
            "LOG_CHANNEL={$defaultEnv['LOG_CHANNEL']}",
            "LOG_STACK={$defaultEnv['LOG_STACK']}",
            "LOG_DEPRECATIONS_CHANNEL=null",
            "LOG_LEVEL={$defaultEnv['LOG_LEVEL']}",
            "",
            "DB_CONNECTION={$defaultEnv['DB_CONNECTION']}",
            "DB_HOST={$defaultEnv['DB_HOST']}",
            "DB_PORT={$defaultEnv['DB_PORT']}",
            "DB_DATABASE={$database['name']}",
            "DB_USERNAME={$database['user']}",
            "DB_PASSWORD=\"{$database['password']}\"",
            "",
            "SESSION_DRIVER={$defaultEnv['SESSION_DRIVER']}",
            "SESSION_LIFETIME={$defaultEnv['SESSION_LIFETIME']}",
            "SESSION_ENCRYPT=false",
            "SESSION_PATH=/",
            "SESSION_DOMAIN=null",
            "",
            "BROADCAST_CONNECTION={$defaultEnv['BROADCAST_CONNECTION']}",
            "FILESYSTEM_DISK={$defaultEnv['FILESYSTEM_DISK']}",
            "QUEUE_CONNECTION={$defaultEnv['QUEUE_CONNECTION']}",
            "",
            "CACHE_STORE={$defaultEnv['CACHE_STORE']}",
            "# CACHE_PREFIX=",
            "",
            "MEMCACHED_HOST=127.0.0.1",
            "",
            "REDIS_CLIENT=phpredis",
            "REDIS_HOST=127.0.0.1",
            "REDIS_PASSWORD=\"\"",
            "REDIS_PORT=6379",
            "",
            "MAIL_MAILER={$defaultEnv['MAIL_MAILER']}",
            "MAIL_SCHEME=null",
            "MAIL_HOST=127.0.0.1",
            "MAIL_PORT=2525",
            "MAIL_USERNAME=null",
            "MAIL_PASSWORD=null",
            "MAIL_FROM_ADDRESS=\"hello@{$domain}\"",
            "MAIL_FROM_NAME=\"\${APP_NAME}\"",
            "",
            "AWS_ACCESS_KEY_ID=",
            "AWS_SECRET_ACCESS_KEY=",
            "AWS_DEFAULT_REGION=us-east-1",
            "AWS_BUCKET=",
            "AWS_USE_PATH_STYLE_ENDPOINT=false",
            "",
            "VITE_APP_NAME=\"\${APP_NAME}\"",
            "",
            "# Bol.com Affiliate Configuration",
            "BOL_CLIENT_ID=" . ($sharedCredentials['bol_client_id'] ?? ''),
            "BOL_CLIENT_SECRET=" . ($sharedCredentials['bol_client_secret'] ?? ''),
            "BOL_SITE_ID=" . ($bolSiteId ?: ''),
            "BOL_CATEGORY_ID=" . ($bolCategoryId ?: ''),
            "",
            "# OpenAI Configuration (for content generation)",
            "OPENAI_API_KEY=" . ($sharedCredentials['openai_api_key'] ?? ''),
        ];

        return implode("\n", $lines);
    }

    /**
     * Update the deployment script
     */
    protected function updateDeploymentScript(string $siteId): void
    {
        $response = Http::withToken($this->apiToken)
            ->put("{$this->baseUrl}/orgs/{$this->organization}/servers/{$this->serverId}/sites/{$siteId}/deployments/script", [
                'content' => config('forge.deploy_script'),
            ]);

        if ($response->failed()) {
            throw new \Exception("Failed to update deployment script: " . $response->body());
        }

        // Small delay to ensure script is saved
        sleep(1);
    }

    /**
     * Enable quick deploy (auto-deploy on git push)
     */
    protected function enableQuickDeploy(string $siteId): void
    {
        $response = Http::withToken($this->apiToken)
            ->post("{$this->baseUrl}/orgs/{$this->organization}/servers/{$this->serverId}/sites/{$siteId}/deployments/auto-deployments");

        if ($response->failed()) {
            throw new \Exception("Failed to enable quick deploy: " . $response->body());
        }
    }

    /**
     * Obtain Let's Encrypt SSL certificate
     */
    protected function obtainSSLCertificate(string $siteId, string $domain): array
    {
        $response = Http::withToken($this->apiToken)
            ->post("{$this->baseUrl}/orgs/{$this->organization}/servers/{$this->serverId}/sites/{$siteId}/certificates/letsencrypt", [
                'domains' => [$domain, "www.{$domain}"],
            ]);

        if ($response->failed()) {
            throw new \Exception("Failed to obtain SSL certificate: " . $response->body());
        }

        $certificate = $response->json('data.attributes');

        // Wait for certificate to be active (max 120 seconds)
        $this->waitForSSL($siteId, $certificate['id'], 120);

        // Activate certificate
        $this->activateSSLCertificate($siteId, $certificate['id']);

        return $certificate;
    }

    /**
     * Wait for SSL certificate to be active
     */
    protected function waitForSSL(string $siteId, string $certificateId, int $maxWaitSeconds): void
    {
        $waited = 0;
        $checkInterval = 5;

        while ($waited < $maxWaitSeconds) {
            $response = Http::withToken($this->apiToken)
                ->get("{$this->baseUrl}/orgs/{$this->organization}/servers/{$this->serverId}/sites/{$siteId}/certificates/{$certificateId}");

            if ($response->successful()) {
                $status = $response->json('data.attributes.status');
                if ($status === 'installed') {
                    return;
                }
            }

            sleep($checkInterval);
            $waited += $checkInterval;
        }

        throw new \Exception("SSL certificate installation timed out after {$maxWaitSeconds} seconds");
    }

    /**
     * Activate SSL certificate
     */
    protected function activateSSLCertificate(string $siteId, string $certificateId): void
    {
        $response = Http::withToken($this->apiToken)
            ->post("{$this->baseUrl}/orgs/{$this->organization}/servers/{$this->serverId}/sites/{$siteId}/certificates/{$certificateId}/activate");

        if ($response->failed()) {
            throw new \Exception("Failed to activate SSL certificate: " . $response->body());
        }
    }

    /**
     * Create Laravel scheduler (cron job)
     */
    protected function createScheduler(string $domain): void
    {
        $phpVersion = config('forge.php_version', 'php84');

        $payload = [
            'command' => "{$phpVersion} /home/forge/{$domain}/artisan schedule:run",
            'user' => 'forge',
            'frequency' => 'minutely',
            'name' => "Laravel Scheduler - {$domain}",
        ];

        \Log::info("Creating scheduler for {$domain}", ['payload' => $payload]);

        $response = Http::withToken($this->apiToken)
            ->post("{$this->baseUrl}/orgs/{$this->organization}/servers/{$this->serverId}/scheduled-jobs", $payload);

        \Log::info("Scheduler creation response", [
            'status' => $response->status(),
            'body' => $response->body(),
            'successful' => $response->successful(),
        ]);

        // API returns 202 (Accepted) for async operations
        if ($response->status() !== 202 && $response->failed()) {
            throw new \Exception("Failed to create scheduler: " . $response->body());
        }

        // Response structure: { data: { id: "123", type: "scheduledJobs", attributes: {...} } }
        $schedulerId = $response->json('data.id');
        if (!$schedulerId) {
            \Log::error("Scheduler creation returned success but no ID", ['response' => $response->json()]);
            throw new \Exception("Scheduler creation failed silently - no ID returned");
        }

        \Log::info("Scheduler created successfully", ['id' => $schedulerId, 'domain' => $domain]);
    }

    /**
     * Deploy the site
     */
    protected function deploySite(string $siteId): void
    {
        $response = Http::withToken($this->apiToken)
            ->post("{$this->baseUrl}/orgs/{$this->organization}/servers/{$this->serverId}/sites/{$siteId}/deployments");

        if ($response->failed()) {
            throw new \Exception("Failed to deploy site: " . $response->body());
        }

        // Wait for deployment to finish (max 300 seconds = 5 minutes)
        $this->waitForDeployment($siteId, 300);
    }

    /**
     * Wait for deployment to finish
     */
    protected function waitForDeployment(string $siteId, int $maxWaitSeconds): void
    {
        $waited = 0;
        $checkInterval = 5;

        while ($waited < $maxWaitSeconds) {
            $response = Http::withToken($this->apiToken)
                ->get("{$this->baseUrl}/orgs/{$this->organization}/sites/{$siteId}");

            if ($response->successful()) {
                $deploymentStatus = $response->json('data.attributes.deployment_status');
                if ($deploymentStatus === null || $deploymentStatus === 'finished') {
                    return;
                }
            }

            sleep($checkInterval);
            $waited += $checkInterval;
        }

        throw new \Exception("Deployment timed out after {$maxWaitSeconds} seconds");
    }

    /**
     * Reset Nginx configuration to Forge's default
     */
    protected function resetNginxConfig(string $siteId): void
    {
        \Log::info("Resetting Nginx to Forge default configuration");

        $response = Http::withToken($this->apiToken)
            ->post("{$this->baseUrl}/orgs/{$this->organization}/servers/{$this->serverId}/sites/{$siteId}/nginx");

        if ($response->failed()) {
            \Log::error("Failed to reset Nginx config: " . $response->body());
            return;
        }

        \Log::info("Nginx configuration reset to default successfully");

        // Wait for Nginx to reload
        sleep(5);
    }

    /**
     * Create .well-known directory for Let's Encrypt HTTP-01 validation
     */
    protected function createWellKnownDirectory(string $siteId, string $domain): void
    {
        $command = "mkdir -p /home/forge/{$domain}/public/.well-known/acme-challenge && " .
                   "chmod -R 755 /home/forge/{$domain}/public/.well-known";

        $response = Http::withToken($this->apiToken)
            ->post("{$this->baseUrl}/v1/servers/{$this->serverId}/sites/{$siteId}/commands", [
                'command' => $command,
            ]);

        if ($response->failed()) {
            \Log::warning("Failed to create .well-known directory: " . $response->body());
        } else {
            \Log::info(".well-known directory created successfully");
        }
    }

    /**
     * Get domain record ID for a site
     * The domain record ID is needed for SSL certificate requests via the API
     */
    protected function getDomainRecordId(string $siteId, string $domain): ?string
    {
        // Try to get via organization API first
        $response = Http::withToken($this->apiToken)
            ->get("{$this->baseUrl}/orgs/{$this->organization}/sites/{$siteId}");

        if ($response->successful()) {
            $data = $response->json();

            // Check if there's domain data in the response
            // The primary domain is usually just the site name itself
            // For now, we'll use the site ID as domain record ID since Forge might use that
            \Log::info("Site data", ['site_id' => $siteId, 'response_keys' => array_keys($data)]);

            // Try to extract from site name - Forge might embed it
            // For bestedraadlozestofzuiger.nl the domain_record_id was 1079176
            // Let's try calling the certificates endpoint and see what it returns
        }

        // Fallback: The domain record ID might be the same as site ID or derivable from it
        // We'll need to test this
        return $siteId;
    }

    /**
     * Ensure Nginx has .well-known configured for Let's Encrypt
     *
     * DEPRECATED: Do not use this function. It breaks Forge's default config.
     * Forge's default Nginx config already has proper .well-known configuration.
     * Use resetNginxConfig() instead if needed.
     */
    protected function ensureNginxWellKnown(string $siteId, string $domain): void
    {
        \Log::warning("ensureNginxWellKnown called but skipped - use resetNginxConfig instead");
        return;
    }

    /**
     * Install SSL certificate for the site
     * Returns true if successful, false if failed
     */
    protected function installSSL(string $siteId, string $domain): bool
    {
        // Create .well-known directory for Let's Encrypt validation
        \Log::info("Creating .well-known directory for SSL validation");
        $this->createWellKnownDirectory($siteId, $domain);

        // Wait a bit to ensure directory is created
        sleep(3);

        // Note: SSL endpoint uses /v1/servers/ path (not /orgs/)
        $url = "{$this->baseUrl}/v1/servers/{$this->serverId}/sites/{$siteId}/certificates/letsencrypt";

        // Use v1 API payload format (domains array, not domain_record_id)
        $payload = [
            'domains' => [$domain, "www.{$domain}"],
            'verification_method' => 'http-01',
            'key_type' => 'ecdsa',
            'prefer_isrg_x1' => false,
        ];

        \Log::info("SSL Request", [
            'url' => $url,
            'payload' => $payload,
            'site_id' => $siteId,
            'domain' => $domain,
        ]);

        $response = Http::withToken($this->apiToken)
            ->accept('application/json')
            ->asJson()
            ->post($url, $payload);

        \Log::info("SSL Response", [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if ($response->failed()) {
            \Log::warning("Failed to install SSL certificate: " . $response->body());
            return false;
        }

        $certData = $response->json();
        $certificateId = $certData['certificate']['id'] ?? null;

        if (!$certificateId) {
            \Log::error("No certificate ID in response");
            return false;
        }

        \Log::info("SSL certificate installation initiated for {$domain}, waiting for completion...");

        // Wait longer and check certificate status regularly
        $maxWaitTime = 120; // 2 minutes
        $checkInterval = 10; // check every 10 seconds
        $waited = 0;

        while ($waited < $maxWaitTime) {
            sleep($checkInterval);
            $waited += $checkInterval;

            // Check specific certificate status
            $certStatusResponse = Http::withToken($this->apiToken)
                ->get("{$this->baseUrl}/v1/servers/{$this->serverId}/sites/{$siteId}/certificates/{$certificateId}");

            if ($certStatusResponse->successful()) {
                $cert = $certStatusResponse->json('certificate');
                $status = $cert['status'] ?? 'unknown';
                $requestStatus = $cert['request_status'] ?? 'unknown';

                \Log::info("Certificate status check", [
                    'waited' => $waited,
                    'status' => $status,
                    'request_status' => $requestStatus,
                    'active' => $cert['active'] ?? false,
                ]);

                // Check if installation is complete
                if ($status === 'installed' && ($cert['active'] ?? false)) {
                    \Log::info("SSL certificate successfully installed and active");
                    return true;
                }

                // Check if installation failed
                if ($requestStatus === 'failed' || $status === 'failed') {
                    $errorDetails = [
                        'status' => $status,
                        'request_status' => $requestStatus,
                        'validation_error' => $cert['validation_error'] ?? null,
                        'domains' => $cert['domain'] ?? null,
                        'type' => $cert['type'] ?? null,
                        'full_cert_data' => $cert,
                    ];

                    \Log::error("SSL certificate installation failed", $errorDetails);

                    // Also output to console if running from command
                    if (app()->runningInConsole()) {
                        echo "\n\n=== SSL INSTALLATION FAILED ===\n";
                        echo "Status: " . ($status ?? 'unknown') . "\n";
                        echo "Request Status: " . ($requestStatus ?? 'unknown') . "\n";
                        if (isset($cert['validation_error'])) {
                            echo "Validation Error: " . $cert['validation_error'] . "\n";
                        }
                        echo "===============================\n\n";
                    }

                    return false;
                }
            }
        }

        \Log::warning("SSL certificate installation timed out after {$maxWaitTime} seconds");

        // Do one final check
        $certResponse = Http::withToken($this->apiToken)
            ->get("{$this->baseUrl}/v1/servers/{$this->serverId}/sites/{$siteId}/certificates");

        if ($certResponse->successful()) {
            $certs = $certResponse->json('certificates', []);
            foreach ($certs as $cert) {
                if (($cert['active'] ?? false) && $cert['status'] === 'installed') {
                    \Log::info("SSL certificate verified as active on final check");
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Extract repository name from URL
     * e.g., "https://github.com/Luuk561/template" -> "Luuk561/template"
     */
    protected function extractRepoName(string $repoUrl): string
    {
        return preg_replace('#^https?://github\.com/(.+?)(?:\.git)?$#', '$1', $repoUrl);
    }

    /**
     * Execute SSH command on site via Forge API
     */
    public function executeSiteCommand(string $siteId, string $command, int $timeout = 600): bool|string
    {
        \Log::info("Executing command on site {$siteId}: {$command}");

        $response = Http::timeout($timeout)
            ->withToken($this->apiToken)
            ->post("{$this->baseUrl}/v1/servers/{$this->serverId}/sites/{$siteId}/commands", [
                'command' => $command,
            ]);

        if ($response->failed()) {
            \Log::error("Failed to execute command", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        }

        $output = $response->json('output') ?? '';
        \Log::info("Command executed successfully", ['output_length' => strlen($output)]);

        // Return output for migration checks, true for success otherwise
        return $output ?: true;
    }

}
