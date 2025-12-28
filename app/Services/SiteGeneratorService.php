<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\BlogVariation;
use App\Models\ContentBlock;
use App\Models\Setting;
use App\Models\TeamMember;
use App\Models\InformationPage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;
use ImagickPixel;

/**
 * SiteGeneratorService
 *
 * Orchestrates full site setup automation:
 * - Generates settings via OpenAI
 * - Generates content blocks via OpenAI
 * - Generates team members (delegates to existing command)
 * - Generates favicon
 * - Optionally generates seed blog posts
 *
 * Designed to be BACKWARDS COMPATIBLE with 27 existing sites.
 */
class SiteGeneratorService
{
    protected SiteContentGeneratorService $contentGenerator;

    public function __construct(SiteContentGeneratorService $contentGenerator)
    {
        $this->contentGenerator = $contentGenerator;
    }

    /**
     * Check if site already has content (safety check for existing sites)
     *
     * @return array ['has_settings' => bool, 'has_content_blocks' => bool, 'has_team' => bool]
     */
    public function checkExistingContent(): array
    {
        return [
            'has_settings' => Setting::count() > 0,
            'has_content_blocks' => ContentBlock::count() > 0,
            'has_team' => TeamMember::count() > 0,
            'has_blogs' => BlogPost::count() > 0,
        ];
    }

    /**
     * Generate and save settings to database
     *
     * @param array $input ['niche', 'domain', 'primary_color']
     * @return array Created settings
     */
    public function generateSettings(array $input): array
    {
        $settings = $this->contentGenerator->generateSettings($input);

        $created = [];

        DB::beginTransaction();
        try {
            foreach ($settings as $key => $value) {
                $setting = Setting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $value]
                );
                $created[$key] = $setting->value;
            }

            DB::commit();

            // Clear settings cache
            \Cache::flush();

            return $created;

        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception("Failed to save settings: " . $e->getMessage());
        }
    }

    /**
     * Generate and save content blocks to database
     *
     * @param string $niche
     * @param string $siteName
     * @param string|null $uniqueFocus
     * @return array Created content blocks
     */
    public function generateContentBlocks(string $niche, string $siteName, ?string $uniqueFocus = null): array
    {
        $contentBlocks = $this->contentGenerator->generateContentBlocks($niche, $siteName, $uniqueFocus);

        $created = [];

        DB::beginTransaction();
        try {
            foreach ($contentBlocks as $key => $content) {
                $block = ContentBlock::updateOrCreate(
                    ['key' => $key],
                    ['content' => $content]
                );
                $created[$key] = $block->content;
            }

            DB::commit();

            // Clear content blocks cache
            \Cache::flush();

            return $created;

        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception("Failed to save content blocks: " . $e->getMessage());
        }
    }

    /**
     * Generate favicon (SVG-based with first letter of site name)
     *
     * @param string $siteName
     * @param string $primaryColor
     * @return string URL to favicon
     */
    public function generateFavicon(string $siteName, string $primaryColor): string
    {
        // Get first letter of site name
        $letter = strtoupper(substr($siteName, 0, 1));

        // Generate SVG favicon with letter
        $svg = $this->generateFaviconSvg($letter, $primaryColor);

        // Save multiple sizes
        $sizes = [
            'favicon-16x16.png' => 16,
            'favicon-32x32.png' => 32,
            'apple-touch-icon.png' => 180,
            'android-chrome-192x192.png' => 192,
            'android-chrome-512x512.png' => 512,
        ];

        $savedFiles = [];

        foreach ($sizes as $filename => $size) {
            try {
                // Convert SVG to PNG using imagick if available
                if (extension_loaded('imagick')) {
                    $image = new Imagick();
                    $image->setBackgroundColor(new ImagickPixel('transparent'));
                    $image->readImageBlob($svg);
                    $image->setImageFormat('png32');
                    $image->resizeImage($size, $size, Imagick::FILTER_LANCZOS, 1);

                    $pngData = $image->getImageBlob();
                    Storage::disk('public')->put('favicons/' . $filename, $pngData);

                    $savedFiles[$filename] = '/storage/favicons/' . $filename;
                    $image->clear();
                    $image->destroy();
                } else {
                    // Fallback: save SVG as favicon.svg
                    if ($filename === 'favicon-32x32.png') {
                        Storage::disk('public')->put('favicons/favicon.svg', $svg);
                        $savedFiles['favicon.svg'] = '/storage/favicons/favicon.svg';
                    }
                }
            } catch (\Exception $e) {
                \Log::warning("Failed to generate favicon size {$size}: " . $e->getMessage());
            }
        }

        // Return primary favicon URL
        return $savedFiles['favicon-32x32.png'] ?? $savedFiles['favicon.svg'] ?? '';
    }

    /**
     * Generate seed blog posts (optional)
     *
     * @param string $niche
     * @param string $siteName
     * @return array Created blog post IDs
     */
    public function generateSeedBlogPosts(string $niche, string $siteName): array
    {
        $seedIdeas = $this->contentGenerator->generateSeedBlogPosts($niche, $siteName);

        if (empty($seedIdeas)) {
            return [];
        }

        $created = [];

        foreach ($seedIdeas as $idea) {
            try {
                // Create blog post with status 'draft' so it can be reviewed
                $blog = BlogPost::create([
                    'title' => $idea['title'],
                    'excerpt' => $idea['excerpt'] ?? null,
                    'content' => json_encode([
                        'version' => 'seed.v1',
                        'note' => 'This is a seed blog post idea. Generate full content using: php artisan blog:generate',
                        'suggested_keywords' => $idea['suggested_keywords'] ?? [],
                        'content_type' => $idea['content_type'] ?? 'koopgids',
                    ]),
                    'status' => 'draft',
                    'type' => 'general',
                ]);

                $created[] = $blog->id;

            } catch (\Exception $e) {
                \Log::warning("Failed to create seed blog post: " . $e->getMessage(), [
                    'idea' => $idea
                ]);
            }
        }

        return $created;
    }

    /**
     * Generate and save blog variations to database
     *
     * @param string $niche
     * @param string|null $uniqueFocus
     * @return int Count of variations created
     */
    public function generateBlogVariations(string $niche, ?string $uniqueFocus = null): int
    {
        $variations = $this->contentGenerator->generateBlogVariations($niche, $uniqueFocus);

        if (empty($variations)) {
            return 0;
        }

        $count = 0;

        DB::beginTransaction();
        try {
            // Delete old variations for this niche (clean slate)
            BlogVariation::where('niche', $niche)->delete();

            // Insert new variations
            foreach ($variations as $category => $values) {
                foreach ($values as $value) {
                    BlogVariation::create([
                        'niche' => $niche,
                        'category' => $category,
                        'value' => $value,
                    ]);
                    $count++;
                }
            }

            DB::commit();

            return $count;

        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception("Failed to save blog variations: " . $e->getMessage());
        }
    }


    /**
     * Get summary of what will be generated
     *
     * @param array $input
     * @return array
     */
    public function getGenerationSummary(array $input): array
    {
        return [
            'settings' => [
                'count' => 7,
                'keys' => ['site_name', 'site_niche', 'tagline', 'meta_keywords', 'contact_email', 'primary_color', 'font_family'],
            ],
            'content_blocks' => [
                'count' => 23,
                'keys' => [
                    'homepage.*' => 4,
                    'producten_index_*' => 5,
                    'producten_top_*' => 4,
                    'merken_index_*' => 4,
                    'reviews.*' => 3,
                    'blogs.*' => 3,
                ],
            ],
            'team' => [
                'count' => 3,
                'note' => 'Will run team:generate command',
            ],
            'favicon' => [
                'formats' => ['16x16', '32x32', '180x180 (apple)', '192x192 (android)', '512x512 (android)'],
            ],
            'seed_blogs' => [
                'count' => 3,
                'status' => 'draft (need to generate full content)',
            ],
        ];
    }

    /* =======================
     *   PRIVATE HELPERS
     * ======================= */

    /**
     * Generate SVG favicon with letter and color
     */
    protected function generateFaviconSvg(string $letter, string $primaryColor): string
    {
        // Calculate lighter shade for gradient
        $lightColor = $this->adjustBrightness($primaryColor, 40);

        return <<<SVG
<svg width="512" height="512" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:{$lightColor};stop-opacity:1" />
      <stop offset="100%" style="stop-color:{$primaryColor};stop-opacity:1" />
    </linearGradient>
  </defs>
  <rect width="512" height="512" rx="90" fill="url(#grad)"/>
  <text x="50%" y="65%" font-family="system-ui, -apple-system, BlinkMacSystemFont, Arial, sans-serif" font-size="340" font-weight="900" fill="#ffffff" text-anchor="middle" dominant-baseline="middle">{$letter}</text>
</svg>
SVG;
    }

    /**
     * Adjust hex color brightness
     */
    protected function adjustBrightness(string $hex, int $steps): string
    {
        // Remove # if present
        $hex = ltrim($hex, '#');

        // Convert to RGB
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        // Adjust brightness
        $r = max(0, min(255, $r + $steps));
        $g = max(0, min(255, $g + $steps));
        $b = max(0, min(255, $b + $steps));

        // Convert back to hex
        return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT)
                   . str_pad(dechex($g), 2, '0', STR_PAD_LEFT)
                   . str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Generate blog templates for template-based blog generation
     */
    public function generateBlogTemplates(string $niche, ?string $uniqueFocus = null): int
    {
        $templates = $this->contentGenerator->generateBlogTemplates($niche, $uniqueFocus);

        if (empty($templates)) {
            return 0;
        }

        $count = 0;

        DB::beginTransaction();
        try {
            // Delete old templates for this niche (clean slate)
            \App\Models\BlogTemplate::where('niche', $niche)->delete();

            // Insert new templates
            foreach ($templates as $template) {
                \App\Models\BlogTemplate::create([
                    'niche' => $niche,
                    'title_template' => $template['title_template'],
                    'slug_template' => $template['slug_template'],
                    'seo_focus_keyword' => $template['seo_focus_keyword'],
                    'content_outline' => $template['content_outline'],
                    'target_word_count' => $template['target_word_count'] ?? 1500,
                    'cta_type' => $template['cta_type'] ?? 'comparison_table',
                    'variables' => $template['variables'] ?? [],
                    'min_days_between_reuse' => $template['min_days_between_reuse'] ?? 90, // Extended from 60 to 90 days
                ]);
                $count++;
            }

            DB::commit();

            return $count;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate product blog templates for the given niche
     * Returns count of templates generated
     */
    public function generateProductBlogTemplates(string $niche, ?string $uniqueFocus = null): int
    {
        $templates = $this->contentGenerator->generateProductBlogTemplates($niche, $uniqueFocus);

        if (empty($templates)) {
            return 0;
        }

        $count = 0;

        DB::beginTransaction();
        try {
            // Delete old product blog templates for this niche (clean slate)
            \App\Models\ProductBlogTemplate::where('niche', $niche)->delete();

            // Insert new templates
            foreach ($templates as $template) {
                \App\Models\ProductBlogTemplate::create([
                    'niche' => $niche,
                    'title_template' => $template['title_template'],
                    'slug_template' => $template['slug_template'],
                    'seo_focus_keyword' => $template['seo_focus_keyword'],
                    'content_outline' => $template['content_outline'],
                    'target_word_count' => $template['target_word_count'] ?? 1500,
                    'tone' => $template['tone'] ?? 'practical',
                    'scenario_focus' => $template['scenario_focus'] ?? 'how_to',
                    'cta_type' => $template['cta_type'] ?? 'product_primary',
                    'variables' => $template['variables'] ?? [],
                    'min_days_between_reuse' => $template['min_days_between_reuse'] ?? 90, // Extended from 60 to 90 days
                ]);
                $count++;
            }

            DB::commit();

            return $count;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate information pages for the given niche
     * Returns count of pages generated
     */
    public function generateInformationPages(string $niche, ?string $uniqueFocus = null): int
    {
        try {
            $openai = app(\App\Services\OpenAIService::class);

            // Generate topics
            $topicsPrompt = $this->buildInformationTopicsPrompt($niche, $uniqueFocus);
            $topicsJson = $openai->generateFromPrompt($topicsPrompt, 'gpt-4o-mini');

            // Clean JSON
            $topicsJson = preg_replace('/^```(?:json)?\s*/m', '', $topicsJson);
            $topicsJson = preg_replace('/\s*```$/m', '', $topicsJson);
            $topics = json_decode(trim($topicsJson), true);

            if (!$topics || !is_array($topics)) {
                \Log::warning('Could not parse information page topics JSON');
                return 0;
            }

            $count = 0;

            foreach ($topics as $index => $topic) {
                $menuTitle = $topic['menu_title'] ?? $topic['title'] ?? "Topic " . ($index + 1);
                $articleTitle = $topic['article_title'] ?? $menuTitle;
                $slug = $topic['slug'] ?? Str::slug($menuTitle);
                $metaDescription = $topic['meta_description'] ?? '';

                // Check if slug exists
                if (InformationPage::where('slug', $slug)->exists()) {
                    $slug = $slug . '-' . time();
                }

                // Generate outline
                $outlinePrompt = $this->buildInformationOutlinePrompt($articleTitle, $niche);
                $outlineJson = $openai->generateFromPrompt($outlinePrompt, 'gpt-4o-mini');
                $outlineJson = preg_replace('/^```(?:json)?\s*/m', '', $outlineJson);
                $outlineJson = preg_replace('/\s*```$/m', '', $outlineJson);
                $outline = json_decode(trim($outlineJson), true);

                if (!$outline || !isset($outline['sections'])) {
                    \Log::warning("Could not generate outline for: {$articleTitle}");
                    continue;
                }

                // Generate intro
                $introPrompt = $this->buildInformationIntroPrompt($articleTitle, $niche, $outline);
                $intro = $openai->generateFromPrompt($introPrompt, 'gpt-4o-mini');
                $intro = $this->cleanHtml($intro);

                // Generate sections
                $sections = [];
                foreach ($outline['sections'] as $sectionOutline) {
                    $sectionPrompt = $this->buildInformationSectionPrompt($articleTitle, $niche, $sectionOutline, $outline);
                    $sectionContent = $openai->generateFromPrompt($sectionPrompt, 'gpt-4o-mini');
                    $sections[] = $this->cleanHtml($sectionContent);
                }

                // Generate conclusion
                $conclusionPrompt = $this->buildInformationConclusionPrompt($articleTitle, $niche, $outline);
                $conclusion = $openai->generateFromPrompt($conclusionPrompt, 'gpt-4o-mini');
                $conclusion = $this->cleanHtml($conclusion);

                // Combine all parts
                $content = $intro . "\n\n" . implode("\n\n", $sections) . "\n\n" . $conclusion;

                // Generate excerpt
                $excerptPrompt = $this->buildInformationExcerptPrompt($articleTitle, $content);
                $excerpt = $openai->generateFromPrompt($excerptPrompt, 'gpt-4o-mini');
                $excerpt = trim($excerpt);

                // Save to database
                $order = InformationPage::max('order') + 1;
                InformationPage::create([
                    'title' => $articleTitle,
                    'menu_title' => $menuTitle,
                    'slug' => $slug,
                    'content' => $content,
                    'excerpt' => $excerpt,
                    'meta_title' => Str::limit($articleTitle, 60),
                    'meta_description' => $metaDescription ?: Str::limit($excerpt, 155),
                    'order' => $order,
                    'is_active' => true,
                ]);

                $count++;
            }

            return $count;

        } catch (\Exception $e) {
            \Log::error('Failed to generate information pages: ' . $e->getMessage());
            throw $e;
        }
    }

    private function cleanHtml(string $html): string
    {
        $html = preg_replace('/^```(?:html)?\s*/m', '', $html);
        $html = preg_replace('/\s*```$/m', '', $html);
        return trim($html);
    }

    private function buildInformationTopicsPrompt(string $niche, ?string $uniqueFocus = null): string
    {
        $uniqueFocusSection = $uniqueFocus ? "\n\nUNIEKE FOCUS: {$uniqueFocus} (gebruik spaarzaam waar relevant)" : '';

        return <<<PROMPT
Je bent een SEO-expert voor Nederlandse affiliate websites over {$niche}.{$uniqueFocusSection}

DOEL: Genereer 5-7 informatie pagina onderwerpen die keuze-vragen beantwoorden tijdens het koopproces.

BELANGRIJKE CRITERIA:
1. Beantwoord vragen die mensen ECHT hebben tijdens vergelijken
2. Focus op praktische koopoverwegingen
3. Specifiek voor {$niche}
4. Moet natuurlijk leiden naar productvergelijking

SUCCESVOLLE PATRONEN:
1. VERGELIJKING: "Enkele of dubbele lade?"
2. SPECIFICATIE: "Welke maat past bij jou?"
3. PRAKTISCH: "Hoeveel ruimte neemt het in?"
4. FUNCTIE: "Belangrijkste functies"
5. PRIJS: "Wat kost een goede {$niche}?"
6. FOUTEN: "Veelgemaakte fouten bij kopen"

TAALGEBRUIK:
- menu_title: 30-45 karakters, natuurlijk Nederlands met lidwoorden
- article_title: 50-70 karakters, kan context toevoegen
- slug: URL-friendly
- meta_description: 150-155 karakters

Return ALLEEN een JSON array:
[
  {
    "menu_title": "...",
    "article_title": "...",
    "slug": "...",
    "meta_description": "..."
  }
]
PROMPT;
    }

    private function buildInformationOutlinePrompt(string $title, string $niche): string
    {
        return <<<PROMPT
Je bent een expert content strategist voor Nederlandse affiliate websites over {$niche}.

OPDRACHT: Maak een gedetailleerde outline voor: "{$title}"

VEREISTEN:
- 4-6 H2 hoofdsecties
- Elke sectie: 2-4 key points
- Logische opbouw

OUTPUT FORMAT (JSON):
{
  "intro_summary": "Wat de intro moet behandelen (50 woorden)",
  "sections": [
    {
      "h2_title": "Titel",
      "purpose": "Waarom deze sectie belangrijk is",
      "key_points": ["Punt 1", "Punt 2", "Punt 3"],
      "suggested_elements": ["table", "list", "blockquote"]
    }
  ],
  "conclusion_summary": "Wat de conclusie moet bevatten (50 woorden)"
}

Return ALLEEN valid JSON.
PROMPT;
    }

    private function buildInformationIntroPrompt(string $title, string $niche, array $outline): string
    {
        $introSummary = $outline['intro_summary'] ?? '';
        return <<<PROMPT
Schrijf een pakkende intro voor: "{$title}" over {$niche}.

INTRO MOET BEVATTEN: {$introSummary}

VEREISTEN:
- 150-200 woorden
- Hook direct
- Professioneel maar toegankelijk

Return ALLEEN HTML (<p> tags).
PROMPT;
    }

    private function buildInformationSectionPrompt(string $title, string $niche, array $sectionOutline, array $fullOutline): string
    {
        $h2Title = $sectionOutline['h2_title'] ?? 'Sectie';
        $purpose = $sectionOutline['purpose'] ?? '';
        $keyPoints = isset($sectionOutline['key_points']) ? implode("\n- ", $sectionOutline['key_points']) : '';

        return <<<PROMPT
Schrijf de sectie "{$h2Title}" voor "{$title}" over {$niche}.

DOEL: {$purpose}

KEY POINTS:
- {$keyPoints}

VEREISTEN:
- Begin met <h2>{$h2Title}</h2>
- 250-400 woorden
- 2-4 H3 subsecties
- Concrete cijfers en voorbeelden

Return ALLEEN HTML (start met <h2>).
PROMPT;
    }

    private function buildInformationConclusionPrompt(string $title, string $niche, array $outline): string
    {
        $conclusionSummary = $outline['conclusion_summary'] ?? '';
        return <<<PROMPT
Schrijf de conclusie voor: "{$title}" over {$niche}.

CONCLUSIE MOET BEVATTEN: {$conclusionSummary}

VEREISTEN:
- Begin met <h2>Conclusie</h2>
- 200-300 woorden
- Decision framework
- Praktische next step

Return ALLEEN HTML (start met <h2>).
PROMPT;
    }

    private function buildInformationExcerptPrompt(string $title, string $content): string
    {
        return <<<PROMPT
Schrijf een korte samenvatting van maximaal 200 karakters voor: {$title}

Max 200 karakters, pakkend en informatief, Nederlandse taal.

Alleen de excerpt tekst, geen extra uitleg.
PROMPT;
    }
}
