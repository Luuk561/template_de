<?php

namespace App\Console\Commands\SearchConsole;

use Illuminate\Console\Command;

class GscContentPipeline extends Command
{
    protected $signature = 'gsc:content-pipeline 
                            {--days=7 : Dagen GSC data om op te halen}
                            {--content-limit=3 : Aantal content opportunities om te genereren}
                            {--min-impressions=50 : Minimum impressions voor opportunity}
                            {--site-url= : Site URL override}
                            {--force : Force regenereren ook als content al bestaat}
                            {--dry-run : Alleen analyseren, geen content genereren}';

    protected $description = 'Complete GSC-naar-content pipeline: haal data op en genereer automatisch natuurlijke content';

    public function handle()
    {
        $days = $this->option('days');
        $contentLimit = $this->option('content-limit');
        $minImpressions = $this->option('min-impressions');
        $siteUrl = $this->option('site-url');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        $this->info("🚀 GSC Content Pipeline starten...");
        $this->newLine();

        // Stap 1: Haal GSC data op
        $this->info("📡 Stap 1: GSC data ophalen...");
        $gscOptions = ['--days' => $days];
        if ($siteUrl) {
            $gscOptions['--url'] = $siteUrl;
        }

        $gscResult = $this->call('gsc:fetch', $gscOptions);
        
        if ($gscResult !== 0) {
            $this->error("❌ GSC data ophalen gefaald - pipeline gestopt");
            return 1;
        }

        $this->newLine();

        // Stap 2: Genereer content opportunities met intelligente fallback strategie
        $this->info("🤖 Stap 2: Content opportunities analyseren en genereren...");

        // Probeer eerst met hoge kwaliteit thresholds
        $contentGenerated = $this->tryGenerateContentWithFallback($days, $contentLimit, $minImpressions, $siteUrl, $dryRun, $force);

        $this->newLine();

        if ($contentGenerated) {
            $this->info("🎉 GSC Content Pipeline succesvol voltooid!");

            if (!$dryRun) {
                $this->newLine();
                $this->info("💡 Volgende stappen:");
                $this->line("• Controleer gegenereerde content");
                $this->line("• Plan social media promotie");
                $this->line("• Monitor GSC performance over 1-2 weken");
                $this->line("• Run pipeline opnieuw voor nieuwe opportunities");
            }
        } else {
            $this->warn("⚠️ Geen GSC content gegenereerd - fallback naar algemene blog...");

            if (!$dryRun) {
                $this->newLine();
                $this->info("📝 Genereren algemene blog als fallback...");
                $fallbackResult = $this->call('app:generate-blog');

                if ($fallbackResult === 0) {
                    $this->info("✅ Fallback blog succesvol gegenereerd");
                } else {
                    $this->error("❌ Ook fallback blog gefaald");
                    return 1;
                }
            }
        }

        return 0;
    }

    /**
     * Probeer content te genereren met fallback strategie
     * Start met hoge kwaliteit thresholds, verlaag stapsgewijs als er niets gevonden wordt
     */
    private function tryGenerateContentWithFallback($days, $contentLimit, $minImpressions, $siteUrl, $dryRun, $force): bool
    {
        // Strategie: probeer verschillende thresholds in volgorde van kwaliteit
        $strategies = [
            ['impressions' => max($minImpressions, 10), 'days' => $days, 'label' => 'hoge kwaliteit'],
            ['impressions' => max(5, floor($minImpressions / 2)), 'days' => $days, 'label' => 'medium kwaliteit'],
            ['impressions' => 1, 'days' => min($days * 2, 60), 'label' => 'lage drempel, meer data'],
        ];

        foreach ($strategies as $index => $strategy) {
            $this->line("🔍 Poging " . ($index + 1) . ": {$strategy['label']} (min {$strategy['impressions']} impressions, {$strategy['days']} dagen)");

            $contentOptions = [
                '--limit' => $contentLimit,
                '--days' => $strategy['days'],
                '--min-impressions' => $strategy['impressions'],
            ];

            if ($siteUrl) {
                $contentOptions['--site-url'] = $siteUrl;
            }

            if ($dryRun) {
                $contentOptions['--dry-run'] = true;
            }

            if ($force) {
                $contentOptions['--force'] = true;
            }

            $contentResult = $this->call('gsc:generate-content', $contentOptions);

            // Als content succesvol is gegenereerd (exit code 0 en geen waarschuwing over geen opportunities)
            if ($contentResult === 0) {
                // Check of er daadwerkelijk content is gegenereerd
                $recentGscContent = \App\Models\BlogPost::where('type', 'gsc_opportunity')
                    ->where('created_at', '>=', now()->subMinutes(5))
                    ->count();

                if ($recentGscContent > 0) {
                    $this->info("   ✅ Content gevonden met strategie: {$strategy['label']}");
                    return true;
                }
            }

            if ($index < count($strategies) - 1) {
                $this->line("   ⚠️ Geen content gevonden, probeer volgende strategie...");
                $this->newLine();
            }
        }

        // Geen content gegenereerd met alle strategieën
        $this->warn("   ❌ Geen GSC opportunities gevonden met alle strategieën");
        return false;
    }
}