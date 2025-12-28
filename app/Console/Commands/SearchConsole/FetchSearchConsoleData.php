<?php

namespace App\Console\Commands\SearchConsole;

use Illuminate\Console\Command;
use Google\Client;
use Google\Service\Webmasters;
use Google\Service\Webmasters\SearchAnalyticsQueryRequest;
use App\Models\SearchConsoleData;
use Carbon\Carbon;
use Illuminate\Support\Str;

class FetchSearchConsoleData extends Command
{
    protected $signature = 'gsc:fetch {--days=7 : Aantal dagen terug om data op te halen} {--url= : Specifieke site URL (override)}';
    
    protected $description = 'Haal GSC data op voor huidige affiliate site en sla op in database voor AI content generatie';

    public function handle()
    {
        $days = (int) $this->option('days');
        $overrideUrl = $this->option('url');

        // Bepaal site URL - gebruik override of haal uit config/env
        $siteUrl = $overrideUrl ?: $this->getCurrentSiteUrl();
        
        if (!$siteUrl) {
            $this->error('❌ Geen site URL gevonden. Configureer GSC_SITE_URL in .env of gebruik --url parameter');
            return 1;
        }

        $this->info("🔍 GSC data ophalen voor: {$siteUrl}");
        $this->info("📅 Periode: {$days} dagen terug");

        // Controleer Google Service Account configuratie
        $serviceAccountPath = storage_path('app/google/service-account.json');
        if (!file_exists($serviceAccountPath)) {
            $this->error("❌ Service account file niet gevonden: {$serviceAccountPath}");
            $this->line("   Plaats je Google Service Account JSON in storage/app/google/service-account.json");
            $this->line("   Deze service account moet toegang hebben tot GSC property: {$siteUrl}");
            return 1;
        }

        try {
            $client = new Client();
            $client->setAuthConfig($serviceAccountPath);
            $client->addScope(Webmasters::WEBMASTERS_READONLY);
            $service = new Webmasters($client);

            $totalSynced = 0;
            $totalErrors = 0;

            // Haal data op voor elke dag
            for ($i = 0; $i < $days; $i++) {
                $date = Carbon::now()->subDays($i)->toDateString();
                $synced = $this->fetchDataForSiteAndDate($service, $siteUrl, $date);

                if ($synced === false) {
                    $totalErrors++;
                } else {
                    $totalSynced += $synced;
                }
            }

            $this->newLine();
            $this->info("✅ GSC sync voltooid!");
            $this->info("📊 Totaal keywords opgehaald: {$totalSynced}");
            
            if ($totalErrors > 0) {
                $this->warn("⚠️  Fouten opgetreden: {$totalErrors} dagen");
            }

            // Toon enkele high-potential keywords
            $this->showTopOpportunities($siteUrl);

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ GSC API fout: " . $e->getMessage());
            return 1;
        }
    }

    private function getCurrentSiteUrl()
    {
        // Voor site-specifieke deployments: probeer auto-detectie
        $siteUrl = env('GSC_SITE_URL'); // Directe override

        if (!$siteUrl) {
            // Primaire fallback: gebruik APP_URL
            $appUrl = env('APP_URL', '');
            if ($appUrl && !str_contains($appUrl, '127.0.0.1') && !str_contains($appUrl, 'localhost')) {
                $siteUrl = rtrim($appUrl, '/') . '/';
            }
        }

        if (!$siteUrl) {
            // Alternatieve fallback: gebruik APP_NAME als domain (crosstrainertest.nl)
            $appName = env('APP_NAME', '');
            if (str_contains($appName, '.')) {
                $siteUrl = 'https://' . rtrim($appName, '/') . '/';
            }
        }

        // Force HTTPS voor GSC (Google Search Console accepteert alleen HTTPS)
        if ($siteUrl && str_starts_with($siteUrl, 'http://')) {
            $siteUrl = str_replace('http://', 'https://', $siteUrl);
        }

        return $siteUrl;
    }

    private function fetchDataForSiteAndDate($service, $siteUrl, $date)
    {
        $request = new SearchAnalyticsQueryRequest([
            'startDate' => $date,
            'endDate' => $date,
            'dimensions' => ['query'], // Focus op keywords, later uitbreiden naar page
            'rowLimit' => 200, // Meer keywords voor content ideas
            'startRow' => 0,
        ]);

        try {
            $response = $service->searchanalytics->query($siteUrl, $request);

            if (empty($response->getRows())) {
                $this->line("   📅 {$date}: Geen data beschikbaar");
                return 0;
            }

            $syncedCount = 0;
            foreach ($response->getRows() as $row) {
                $query = $row->getKeys()[0];
                
                // Skip lege queries
                if (empty($query) || strlen($query) < 2) {
                    continue;
                }

                SearchConsoleData::createFromGscData($siteUrl, $date, $query, $row);
                $syncedCount++;
            }

            $this->line("   📅 {$date}: <info>{$syncedCount}</info> keywords opgeslagen");
            return $syncedCount;

        } catch (\Exception $e) {
            $this->error("   ❌ {$date}: " . $e->getMessage());
            return false;
        }
    }

    private function showTopOpportunities($siteUrl, $limit = 5)
    {
        $opportunities = SearchConsoleData::forSite($siteUrl)
            ->withMinimumImpressions(10)
            ->whereNotNull('query')
            ->whereNotNull('position')
            ->whereNotNull('clicks')
            ->where('position', '>', 10)
            ->where('clicks', '<', 5)
            ->orderByDesc('impressions')
            ->limit($limit)
            ->get();

        if ($opportunities->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->info("🎯 Top content opportunities (hoge impressions, lage clicks):");
        $this->table(
            ['Keyword', 'Impressions', 'Clicks', 'Position', 'CTR%'],
            $opportunities->map(fn($item) => [
                Str::limit($item->query ?? '', 40),
                $item->impressions,
                $item->clicks,
                round((float)$item->position, 1),
                round((float)$item->ctr * 100, 2) . '%',
            ])
        );
    }
}