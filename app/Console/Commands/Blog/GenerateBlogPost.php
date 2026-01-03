<?php

namespace App\Console\Commands\Blog;

use App\Helpers\BlogFormatter;
use App\Models\BlogPost;
use App\Models\BlogVariation;
use App\Models\BlogTemplate;
use App\Models\Product;
use App\Models\TeamMember;
use App\Services\OpenAIService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateBlogPost extends Command
{
    protected $signature = 'app:generate-blog {product_id?} {count=1}';

    protected $description = 'Genereer automatisch een of meerdere unieke blogposts op basis van niche-instellingen via OpenAI';

    protected OpenAIService $openAI;

    public function __construct(OpenAIService $openAI)
    {
        parent::__construct();
        $this->openAI = $openAI;
    }

    public function handle()
    {
        $productId = $this->argument('product_id');
        $count = (int) $this->argument('count');

        // Fix: Zet lege of 'null' waardes om naar echte null zodat MySQL geen fout geeft
        if (empty($productId) || strtolower($productId) === 'null') {
            $productId = null;
        }

        for ($i = 0; $i < $count; $i++) {
            $this->generateSingleBlog($productId);
        }

        $this->info("{$count} blog(s) succesvol gegenereerd.");
    }

    private function generateSingleBlog($productId = null)
    {
        $description = '';
        $brand = '';
        $product = null;

        $niche = trim(getSetting('site_niche', ''));
        $toneOfVoice = trim(getSetting('tone_of_voice', ''));
        $targetAudience = trim(getSetting('target_audience', ''));

        // Try template-based generation first (new system)
        $template = BlogTemplate::pickTemplate($niche);

        if ($template) {
            $this->generateBlogFromTemplate($template, $productId, $niche, $toneOfVoice, $targetAudience);
            return;
        }

        // Fallback to old variation system if no templates available
        $this->warn('No blog templates found. Falling back to variation system.');
        $selectedVariations = [];
        $variationAngle = $this->pickBlogVariationAngle($niche, $selectedVariations);

        if ($niche === '' || $toneOfVoice === '' || $targetAudience === '') {
            $this->warn('Instellingen ontbreken. Blog wordt fallback.');
            $slug = 'blog-fallback-'.Str::random(4);
            $content = '<p>Hier zou een blog moeten komen te staan, maar de juiste instellingen ontbreken nog.</p>';

            $blogPost = BlogPost::create([
                'product_id' => $productId,
                'team_member_id' => $this->getRandomTeamMemberId(),
                'title' => 'Blog in afwachting van configuratie',
                'slug' => $slug,
                'content' => $content,
                'excerpt' => strip_tags($content),
                'type' => 'general',
                'status' => 'draft',
                'meta_title' => 'Nog te genereren blog',
                'meta_description' => 'Deze blog wordt automatisch aangemaakt zodra de juiste instellingen beschikbaar zijn.',
                'intro' => $content,
            ]);

            $this->info("Fallback-blog opgeslagen met ID: {$blogPost->id}");

            return;
        }

        if ($productId) {
            $product = Product::find($productId);
            if (! $product) {
                $this->error("Product met ID {$productId} niet gevonden.");

                return;
            }
            $description = $product->description ?? '';
            $brand = $product->brand ?? '';
        }

        // Generate blog using BlogVariation angle
        if ($productId && $product) {
            // Product-focused blog using the variation angle
            $topicContext = "Product blog over {$product->title} (merk: {$brand}) {$variationAngle}. Niche: {$niche}. Doelgroep: {$targetAudience}.";
            $content = $this->openAI->generateProductBlog(
                $variationAngle,
                $topicContext,
                $brand ?: 'Premium'
            );
        } else {
            // General blog using the variation angle
            $topicContext = "Algemeen blog in de niche {$niche} gericht op {$targetAudience}. {$variationAngle}. Tone: {$toneOfVoice}.";
            $content = $this->openAI->generateProductBlog(
                $variationAngle,
                $topicContext,
                $niche
            );
        }

        // Validate JSON content FIRST before doing anything else
        $jsonContent = json_decode($content, true);
        if (!$jsonContent || !isset($jsonContent['title']) || empty($jsonContent['sections'])) {
            $this->error("OpenAI genereerde ongeldige content. JSON: " . substr($content, 0, 500) . "...");
            $this->error("JSON decode error: " . json_last_error_msg());
            return;
        }

        // Check if this is a fallback response (OpenAI failed)
        if (!empty($jsonContent['is_fallback'])) {
            $this->error("OpenAI genereerde fallback content - API call waarschijnlijk gefaald");
            $this->error("Check logs voor details, retry later");
            return;
        }

        // Additional quality check: ensure sections have actual content
        $hasValidContent = false;
        foreach ($jsonContent['sections'] as $section) {
            if (isset($section['paragraphs']) && is_array($section['paragraphs']) && count($section['paragraphs']) > 0) {
                foreach ($section['paragraphs'] as $paragraph) {
                    if (strlen($paragraph) > 50) { // At least 50 characters of actual content
                        $hasValidContent = true;
                        break 2;
                    }
                }
            }
        }

        if (!$hasValidContent) {
            $this->error("OpenAI genereerde content zonder substantiële paragrafen.");
            $this->error("Sections preview: " . json_encode(array_slice($jsonContent['sections'], 0, 2)));
            return;
        }

        // Extract the AI-generated title from the validated JSON content
        $title = $jsonContent['title'];

        $this->info("Titel gegenereerd: {$title}");

        // Detect low-quality generic titles that indicate content generation issues
        if (preg_match('/^Blog over|^Algemeen blog|^Artikel over/i', $title)) {
            $this->warn("OpenAI genereerde een generieke titel: '{$title}' - dit kan duiden op content generatie problemen.");
            $this->warn("Content preview: " . substr(json_encode($jsonContent['sections'] ?? []), 0, 200));
        }

        // Check for exact duplicate titles
        if (BlogPost::where('title', $title)->exists()) {
            $this->warn("Titel '{$title}' bestaat al. Overslaan.");
            return;
        }

        // Check for very similar titles (improved fuzzy match)
        // Extract first significant part before common separators
        $titleBase = preg_replace('/[\:\?\!]\s*.*/u', '', $title);
        $titleBase = trim($titleBase);

        // Check for titles that start with the same base (minimum 15 chars to avoid false positives)
        if (strlen($titleBase) >= 15) {
            $similarTitles = BlogPost::where('title', 'like', $titleBase . '%')->count();
            if ($similarTitles > 0) {
                $this->warn("Vergelijkbare titel gevonden voor '{$titleBase}'. Overslaan om duplicaten te voorkomen.");
                return;
            }
        }

        // Generate meta tags (only after all validation passes)
        $metaTags = $this->openAI->generateMetaTags($title, $description, $brand);
        $metaTitle = $metaTags['meta_title'] ?? $title;
        $metaDescription = $metaTags['meta_description'] ?? '';

        $slug = Str::slug($metaTitle).'-'.Str::random(4);

        // For v3 JSON content, create a simple excerpt from the title
        $excerpt = Str::limit($title, 150);

        // Retry mechanism for database issues
        $maxRetries = 3;
        $retryDelay = 2; // seconds
        $blogPost = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $blogPost = BlogPost::create([
                    'product_id' => $productId,
                    'team_member_id' => $this->getRandomTeamMemberId(),
                    'title' => $title,
                    'slug' => $slug,
                    'content' => $content, // This is now JSON v3 format
                    'excerpt' => $excerpt,
                    'type' => $productId ? 'product' : 'general',
                    'status' => 'published',
                    'meta_title' => $metaTitle,
                    'meta_description' => $metaDescription,
                    // Leave these null for v3 - content is in JSON format
                    'intro' => null,
                    'main_content' => null,
                    'benefits' => null,
                    'usage_tips' => null,
                    'closing' => null,
                ]);

                $this->info("Blog succesvol opgeslagen met ID: {$blogPost->id}");

                // Update last_used_at for selected variations
                if (!empty($selectedVariations)) {
                    $variationIds = array_map(fn($v) => $v->id, $selectedVariations);
                    BlogVariation::whereIn('id', $variationIds)->update(['last_used_at' => now()]);
                    $this->info("Updated " . count($variationIds) . " variation(s) usage timestamp");
                }

                break; // Success, exit retry loop

            } catch (\Exception $e) {
                $this->warn("Database fout poging {$attempt}/{$maxRetries}: " . $e->getMessage());

                if ($attempt === $maxRetries) {
                    // Final attempt failed - store content in temporary file for recovery
                    $tempFile = storage_path('app/failed_blogs/blog_' . date('Y-m-d_H-i-s') . '_' . Str::random(8) . '.json');
                    @mkdir(dirname($tempFile), 0755, true);

                    $failedBlogData = [
                        'timestamp' => now()->toISOString(),
                        'product_id' => $productId,
                        'title' => $title,
                        'slug' => $slug,
                        'content' => $content,
                        'excerpt' => $excerpt,
                        'type' => $productId ? 'product' : 'general',
                        'meta_title' => $metaTitle,
                        'meta_description' => $metaDescription,
                        'error' => $e->getMessage(),
                    ];

                    file_put_contents($tempFile, json_encode($failedBlogData, JSON_PRETTY_PRINT));

                    $this->error("Blog kon niet opgeslagen worden na {$maxRetries} pogingen.");
                    $this->error("Content opgeslagen in: {$tempFile}");
                    $this->error("Run 'php artisan app:recover-failed-blogs' om later te herstellen.");
                    return;
                } else {
                    // Wait before retry
                    sleep($retryDelay);
                    $retryDelay *= 2; // Exponential backoff
                }
            }
        }
    }

    /**
     * Get a random team member ID for blog authorship
     * Returns null if no team members exist yet
     */
    private function getRandomTeamMemberId(): ?int
    {
        $teamMember = TeamMember::inRandomOrder()->first();
        return $teamMember?->id;
    }

    /**
     * Pick smart blog variation angle by combining 1-2 categories
     * Now includes smart tracking to avoid recently used variations (within 30 days)
     *
     * Examples of combinations:
     * - thema only: "Focus: gezonde recepten"
     * - thema + doelgroep: "Thema: afvaltips, Doelgroep: professionals met weinig tijd"
     * - speciale_moment + thema: "Timing: Black Friday, Thema: budget tips"
     * - probleem + seizoen: "Probleem: beperkte ruimte, Seizoen: winter"
     */
    private function pickBlogVariationAngle(string $niche, array &$selectedVariations): string
    {
        // 60% kans: thema only (breedste content)
        // 30% kans: thema + secondary category
        // 10% kans: secondary combination
        $random = rand(1, 100);

        $cutoffDate = now()->subDays(30);

        if ($random <= 60) {
            // Thema only - meest flexibel, prefer least recently used
            $thema = BlogVariation::where('niche', $niche)
                ->where('category', 'themas')
                ->where(function($query) use ($cutoffDate) {
                    $query->whereNull('last_used_at')
                          ->orWhere('last_used_at', '<', $cutoffDate);
                })
                ->orderBy('last_used_at', 'asc')
                ->orderByRaw('RAND()')
                ->first();

            // Fallback: allow recent if no old ones available
            if (!$thema) {
                $thema = BlogVariation::where('niche', $niche)
                    ->where('category', 'themas')
                    ->orderBy('last_used_at', 'asc')
                    ->orderByRaw('RAND()')
                    ->first();
            }

            if ($thema) {
                $selectedVariations[] = $thema;
                return "Focus op thema: {$thema->value}";
            }
        } elseif ($random <= 90) {
            // Thema + secondary category
            $thema = BlogVariation::where('niche', $niche)
                ->where('category', 'themas')
                ->where(function($query) use ($cutoffDate) {
                    $query->whereNull('last_used_at')
                          ->orWhere('last_used_at', '<', $cutoffDate);
                })
                ->orderBy('last_used_at', 'asc')
                ->orderByRaw('RAND()')
                ->first();

            if (!$thema) {
                $thema = BlogVariation::where('niche', $niche)
                    ->where('category', 'themas')
                    ->orderBy('last_used_at', 'asc')
                    ->orderByRaw('RAND()')
                    ->first();
            }

            $secondaryCategories = ['doelgroepen', 'speciale_momenten', 'seizoenen'];
            $secondaryCategory = $secondaryCategories[array_rand($secondaryCategories)];

            $secondary = BlogVariation::where('niche', $niche)
                ->where('category', $secondaryCategory)
                ->where(function($query) use ($cutoffDate) {
                    $query->whereNull('last_used_at')
                          ->orWhere('last_used_at', '<', $cutoffDate);
                })
                ->orderBy('last_used_at', 'asc')
                ->orderByRaw('RAND()')
                ->first();

            if (!$secondary) {
                $secondary = BlogVariation::where('niche', $niche)
                    ->where('category', $secondaryCategory)
                    ->orderBy('last_used_at', 'asc')
                    ->orderByRaw('RAND()')
                    ->first();
            }

            if ($thema && $secondary) {
                $selectedVariations[] = $thema;
                $selectedVariations[] = $secondary;

                $categoryLabel = match($secondaryCategory) {
                    'doelgroepen' => 'Doelgroep',
                    'speciale_momenten' => 'Timing/Moment',
                    'seizoenen' => 'Seizoen',
                    default => 'Context'
                };
                return "Thema: {$thema->value}, {$categoryLabel}: {$secondary->value}";
            }
        } else {
            // Secondary combinations (zonder thema)
            $categories = ['doelgroepen', 'problemen', 'gebruikssituaties', 'speciale_momenten', 'seizoenen'];
            $cat1 = $categories[array_rand($categories)];
            $cat2 = $categories[array_rand($categories)];

            // Avoid same category twice
            while ($cat1 === $cat2) {
                $cat2 = $categories[array_rand($categories)];
            }

            $var1 = BlogVariation::where('niche', $niche)
                ->where('category', $cat1)
                ->where(function($query) use ($cutoffDate) {
                    $query->whereNull('last_used_at')
                          ->orWhere('last_used_at', '<', $cutoffDate);
                })
                ->orderBy('last_used_at', 'asc')
                ->orderByRaw('RAND()')
                ->first();

            if (!$var1) {
                $var1 = BlogVariation::where('niche', $niche)
                    ->where('category', $cat1)
                    ->orderBy('last_used_at', 'asc')
                    ->orderByRaw('RAND()')
                    ->first();
            }

            $var2 = BlogVariation::where('niche', $niche)
                ->where('category', $cat2)
                ->where(function($query) use ($cutoffDate) {
                    $query->whereNull('last_used_at')
                          ->orWhere('last_used_at', '<', $cutoffDate);
                })
                ->orderBy('last_used_at', 'asc')
                ->orderByRaw('RAND()')
                ->first();

            if (!$var2) {
                $var2 = BlogVariation::where('niche', $niche)
                    ->where('category', $cat2)
                    ->orderBy('last_used_at', 'asc')
                    ->orderByRaw('RAND()')
                    ->first();
            }

            if ($var1 && $var2) {
                $selectedVariations[] = $var1;
                $selectedVariations[] = $var2;
                return ucfirst($cat1) . ": {$var1->value}, " . ucfirst($cat2) . ": {$var2->value}";
            }
        }

        // Fallback: single random variation from any category (prefer least recent)
        $variation = BlogVariation::where('niche', $niche)
            ->where(function($query) use ($cutoffDate) {
                $query->whereNull('last_used_at')
                      ->orWhere('last_used_at', '<', $cutoffDate);
            })
            ->orderBy('last_used_at', 'asc')
            ->orderByRaw('RAND()')
            ->first();

        if (!$variation) {
            $variation = BlogVariation::where('niche', $niche)
                ->orderBy('last_used_at', 'asc')
                ->orderByRaw('RAND()')
                ->first();
        }

        if (!$variation) {
            // No variations found - this should trigger an error instead of generic content
            $this->error("Geen blog variations gevonden voor niche: {$niche}");
            $this->error("Run 'php artisan db:seed --class=BlogVariationsTableSeeder' om variations toe te voegen");
            throw new \Exception("Geen blog variations beschikbaar voor niche: {$niche}");
        }

        $selectedVariations[] = $variation;

        return "Focus: {$variation->value}";
    }

    protected function generateBlogFromTemplate(
        BlogTemplate $template,
        ?int $productId,
        string $niche,
        string $toneOfVoice,
        string $targetAudience
    ): void {
        // Instantiate template variables
        $instantiated = $template->instantiate();

        $this->info("Generating blog from template: {$instantiated['title']}");

        // Get product if specified
        $product = $productId ? Product::find($productId) : null;

        // Get products for comparison table/top list
        $products = Product::where('rating_average', '>=', 4.0)
            ->whereNotNull('price')
            ->orderBy('rating_average', 'desc')
            ->orderBy('rating_count', 'desc')
            ->limit(10)
            ->get();

        if ($products->isEmpty()) {
            $this->warn('No suitable products found for blog content');
            return;
        }

        // Build content outline string
        $outlineText = collect($template->content_outline)
            ->map(function($heading, $index) use ($instantiated) {
                // Replace variables in headings
                $heading = str_replace(array_keys($instantiated), array_values($instantiated), $heading);
                return ($index + 1) . ". " . $heading;
            })
            ->implode("\n");

        // Generate blog content via OpenAI in V3 JSON format
        $locale = app()->getLocale();
        $isGerman = $locale === 'de';

        if ($isGerman) {
            $fullPrompt = <<<PROMPT
Du bist ein Experte für SEO-Content. Erstelle einen Blog-Artikel im JSON-Format gemäß dem V3-Schema für {$niche}.

TITEL: {$instantiated['title']}
SEO KEYWORD: {$instantiated['seo_keyword']}
ZIELGRUPPE: {$targetAudience}
TONALITÄT: {$toneOfVoice}
ZIELWORTANZAHL: {$template->target_word_count}

INHALTSSEKTIONEN (als Basis verwenden):
{$outlineText}

PRODUKTE:
PROMPT;
        } else {
            $fullPrompt = <<<PROMPT
Je bent een expert SEO content writer. Genereer een blog artikel in JSON format volgens het V3 schema voor {$niche}.

TITEL: {$instantiated['title']}
SEO KEYWORD: {$instantiated['seo_keyword']}
DOELGROEP: {$targetAudience}
TONE OF VOICE: {$toneOfVoice}
TARGET WOORDEN: {$template->target_word_count}

CONTENT SECTIES (gebruik deze als basis):
{$outlineText}

PRODUCTEN:
PROMPT;
        }

        foreach ($products->take(5) as $index => $prod) {
            $fullPrompt .= "\n" . ($index + 1) . ". {$prod->title} - €{$prod->price} - Rating: {$prod->rating_average}/5";
        }

        if ($isGerman) {
            $fullPrompt .= <<<PROMPT


WICHTIG: Dies ist ein ALLGEMEINER BLOG - kein Produktblog!
Dies ist ein ausführlicher, informativer Artikel, der die Website als Autorität in der Nische positioniert.

SCHREIBE ALS MENSCHLICHER EXPERTE - NICHT ALS KI:
PROMPT;
        } else {
            $fullPrompt .= <<<PROMPT


BELANGRIJK: Dit is een GENERAL BLOG - geen product blog!
Dit is een diepgaand, informatief artikel dat de site positioneert als autoriteit in de niche.

SCHRIJF ALS MENSELIJKE EXPERT - NIET ALS AI:
PROMPT;
        }

        if ($isGerman) {
            $fullPrompt .= <<<PROMPT
✅ Variiere Satzlänge (kurz, lang, mittel gemischt)
✅ Füge konkrete Details hinzu (Wattleistung, Abmessungen, Temperaturen, Dezibel)
✅ Gebe Meinungen und Nuancen ("Günstige Modelle sind oft lauter als Hersteller zugeben")
✅ Verwende Gegenargumente ("Manche Nutzer finden doppelte Körbe verschwenderisch")
✅ Spezifische Beispiele ("In Küchen unter 60cm Breite...")
✅ Variiere Struktur pro Blog (manchmal Bullets, manchmal lange Absätze, manchmal kurze Abschnitte)

❌ VERBOTENE KI-MUSTER (NIEMALS verwenden):
❌ "Bei so vieler Auswahl auf dem Markt kann es überwältigend sein"
❌ "Eine Checkliste hilft dir, dich auf das Wesentliche zu konzentrieren"
❌ "Das ist eine smarte Investition für Familien, die..."
❌ "Perfekt für Familien, die gesund kochen möchten"
❌ "Es ist wichtig zu wissen, dass..."
❌ "Entdecke die Unterschiede und wähle, was zu dir passt"
❌ Generische Intros ohne neue Info
❌ Zu neutraler Ton ohne Meinung
❌ Immer gleiche Struktur (variieren!)
❌ Vage Wörter: "oft", "manchmal", "meist", "in der Regel" (konkret sein!)
❌ Gefälschte Preise ohne Quelle ("im Juli für €80") - verwende KEINE spezifischen Preise!
❌ Experten-Zitate ohne Name/Quelle
❌ Lifestyle-Sprache: "Statement", "Eyecatcher", "Chic", "Elegantes Design"
❌ Interior-Design-Fokus - dies ist eine PRODUKTBERATUNGS-Seite, kein Wohnmagazin!
❌ Vage Schlussfolgerungen: "Die Wahl hängt ab von..." (gib HARTE Empfehlung!)

PFLICHT IN JEDEM BLOG:
1. Mindestens 3 konkrete Specs (z.B. "1500W", "5.5L Kapazität", "unter 50dB")
2. Mindestens 1 Meinungs-Hook (z.B. "Philips ist teurer aber leiser - für Wohnungen essentiell")
3. Mindestens 1 praktisches Szenario (z.B. "Für 2 Personen in 40m² Wohnung...")
4. Variiere Anzahl Sections: manchmal 3, manchmal 5, manchmal 6 (nicht immer 4-5!)
5. Variiere Absatzlängen: manchmal 2 Sätze, manchmal 6 Sätze
6. HARTE SCHLUSSFOLGERUNG mit spezifischer Empfehlung (nicht "hängt ab von", sondern "ich empfehle X weil Y")
7. KEINE spezifischen Preise (ok: "durchschnittlich 20-30% Rabatt", nie: "für €84,99")
8. Fokus auf FUNKTION und LEISTUNG, nicht auf Aussehen/Design/Lifestyle

KRITISCH: Verwende GENAU diesen Titel (nicht ändern!):
Titel: {$instantiated['title']}

JSON STRUKTUR (genau folgen):
{
  "version": "blog.v3",
  "locale": "de-DE",
  "author": "besteslaufband.de",
  "title": "{$instantiated['title']}",
  "standfirst": "Informativer Einstieg OHNE generische Sätze (2-3 Sätze mit konkretem Hook)",
  "sections": [
    {
      "type": "text",
      "heading": "H2 Überschrift (variiere Stil: manchmal Frage, manchmal Statement)",
      "paragraphs": ["Absatz mit KONKRETEN Details (Specs, Meinungen, Beispiele)", "Zweiter Absatz mit Nuance"]
    },
    {
      "type": "quote",
      "quote": {{"text": "Experten-Meinung mit konkreter Beobachtung (NICHT generisch!)"}}
    },
    {
      "type": "text",
      "heading": "Nächste H2",
      "paragraphs": ["Absatz 1", "Absatz 2", "Optional: Absatz 3 falls nötig"]
    }
  ],
  "product_context": {
    "name": "{$niche}",
    "why_relevant": "Konkreter Grund mit Specs/Verwendung"
  },
  "closing": {
    "headline": "Fazit",
    "summary": "Fazit mit konkreten Empfehlungen und Meinungen (NICHT generisch!)",
    "primary_cta": {
      "label": "Alle {$niche} ansehen",
      "url_key": "producten.index"
    }
  }
}

SCHREIBSTIL-VARIATION (zufällig wählen):
- Stil A: Kurze Sätze, direkter Ton, viele Bullets/Listen
- Stil B: Längere Absätze, tiefgehende Erklärung, technisch
- Stil C: Mix aus kurz/lang, praktischer Fokus, Szenarien
- Stil D: Frage-getrieben, interaktiv, persönlich

REGELN:
1. Ziel {$template->target_word_count} Wörter (erreiche das!)
2. Ton: {$toneOfVoice} aber mit MENSCHLICHER Variation (nicht roboterhaft konsistent!)
3. SEO keyword: {$instantiated['seo_keyword']} muss 3-4 mal vorkommen (natürlich!)
4. Bespreche 2-3 Produkte mit KONKRETEN Vergleichen (z.B. "Ninja ist 400g schwerer aber leiser")
5. Füge IMMER mindestens 3 konkrete Zahlen/Specs hinzu
6. Gebe IMMER mindestens 1 Meinung oder Gegenargument
7. Gib NUR minified JSON zurück, KEINE Markdown-Blöcke

🚫 LINKING REGELN (WICHTIG):
- Platziere NIEMALS Links in laufendem Text (paragraphs)
- Erwähne Produkte/Marken im Text, aber NICHT verlinken
- Einziger Link darf in "closing" > "primary_cta" (automatisch zu /producten)
- Kein internes Linking-System - CTA-Buttons machen das

Beginne JETZT:
PROMPT;
        } else {
            $fullPrompt .= <<<PROMPT
✅ Varieer zinslengte (kort, lang, medium door elkaar)
✅ Voeg concrete details toe (wattages, afmetingen, temperaturen, decibels)
✅ Geef meningen en nuance ("Goedkope modellen zijn vaak luider dan fabrikanten toegeven")
✅ Gebruik contra-argumenten ("Sommige gebruikers vinden dubbele manden juist verspilling")
✅ Specifieke voorbeelden ("In keukens onder 60cm breed...")
✅ Varieer structuur per blog (soms bullets, soms lange alinea's, soms korte secties)

❌ VERBODEN AI-PATRONEN (gebruik deze NOOIT):
❌ "Met zoveel keuze op de markt kan het overweldigend zijn"
❌ "Een checklist helpt je focussen op wat echt belangrijk is"
❌ "Dit is een slimme investering voor gezinnen die..."
❌ "Perfect voor gezinnen die gezond willen koken"
❌ "Het is belangrijk om te weten dat..."
❌ "Ontdek de verschillen en kies wat bij jou past"
❌ Generieke intro's die geen nieuwe info geven
❌ Te neutrale toon zonder mening
❌ Altijd dezelfde structuur (varieer!)
❌ Vage woorden: "vaak", "soms", "meestal", "doorgaans" (gebruik concreet!)
❌ Gefakte prijzen zonder bron ("in juli voor €80") - gebruik GEEN specifieke prijzen!
❌ Expert quotes zonder naam/bron
❌ Lifestyle taal: "statement", "eyecatcher", "chic", "strak design"
❌ Interior design focus - dit is een PRODUCT ADVIES site, geen woonblad!
❌ Vage conclusies: "De keuze hangt af van..." (geef HARDE aanbeveling!)

VERPLICHT IN ELKE BLOG:
1. Minimaal 3 concrete specs (bijv. "1500W", "5.5L capaciteit", "onder 50dB")
2. Minimaal 1 opinie-hook (bijv. "Philips is duurder maar stiller - voor appartementen cruciaal")
3. Minimaal 1 praktisch scenario (bijv. "Voor 2 personen in 40m² appartement...")
4. Varieer aantal sections: soms 3, soms 5, soms 6 (niet altijd 4-5!)
5. Varieer paragraaf lengtes: soms 2 zinnen, soms 6 zinnen
6. HARDE CONCLUSIE met specifieke aanbeveling (niet "hangt af van", wel "ik adviseer X omdat Y")
7. GEEN specifieke prijzen (wel: "gemiddeld 20-30% korting", nooit: "voor €84,99")
8. Focus op FUNCTIE en PRESTATIES, niet op looks/design/lifestyle

KRITISCH: Gebruik EXACT deze titel (niet aanpassen!):
Titel: {$instantiated['title']}

JSON STRUCTURE (volg exact):
{
  "version": "blog.v3",
  "locale": "nl-NL",
  "author": "airfryertest.nl",
  "title": "{$instantiated['title']}",
  "standfirst": "Informatieve opening ZONDER generieke zinnen (2-3 zinnen met concrete hook)",
  "sections": [
    {
      "type": "text",
      "heading": "H2 heading (varieer stijl: soms vraag, soms statement)",
      "paragraphs": ["Paragraaf met CONCRETE details (specs, meningen, voorbeelden)", "Tweede paragraaf met nuance"]
    },
    {
      "type": "quote",
      "quote": {{"text": "Expert mening met concrete observatie (NIET generiek!)"}}
    },
    {
      "type": "text",
      "heading": "Volgende H2",
      "paragraphs": ["Paragraaf 1", "Paragraaf 2", "Optioneel: paragraaf 3 als nodig"]
    }
  ],
  "product_context": {
    "name": "{$niche}",
    "why_relevant": "Concrete reden met specs/gebruik"
  },
  "closing": {
    "headline": "Conclusie",
    "summary": "Conclusie met concrete aanbevelingen en meningen (NIET generiek!)",
    "primary_cta": {
      "label": "Bekijk alle {$niche}",
      "url_key": "producten.index"
    }
  }
}

SCHRIJFSTIJL VARIATIE (kies random):
- Stijl A: Korte zinnen, directe toon, veel bullets/lijstjes
- Stijl B: Langere alinea's, diepgaande uitleg, technisch
- Stijl C: Mix van kort/lang, praktische focus, scenario's
- Stijl D: Vraag-gedreven, interactief, persoonlijk

REGELS:
1. Target {$template->target_word_count} woorden (haal dit!)
2. Tone: {$toneOfVoice} maar met MENSELIJKE variatie (niet robotachtig consistent!)
3. SEO keyword: {$instantiated['seo_keyword']} moet 3-4 keer voorkomen (natuurlijk!)
4. Bespreek 2-3 producten met CONCRETE vergelijkingen (bijv. "Ninja is 400g zwaarder maar stiller")
5. Voeg ALTIJD minstens 3 concrete cijfers/specs toe
6. Geef ALTIJD minstens 1 mening of contra-argument
7. Return ALLEEN minified JSON, GEEN markdown blokken

🚫 LINKING REGELS (BELANGRIJK):
- Plaats NOOIT links in lopende tekst (paragraphs)
- Vermeld producten/merken wel in tekst, maar NIET linken
- Enige link mag in "closing" > "primary_cta" (automatisch naar /producten)
- Geen intern linking systeem - CTA knoppen doen dat werk

Begin NU:
PROMPT;
        }

        $jsonResponse = $this->openAI->generateFromPrompt($fullPrompt, 'gpt-4o-mini');
        $jsonResponse = trim($jsonResponse);

        // Clean JSON response (remove markdown code blocks if present)
        $jsonResponse = preg_replace('/^```json\s*/', '', $jsonResponse);
        $jsonResponse = preg_replace('/\s*```$/', '', $jsonResponse);
        $jsonResponse = trim($jsonResponse);

        // Fix common JSON encoding issues
        // First, let's try to parse it - if it fails, try to fix newlines
        $testDecode = json_decode($jsonResponse, true);
        if (json_last_error() === JSON_ERROR_CTRL_CHAR) {
            // Replace literal newlines with escaped newlines in string values
            // This regex finds strings and escapes newlines within them
            $jsonResponse = preg_replace_callback('/"([^"]*)"/', function($matches) {
                return '"' . str_replace(["\r\n", "\r", "\n", "\t"], ['\\n', '\\n', '\\n', '\\t'], $matches[1]) . '"';
            }, $jsonResponse);
        }

        // Remove other control chars (but NOT \n and \t as they should be escaped now)
        $jsonResponse = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $jsonResponse);
        $jsonResponse = mb_convert_encoding($jsonResponse, 'UTF-8', 'UTF-8'); // Ensure UTF-8

        $blogContent = $jsonResponse;

        // Validate JSON
        $decoded = json_decode($blogContent, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($decoded)) {
            $this->error('Generated blog content is not valid JSON');
            $this->error('JSON Error: ' . json_last_error_msg());

            // Save to file for debugging
            $debugFile = storage_path('logs/failed_blog_json_' . time() . '.txt');
            file_put_contents($debugFile, $blogContent);
            $this->warn('Full response saved to: ' . $debugFile);
            $this->warn('Response preview: ' . substr($blogContent, 0, 300));
            return;
        }

        if (empty($decoded['version']) || $decoded['version'] !== 'blog.v3') {
            $this->warn('JSON does not have blog.v3 version, but continuing...');
        }

        // Generate slug from instantiated slug template
        $baseSlug = Str::slug($instantiated['slug']);
        $slug = $baseSlug;
        $counter = 1;

        while (BlogPost::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        // Generate meta description
        if ($isGerman) {
            $metaPrompt = <<<PROMPT
Schreibe eine perfekte Meta-Description (max. 155 Zeichen) für diesen Blog-Artikel:

Titel: {$instantiated['title']}
SEO Keyword: {$instantiated['seo_keyword']}

Regeln:
- Maximal 155 Zeichen
- Enthält das SEO-Keyword
- Ansprechend und klickbar
- Keine Anführungszeichen oder Sonderzeichen
- Deutsch

Gib NUR die Meta-Description zurück, nichts anderes:
PROMPT;
        } else {
            $metaPrompt = <<<PROMPT
Schrijf een perfecte meta description (max 155 karakters) voor dit blog artikel:

Titel: {$instantiated['title']}
SEO Keyword: {$instantiated['seo_keyword']}

Regels:
- Maximaal 155 karakters
- Bevat het SEO keyword
- Prikkelend en klikbaar
- Geen quotes of rare tekens
- Nederlands

Geef ALLEEN de meta description, niets anders:
PROMPT;
        }

        $metaDescription = $this->openAI->generateFromPrompt($metaPrompt, 'gpt-4o-mini');
        $metaDescription = trim($metaDescription, '"\'');

        if (mb_strlen($metaDescription) > 155) {
            $metaDescription = mb_substr($metaDescription, 0, 152) . '...';
        }

        // Determine if this is a product blog or general blog
        // Product blogs: when product was explicitly requested via command argument
        // General blogs: template-generated blogs about topics/features (NO product_id!)
        $isProductBlog = $product !== null;

        $blog = BlogPost::create([
            'title' => $instantiated['title'],
            'slug' => $slug,
            'content' => $blogContent,
            'meta_description' => $metaDescription,
            'status' => 'published',
            'product_id' => $isProductBlog ? $product->id : null, // NULL for general blogs
            'team_member_id' => $this->getRandomTeamMemberId(),
        ]);

        // Mark template as used
        $template->markAsUsed();

        $this->info("Blog post created successfully!");
        $this->info("ID: {$blog->id}");
        $this->info("Title: {$blog->title}");
        $this->info("Slug: {$blog->slug}");
        $this->info("Word count: " . str_word_count(strip_tags($blogContent)));
        $this->info("Template marked as used (reusable after 60 days)");
    }
}
