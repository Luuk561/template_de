<?php

namespace App\Console\Commands\SearchConsole;

use Illuminate\Console\Command;
use App\Services\ContentOpportunityService;
use App\Services\OpenAIService;
use App\Services\InternalLinkingService;
use App\Models\BlogPost;
use App\Models\SearchConsoleData;
use Illuminate\Support\Str;

class GenerateGscContentOpportunities extends Command
{
    protected $signature = 'gsc:generate-content 
                            {--limit=5 : Aantal content opportunities om te genereren}
                            {--min-impressions=50 : Minimum impressions voor opportunity}
                            {--days=30 : Aantal dagen GSC data om te analyseren}
                            {--site-url= : Site URL override}
                            {--dry-run : Toon alleen opportunities zonder content te genereren}
                            {--force : Force regenereren ook als content al bestaat}';

    protected $description = 'Analyseer GSC data en genereer automatisch natuurlijke content voor high-potential keywords';

    protected ContentOpportunityService $opportunityService;
    protected OpenAIService $openAIService;
    protected InternalLinkingService $linkingService;

    public function __construct(ContentOpportunityService $opportunityService, OpenAIService $openAIService, InternalLinkingService $linkingService)
    {
        parent::__construct();
        $this->opportunityService = $opportunityService;
        $this->openAIService = $openAIService;
        $this->linkingService = $linkingService;
    }

    public function handle()
    {
        $limit = (int) $this->option('limit');
        $minImpressions = (int) $this->option('min-impressions');
        $days = (int) $this->option('days');
        $siteUrl = $this->option('site-url') ?: $this->getCurrentSiteUrl();
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        if (!$siteUrl) {
            $this->error('❌ Geen site URL gevonden. Configureer GSC_SITE_URL in .env of gebruik --site-url');
            return 1;
        }

        $this->info("🔍 Analyseren GSC data voor content opportunities...");
        $this->info("📊 Site: {$siteUrl}");
        $this->info("📅 Periode: {$days} dagen, min {$minImpressions} impressions");

        // Stap 1: Vind content opportunities
        $opportunities = $this->opportunityService->findContentOpportunities(
            $siteUrl, 
            $minImpressions, 
            $days
        );

        if ($opportunities->isEmpty()) {
            $this->warn('⚠️ Geen content opportunities gevonden met huidige criteria');
            $this->line('💡 Probeer lagere --min-impressions of meer --days');
            return 0;
        }

        $this->info("✅ {$opportunities->count()} content opportunities gevonden");

        // Stap 2: Cluster keywords in thema's
        $themes = $this->opportunityService->clusterKeywordsIntoThemes($opportunities, $limit);

        $this->newLine();
        $this->info("🎯 Top content thema's:");
        $this->table(
            ['Thema', 'Primary Keyword', 'Impressions', 'Score', 'Type'],
            $themes->take($limit)->map(fn($theme) => [
                Str::limit($theme['theme_name'], 25),
                Str::limit($theme['primary_keyword'], 30),
                number_format($theme['total_impressions']),
                round($theme['avg_opportunity_score'], 1),
                $theme['content_type'],
            ])
        );

        if ($dryRun) {
            $this->newLine();
            $this->info("🔍 Dry run mode - geen content gegenereerd");
            $this->showDetailedOpportunities($themes->take($limit));
            return 0;
        }

        // Stap 3: Genereer content prompts
        $contentPrompts = $this->opportunityService->generateContentPrompts($themes->take($limit));

        $this->newLine();
        $this->info("🤖 Content genereren...");

        $generated = 0;
        $failed = 0;
        $totalPrompts = count($contentPrompts);

        foreach ($contentPrompts as $index => $promptData) {
            try {
                $this->line("📝 [{" . ($index + 1) . "}/{$totalPrompts}] Genereren: {$promptData['blog_title']}");

                $success = $this->generateBlogFromPrompt($promptData);

                if ($success) {
                    $generated++;
                    $this->info("   ✅ Succesvol gegenereerd");
                } else {
                    $failed++;
                    $this->error("   ❌ Generatie gefaald");
                }

                // Multi-site rate limiting: 3-5 seconden tussen GSC content generatie
                if ($index < $totalPrompts - 1) { // Skip sleep op laatste item
                    $delay = rand(3, 5);
                    $this->line("   ⏳ Wachten {$delay}s (server-vriendelijk)...");
                    sleep($delay);
                }

                // Memory cleanup
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }

            } catch (\Exception $e) {
                $failed++;
                $this->error("   ❌ Fout: " . $e->getMessage());
                \Log::error('GSC content generation failed', [
                    'prompt_data' => $promptData,
                    'error' => $e->getMessage()
                ]);

                // Extra delay bij fouten
                sleep(5);
            }
        }

        $this->newLine();
        $this->info("🎉 Content generatie voltooid!");
        $this->info("✅ Gegenereerd: {$generated} blogs");
        if ($failed > 0) {
            $this->warn("❌ Gefaald: {$failed} blogs");
        }

        // Toon suggesties voor vervolgstappen
        $this->newLine();
        $this->info("💡 Vervolgstappen:");
        $this->line("• Controleer gegenereerde content in /admin of database");
        $this->line("• Run GSC sync na 1-2 weken om impact te meten");
        $this->line("• Gebruik: php artisan gsc:fetch --days=7");

        return 0;
    }

    private function getCurrentSiteUrl(): ?string
    {
        // Auto-detecteer huidige site URL voor deze deployment
        $siteUrl = env('GSC_SITE_URL'); // Override mogelijk

        if (!$siteUrl) {
            // Primaire fallback: gebruik APP_URL
            $appUrl = env('APP_URL', '');
            if ($appUrl && !str_contains($appUrl, '127.0.0.1') && !str_contains($appUrl, 'localhost')) {
                $siteUrl = rtrim($appUrl, '/') . '/';
            }
        }

        if (!$siteUrl) {
            // Voor affiliate template: APP_NAME = domain
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

    private function generateBlogFromPrompt(array $promptData): bool
    {
        $theme = $promptData['theme'];
        $niche = getSetting('site_niche', 'Premium Products');

        // Check of deze blog al bestaat (gebruik dezelfde logic als ContentOpportunityService)
        if ($this->hasExistingContentForKeyword($theme['primary_keyword'])) {
            $this->warn("   ⚠️ Blog over '{$theme['primary_keyword']}' bestaat al - overslaan");
            return false;
        }

        // Genereer interne link context
        $internalLinkContext = $this->linkingService->createLinkContext(
            $theme['primary_keyword'], 
            $niche
        );

        // Gebruik de nieuwe E-E-A-T geoptimaliseerde methode
        $content = $this->openAIService->generateGscOpportunityBlog(
            $theme,
            $niche,
            $internalLinkContext
        );
        

        // Parse JSON content
        $jsonContent = json_decode($content, true);
        if (!$jsonContent || !isset($jsonContent['title'])) {
            $this->error("   ❌ AI content generatie gefaald - ongeldig JSON");
            return false;
        }

        // Check if this is a fallback response
        if (!empty($jsonContent['is_fallback'])) {
            $this->error("   ❌ OpenAI genereerde fallback content - API call gefaald");
            return false;
        }

        // Kwaliteit check - minimum woorden
        $allText = '';
        
        // Extract text from title and standfirst
        $allText .= $jsonContent['title'] ?? '';
        $allText .= ' ' . ($jsonContent['standfirst'] ?? '');
        
        // Extract text from sections
        foreach ($jsonContent['sections'] ?? [] as $section) {
            $allText .= ' ' . ($section['heading'] ?? '');
            
            if (!empty($section['subheadings'])) {
                $allText .= ' ' . implode(' ', $section['subheadings']);
            }
            
            if (!empty($section['paragraphs'])) {
                $allText .= ' ' . implode(' ', $section['paragraphs']);
            }
        }
        
        // Extract text from closing
        if (!empty($jsonContent['closing'])) {
            $allText .= ' ' . ($jsonContent['closing']['headline'] ?? '');
            $allText .= ' ' . ($jsonContent['closing']['summary'] ?? '');
        }
        
        $wordCount = str_word_count($allText);
        
        
        if ($wordCount < 400) {
            $this->warn("   ⚠️ Content te kort ({$wordCount} woorden) - overslaan");
            $this->warn("   🔍 Debug: Raw content length: " . strlen($content));
            return false;
        }

        // Genereer meta tags
        $metaTags = $this->openAIService->generateMetaTags(
            $jsonContent['title'],
            "Expert artikel over {$theme['primary_keyword']} gebaseerd op zoekdata",
            getSetting('site_name', '')
        );

        // Maak unieke slug
        $baseSlug = Str::slug($jsonContent['title']);
        $slug = $baseSlug;
        $counter = 1;
        while (BlogPost::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        // Sla E-E-A-T geoptimaliseerde blog op
        BlogPost::create([
            'title' => $jsonContent['title'],
            'slug' => $slug,
            'content' => $content, // Enhanced JSON v3 format with E-E-A-T
            'excerpt' => Str::limit($jsonContent['standfirst'] ?? $jsonContent['title'], 240),
            'type' => 'gsc_opportunity', // GSC-generated content
            'status' => 'published',
            'meta_title' => $metaTags['meta_title'] ?? $jsonContent['title'],
            'meta_description' => $metaTags['meta_description'] ?? '',
            'product_id' => null, // Geen specifiek product
            // V3 format - laat legacy fields null
            'intro' => null,
            'main_content' => null,
            'benefits' => null,
            'usage_tips' => null,
            'closing' => null,
        ]);

        // Markeer gebruikte keywords als processed
        $this->markKeywordsAsProcessed($theme['related_keywords']);

        $this->line("   📊 Content kwaliteit: {$wordCount} woorden, E-E-A-T geoptimaliseerd");

        return true;
    }

    private function markKeywordsAsProcessed(array $keywords): void
    {
        // Update status van gebruikt keywords
        SearchConsoleData::whereIn('query', $keywords)
            ->where('status', 'active')
            ->update(['status' => 'processed']);
    }

    private function hasExistingContentForKeyword(string $keyword): bool
    {
        $cleanKeyword = strtolower(trim($keyword));
        
        if (strlen($cleanKeyword) < 5) {
            return false;
        }
        
        $blogs = BlogPost::where('status', 'published')->get();
        $keywordWords = explode(' ', $cleanKeyword);
        
        foreach ($blogs as $blog) {
            $cleanTitle = strtolower(trim($blog->title));
            $titleWords = explode(' ', $cleanTitle);
            
            // Check 1: Exacte keyword match met lagere threshold voor merknamen
            if (str_contains($cleanTitle, $cleanKeyword)) {
                $titleLength = strlen($cleanTitle);
                $keywordLength = strlen($cleanKeyword);
                
                // Lagere threshold (30% ipv 50%) voor betere duplicate detection
                if (($keywordLength / $titleLength) > 0.3) {
                    return true;
                }
            }
            
            // Check 2: Brand/merk detection - veel merkspecifieker
            $brandWords = $this->extractBrandWords($keywordWords);
            if (!empty($brandWords)) {
                $titleBrandWords = $this->extractBrandWords($titleWords);
                
                // Als beide merknamen bevatten en overlap hebben = duplicate
                if (!empty($titleBrandWords)) {
                    $brandOverlap = array_intersect($brandWords, $titleBrandWords);
                    if (!empty($brandOverlap)) {
                        // Kijk ook naar product context
                        $productOverlap = $this->calculateProductOverlap($keywordWords, $titleWords);
                        if ($productOverlap > 0.4) { // 40% product overlap + merk = duplicate
                            return true;
                        }
                    }
                }
            }
            
            // Check 3: Consecutive word matches (aangepast)
            if (count($keywordWords) >= 2) { // Verlaagd van 3 naar 2 voor betere detectie
                $maxConsecutive = $this->findMaxConsecutiveMatches($titleWords, $keywordWords);
                
                // Voor korte keywords (2-3 woorden): minder stricte match
                if (count($keywordWords) <= 3 && $maxConsecutive >= 2) {
                    return true;
                }
                
                // Voor langere keywords: 3+ consecutive matches
                if (count($keywordWords) > 3 && $maxConsecutive >= 3) {
                    return true;
                }
            }
            
            // Check 4: High similarity score (Levenshtein-gebaseerd)
            $similarity = $this->calculateStringSimilarity($cleanKeyword, $cleanTitle);
            if ($similarity > 0.7) { // 70% similarity = waarschijnlijk duplicate
                return true;
            }
        }
        
        return false;
    }

    private function extractBrandWords(array $words): array
    {
        // Bekende merknamen en patronen die wijzen op merken
        $brandIndicators = [
            // Directe merknamen (uit je affiliate program)
            'lacardia', 'zentriq', 'xiaomi', 'kingsmith', 'y-rain', 'walkingpad',
            'philips', 'samsung', 'bosch', 'ninja', 'tefal', 'princess',
            // Patronen die vaak merken zijn
        ];
        
        $brands = [];
        foreach ($words as $word) {
            // Directe merk match
            if (in_array($word, $brandIndicators)) {
                $brands[] = $word;
            }
            
            // Kapitalisatie patroon (merknamen zijn vaak CamelCase/Pascal)
            if (strlen($word) > 3 && preg_match('/^[A-Z][a-z]/', $word)) {
                $brands[] = strtolower($word);
            }
        }
        
        return array_unique($brands);
    }

    private function calculateProductOverlap(array $keywords, array $title): float
    {
        // Product-specifieke woorden (alles behalve stopwoorden)
        $stopwords = ['de', 'het', 'een', 'van', 'en', 'met', 'voor', 'in', 'op', 'bij', 'naar', 'aan', 'om', 'over', 'als', 'is', 'zijn', 'was', 'waren', 'heeft', 'hebben', 'had', 'hadden'];
        
        $keywordProduct = array_diff($keywords, $stopwords);
        $titleProduct = array_diff($title, $stopwords);
        
        if (empty($keywordProduct) || empty($titleProduct)) {
            return 0;
        }
        
        $overlap = array_intersect($keywordProduct, $titleProduct);
        return count($overlap) / min(count($keywordProduct), count($titleProduct));
    }

    private function findMaxConsecutiveMatches(array $titleWords, array $keywordWords): int
    {
        $maxConsecutive = 0;
        
        for ($i = 0; $i <= count($titleWords) - count($keywordWords); $i++) {
            $consecutive = 0;
            
            for ($j = 0; $j < count($keywordWords); $j++) {
                if (isset($titleWords[$i + $j]) && $titleWords[$i + $j] === $keywordWords[$j]) {
                    $consecutive++;
                } else {
                    break;
                }
            }
            
            $maxConsecutive = max($maxConsecutive, $consecutive);
        }
        
        return $maxConsecutive;
    }

    private function calculateStringSimilarity(string $str1, string $str2): float
    {
        $maxLength = max(strlen($str1), strlen($str2));
        if ($maxLength === 0) {
            return 1.0;
        }
        
        $levenshtein = levenshtein($str1, $str2);
        return 1 - ($levenshtein / $maxLength);
    }

    private function showDetailedOpportunities($themes): void
    {
        foreach ($themes as $index => $theme) {
            $this->newLine();
            $this->info("📋 Thema " . ($index + 1) . ": {$theme['theme_name']}");
            $this->line("🎯 Primary keyword: {$theme['primary_keyword']}");
            $this->line("📊 Total impressions: " . number_format($theme['total_impressions']));
            $this->line("🎨 Content type: {$theme['content_type']}");
            $this->line("💡 Suggested angle: {$theme['suggested_angle']}");
            $this->line("🔑 Related keywords: " . implode(', ', array_slice($theme['related_keywords'], 0, 5)));
        }
    }
}