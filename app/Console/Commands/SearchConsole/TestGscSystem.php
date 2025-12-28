<?php

namespace App\Console\Commands\SearchConsole;

use Illuminate\Console\Command;
use App\Models\SearchConsoleData;
use App\Models\BlogPost;
use App\Services\ContentOpportunityService;
use App\Services\InternalLinkingService;
use App\Services\OpenAIService;

class TestGscSystem extends Command
{
    protected $signature = 'gsc:test 
                            {--full : Run volledige systeemtest inclusief AI generatie}
                            {--skip-ai : Skip AI content generatie tests}';

    protected $description = 'Test alle componenten van het GSC-naar-content systeem';

    protected ContentOpportunityService $opportunityService;
    protected InternalLinkingService $linkingService;
    protected OpenAIService $openAIService;

    public function __construct(
        ContentOpportunityService $opportunityService, 
        InternalLinkingService $linkingService,
        OpenAIService $openAIService
    ) {
        parent::__construct();
        $this->opportunityService = $opportunityService;
        $this->linkingService = $linkingService;
        $this->openAIService = $openAIService;
    }

    public function handle()
    {
        $this->info("🧪 GSC Content System Test Suite");
        $this->newLine();

        $fullTest = $this->option('full');
        $skipAI = $this->option('skip-ai');

        $testsPassed = 0;
        $testsTotal = 0;

        // Test 1: Database connectie en GSC data
        [$passed, $total] = $this->testGscDataAvailability();
        $testsPassed += $passed;
        $testsTotal += $total;

        // Test 2: Content Opportunity Service
        [$passed, $total] = $this->testContentOpportunityService();
        $testsPassed += $passed;
        $testsTotal += $total;

        // Test 3: Internal Linking Service
        [$passed, $total] = $this->testInternalLinkingService();
        $testsPassed += $passed;
        $testsTotal += $total;

        // Test 4: AI Content Generation (optioneel)
        if (!$skipAI && ($fullTest || $this->confirm('Test AI content generatie? (kost OpenAI credits)'))) {
            [$passed, $total] = $this->testAIContentGeneration();
            $testsPassed += $passed;
            $testsTotal += $total;
        }

        // Test 5: Complete Pipeline
        [$passed, $total] = $this->testCompletePipeline();
        $testsPassed += $passed;
        $testsTotal += $total;

        // Resultaten
        $this->newLine();
        $this->info("🏁 Test Resultaten:");
        $this->line("✅ Geslaagd: {$testsPassed}/{$testsTotal}");
        
        if ($testsPassed === $testsTotal) {
            $this->info("🎉 Alle tests geslaagd! Het GSC systeem is operationeel.");
        } else {
            $this->error("❌ " . ($testsTotal - $testsPassed) . " test(s) gefaald. Check de output hierboven.");
        }

        return $testsPassed === $testsTotal ? 0 : 1;
    }

    private function testGscDataAvailability(): array
    {
        $this->info("1. 🗃️ Testing GSC Data Availability...");
        
        $passed = 0;
        $total = 3;

        // Check tabel bestaat
        try {
            $count = SearchConsoleData::count();
            $this->line("   ✅ SearchConsoleData tabel bereikbaar ({$count} records)");
            $passed++;
        } catch (\Exception $e) {
            $this->error("   ❌ SearchConsoleData tabel niet bereikbaar: " . $e->getMessage());
        }

        // Check of er recente data is
        $recentData = SearchConsoleData::where('created_at', '>=', now()->subDays(7))->count();
        if ($recentData > 0) {
            $this->line("   ✅ Recente GSC data beschikbaar ({$recentData} records afgelopen week)");
            $passed++;
        } else {
            $this->warn("   ⚠️ Geen recente GSC data (run: php artisan gsc:fetch)");
        }

        // Check site URL detectie
        $currentUrl = $this->getCurrentSiteUrl();
        if ($currentUrl) {
            $this->line("   ✅ Site URL detectie: {$currentUrl}");
            $passed++;
        } else {
            $this->error("   ❌ Kan site URL niet detecteren (check APP_NAME in .env)");
        }

        return [$passed, $total];
    }

    private function testContentOpportunityService(): array
    {
        $this->info("\n2. 🎯 Testing Content Opportunity Service...");
        
        $passed = 0;
        $total = 3;

        $siteUrl = $this->getCurrentSiteUrl() ?: 'https://example.com/';

        try {
            // Test opportunity finding
            $opportunities = $this->opportunityService->findContentOpportunities($siteUrl, 1, 30);
            $this->line("   ✅ Opportunity detection werkt ({$opportunities->count()} gevonden)");
            $passed++;

            if ($opportunities->isNotEmpty()) {
                // Test clustering
                $themes = $this->opportunityService->clusterKeywordsIntoThemes($opportunities, 3);
                $this->line("   ✅ Keyword clustering werkt ({$themes->count()} thema's)");
                $passed++;

                // Test content prompts
                $prompts = $this->opportunityService->generateContentPrompts($themes);
                $this->line("   ✅ Content prompt generatie werkt ({$prompts->count()} prompts)");
                $passed++;
            } else {
                $this->warn("   ⚠️ Geen opportunities gevonden voor clustering test");
                $this->warn("   ⚠️ Geen opportunities gevonden voor prompt test");
            }

        } catch (\Exception $e) {
            $this->error("   ❌ ContentOpportunityService error: " . $e->getMessage());
        }

        return [$passed, $total];
    }

    private function testInternalLinkingService(): array
    {
        $this->info("\n3. 🔗 Testing Internal Linking Service...");
        
        $passed = 0;
        $total = 2;

        try {
            $niche = getSetting('site_niche', 'test');
            
            // Test link finding
            $links = $this->linkingService->findRelevantLinks('test keyword', $niche);
            if (count($links) > 0) {
                $this->line("   ✅ Link detection werkt (" . count($links) . " links gevonden)");
                $passed++;
            } else {
                $this->warn("   ⚠️ Geen links gevonden (mogelijk geen products/blogs in database)");
            }

            // Test context generation
            $context = $this->linkingService->createLinkContext('test keyword', $niche);
            if (!empty($context)) {
                $this->line("   ✅ Link context generatie werkt");
                $passed++;
            } else {
                $this->error("   ❌ Link context generatie gefaald");
            }

        } catch (\Exception $e) {
            $this->error("   ❌ InternalLinkingService error: " . $e->getMessage());
        }

        return [$passed, $total];
    }

    private function testAIContentGeneration(): array
    {
        $this->info("\n4. 🤖 Testing AI Content Generation...");
        
        $passed = 0;
        $total = 2;

        try {
            $niche = getSetting('site_niche', 'test');
            
            // Test E-E-A-T optimized generation
            $testTheme = [
                'primary_keyword' => 'test product',
                'related_keywords' => ['test', 'product', 'review'],
                'content_type' => 'informational',
                'suggested_angle' => 'Informatieve gids',
            ];

            $content = $this->openAIService->generateGscOpportunityBlog(
                $testTheme,
                $niche,
                'Test internal link context'
            );

            $jsonContent = json_decode($content, true);
            if ($jsonContent && isset($jsonContent['title'])) {
                $this->line("   ✅ E-E-A-T optimized AI generation werkt");
                $this->line("   📝 Test titel: " . $jsonContent['title']);
                $passed++;
            } else {
                $this->error("   ❌ AI content generation geeft invalid JSON");
            }

            // Test word count
            if ($jsonContent) {
                $wordCount = str_word_count(strip_tags(json_encode($jsonContent['sections'] ?? [])));
                if ($wordCount >= 200) {
                    $this->line("   ✅ Content length check passed ({$wordCount} woorden)");
                    $passed++;
                } else {
                    $this->warn("   ⚠️ Content te kort ({$wordCount} woorden)");
                }
            }

        } catch (\Exception $e) {
            $this->error("   ❌ AI Content Generation error: " . $e->getMessage());
        }

        return [$passed, $total];
    }

    private function testCompletePipeline(): array
    {
        $this->info("\n5. ⚙️ Testing Complete Pipeline Components...");
        
        $passed = 0;
        $total = 2;

        // Test command availability
        try {
            $output = shell_exec('php artisan list | grep gsc:');
            if (strpos($output, 'gsc:content-pipeline') !== false) {
                $this->line("   ✅ Pipeline commands beschikbaar");
                $passed++;
            } else {
                $this->error("   ❌ Pipeline commands niet gevonden");
            }
        } catch (\Exception $e) {
            $this->error("   ❌ Command check error: " . $e->getMessage());
        }

        // Test service account configuratie
        $serviceAccountPath = storage_path('app/google/service-account.json');
        if (file_exists($serviceAccountPath)) {
            $this->line("   ✅ Google Service Account configuratie gevonden");
            $passed++;
        } else {
            $this->warn("   ⚠️ Service account niet geconfigureerd ({$serviceAccountPath})");
        }

        return [$passed, $total];
    }

    private function getCurrentSiteUrl(): ?string
    {
        $siteUrl = env('GSC_SITE_URL');
        
        if (!$siteUrl) {
            // Primaire fallback: gebruik APP_URL
            $appUrl = env('APP_URL', '');
            if ($appUrl && !str_contains($appUrl, '127.0.0.1') && !str_contains($appUrl, 'localhost')) {
                $siteUrl = rtrim($appUrl, '/') . '/';
            }
        }
        
        if (!$siteUrl) {
            $appName = env('APP_NAME', '');
            if (str_contains($appName, '.')) {
                $siteUrl = 'https://' . rtrim($appName, '/') . '/';
            }
        }

        return $siteUrl;
    }
}