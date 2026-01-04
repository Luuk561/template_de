<?php

namespace App\Console\Commands\Blog;

use App\Models\BlogPost;
use App\Models\Product;
use App\Models\ProductBlogTemplate;
use App\Services\OpenAIService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class GenerateProductBlog extends Command
{
    protected $signature = 'app:generate-product-blog {product_id?}';

    protected $description = 'Generate a product-focused blog for a specific product using ProductBlogTemplate (auto-selects popular product if no ID provided)';

    protected OpenAIService $openAI;

    public function __construct(OpenAIService $openAI)
    {
        parent::__construct();
        $this->openAI = $openAI;
    }

    public function handle()
    {
        $productId = $this->argument('product_id');

        if ($productId) {
            $product = Product::find($productId);
            if (!$product) {
                $this->error("Product with ID {$productId} not found.");
                Log::error("Product not found", ['product_id' => $productId]);
                return SymfonyCommand::FAILURE;
            }
        } else {
            // Auto-select: Pick most popular product without product blog
            // Product blogs are those created after template system launch (2025-11-05)
            $product = Product::whereDoesntHave('blogPosts', function ($query) {
                    $query->where('created_at', '>', '2025-11-05');
                })
                ->where('rating_average', '>=', 4.0) // Only good products
                ->whereNotNull('price')
                ->orderByDesc('rating_count') // Most reviews = most popular
                ->orderByDesc('rating_average')
                ->first();

            if (!$product) {
                // Fallback: any product without product blog
                $product = Product::whereDoesntHave('blogPosts', function ($query) {
                    $query->where('created_at', '>', '2025-11-05');
                })->first();
            }

            if (!$product) {
                $this->warn('No products without product blog found.');
                Log::warning('No products without product blog found at ' . now());
                return SymfonyCommand::SUCCESS;
            }

            $this->info("Auto-selected popular product: {$product->title} (rating: {$product->rating_average}, reviews: {$product->rating_count})");
        }

        $this->info("Generating product blog for: {$product->title}");

        // Get site niche
        $niche = \App\Models\Setting::where('key', 'site_niche')->value('value');
        if (!$niche) {
            $this->error("site_niche setting not found. Run php artisan site:generate first.");
            return SymfonyCommand::FAILURE;
        }

        // Pick a product blog template
        $template = ProductBlogTemplate::pickTemplate($niche);
        if (!$template) {
            $this->error("No product blog templates found for niche: {$niche}");
            $this->warn("Run: php artisan site:generate-product-blog-templates");
            return SymfonyCommand::FAILURE;
        }

        $this->info("Using template: {$template->title_template}");

        // Instantiate template with product name
        $instantiated = $template->instantiate($product->title);

        // Check if blog with similar title already exists
        $slug = Str::slug($instantiated['title']) . '-' . Str::random(4);
        $existingBlog = BlogPost::where('slug', 'like', Str::slug($instantiated['title']) . '%')
            ->where('product_id', $product->id)
            ->first();

        if ($existingBlog) {
            $this->warn("Blog already exists for this product with similar title: {$existingBlog->title}");
            $this->info("Skipping duplicate generation.");
            return SymfonyCommand::SUCCESS;
        }

        // Generate blog content via OpenAI
        $this->info("Generating blog content via OpenAI (this may take 30-60 seconds)...");

        // Retry logic: 3 attempts with exponential backoff
        $maxAttempts = 3;
        $blogContent = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                if ($attempt > 1) {
                    $this->warn("Retry attempt {$attempt}/{$maxAttempts}...");
                    sleep($attempt); // 1s, 2s backoff
                }

                $blogContent = $this->generateProductBlogContent($product, $template, $instantiated);

                // Validate we got valid content
                if ($blogContent && strlen($blogContent) > 100) {
                    break; // Success!
                }
            } catch (\Exception $e) {
                if ($attempt === $maxAttempts) {
                    $this->error("Failed after {$maxAttempts} attempts: " . $e->getMessage());
                    Log::error("Product blog generation failed", [
                        'product_id' => $product->id,
                        'template_id' => $template->id,
                        'attempts' => $maxAttempts,
                        'error' => $e->getMessage()
                    ]);
                    return SymfonyCommand::FAILURE;
                }
                $this->warn("Attempt {$attempt} failed: " . $e->getMessage());
            }
        }

        // Additional cleaning before validation (same as general blog generation)
        $blogContent = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $blogContent);

        // Validate JSON
        $json = json_decode($blogContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Last resort cleaning
            $blogContent = preg_replace('/[\x00-\x1F\x7F]/u', '', $blogContent);
            $json = json_decode($blogContent, true);
        }

        if (!$json || !isset($json['version']) || $json['version'] !== 'blog.v3') {
            $this->error("Invalid JSON generated (not V3 format)");

            // Debug: save raw response to file
            $debugFile = storage_path('logs/product_blog_debug_' . time() . '.json');
            file_put_contents($debugFile, $blogContent);
            $this->warn("Raw response saved to: {$debugFile}");

            Log::error("Invalid product blog JSON", [
                'json_error' => json_last_error_msg(),
                'content_preview' => substr($blogContent, 0, 200),
                'debug_file' => $debugFile
            ]);
            return SymfonyCommand::FAILURE;
        }

        // Generate SEO meta
        $metaTitle = Str::limit($instantiated['title'], 60, '');

        $locale = app()->getLocale();
        $isGerman = $locale === 'de';

        if ($isGerman) {
            $defaultMetaDescription = "Entdecke, wie du {$product->title} optimal nutzt. Praktische Tipps, Erfahrungen und Ratschläge für das beste Ergebnis.";
        } else {
            $defaultMetaDescription = "Ontdek hoe je {$product->title} optimaal gebruikt. Praktische tips, ervaringen en advies voor het beste resultaat.";
        }

        $metaDescription = Str::limit($defaultMetaDescription, 155, '');

        // Save blog post
        try {
            $blog = BlogPost::create([
                'title' => $instantiated['title'],
                'slug' => $slug,
                'content' => $blogContent,
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
                'status' => 'published',
                'product_id' => $product->id, // CRITICAL: This is a product blog
                'excerpt' => Str::limit($json['standfirst'] ?? ($isGerman ? "Blog über {$product->title}" : "Blog over {$product->title}"), 200),
            ]);

            // Mark template as used
            $template->markAsUsed();

            $this->info("Product blog created successfully!");
            $this->info("Title: {$blog->title}");
            $this->info("URL: /blogs/{$blog->slug}");
            $this->newLine();

            Log::info("Product blog generated successfully", [
                'blog_id' => $blog->id,
                'product_id' => $product->id,
                'template_id' => $template->id,
                'title' => $blog->title
            ]);

            return SymfonyCommand::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to save blog: " . $e->getMessage());
            Log::error("Product blog save failed", [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
            return SymfonyCommand::FAILURE;
        }
    }

    protected function generateProductBlogContent(Product $product, ProductBlogTemplate $template, array $instantiated): string
    {
        $siteName = \App\Models\Setting::where('key', 'site_name')->value('value') ?? 'onze site';
        $niche = \App\Models\Setting::where('key', 'site_niche')->value('value') ?? 'producten';

        // Decode content outline (can be JSON string or already decoded array)
        if (is_string($template->content_outline)) {
            // It's a JSON string, decode it
            $outline = json_decode($template->content_outline, true) ?? [];
        } elseif (is_array($template->content_outline)) {
            // Already an array, use it directly
            $outline = $template->content_outline;
        } else {
            // Invalid or null, default to empty array
            $outline = [];
        }

        $outlineText = '';
        if (is_array($outline)) {
            foreach ($outline as $section) {
                if (is_array($section)) {
                    $outlineText .= "- " . ($section['heading'] ?? $section['title'] ?? 'Section') . "\n";
                    if (isset($section['subheadings']) && is_array($section['subheadings'])) {
                        foreach ($section['subheadings'] as $sub) {
                            $outlineText .= "  - " . (is_string($sub) ? $sub : ($sub['title'] ?? 'Subsection')) . "\n";
                        }
                    }
                } else {
                    $outlineText .= "- " . $section . "\n";
                }
            }
        }

        // Build storytelling prompt based on template tone and scenario
        $toneGuidance = $this->getToneGuidance($template->tone);
        $scenarioGuidance = $this->getScenarioGuidance($template->scenario_focus);

        $locale = app()->getLocale();
        $isGerman = $locale === 'de';

        if ($isGerman) {
            $fullPrompt = <<<PROMPT
SYSTEM: Du bist ein Experte für SEO-Content, spezialisiert auf Produkt-Blogs mit Storytelling-Elementen. Du schreibst auf Deutsch.

AUFGABE: Schreibe einen PRODUKT-BLOG über "{$product->title}" für {$siteName}, eine {$niche} Website.

WICHTIG: Dies ist ein PRODUKT-BLOG - kein allgemeiner Blog!
Ein Produkt-Blog geht SPEZIFISCH darüber, wie du DIESES PRODUKT verwendest, optimierst und Wert daraus ziehst.

PRODUKT-BLOG MERKMALE:
- Ton: {$toneGuidance}
- Fokus: {$scenarioGuidance}
- Storytelling: Verwende "Stell dir vor..." / "Entdecke wie..." / "Transformiere dein..."
- Praktisch: Konkrete Tipps und Ratschläge für DIESES spezifische Produkt
- Erlebnis: Wie macht {$product->title} das Leben einfacher/angenehmer/effizienter?

PRODUKT INFORMATIONEN:
Name: {$product->title}
Marke: {$product->brand}
Beschreibung: {$product->description}
Preis: €{$product->price}

CONTENT OUTLINE (folge dieser Struktur):
{$outlineText}

ZIEL:
- Wortanzahl: {$template->target_word_count} Wörter
- SEO Fokus-Keyword: {$instantiated['seo_keyword']}

KRITISCH: Verwende GENAU diesen Titel (nicht ändern, kein Jahr ändern!):
Titel: {$instantiated['title']}

KRITISCH: Folge GENAU dieser JSON-Struktur. Jedes section-Objekt muss auf derselben Ebene im sections-Array stehen!

KORREKTE STRUKTUR (ACHTUNG: quote ist SEPARATES section, nicht innerhalb von text!):
{
  "version": "blog.v3",
  "locale": "de-DE",
  "author": "{$siteName}",
  "title": "{$instantiated['title']}",
  "standfirst": "Eine packende Eröffnung (2-3 Sätze)",
  "sections": [
    {
      "type": "text",
      "heading": "Erste H2 Überschrift",
      "paragraphs": ["Absatz 1 Text hier", "Absatz 2 Text hier"]
    },
    {
      "type": "quote",
      "quote": {"text": "Zitat-Text hier"}
    },
    {
      "type": "text",
      "heading": "Zweite H2 Überschrift",
      "paragraphs": ["Absatz 1 Text hier", "Absatz 2 Text hier"]
    },
    {
      "type": "text",
      "heading": "Dritte H2 Überschrift",
      "paragraphs": ["Absatz 1 Text hier", "Absatz 2 Text hier"]
    }
  ],
  "product_context": {
    "name": "{$product->title}",
    "brand": "{$product->brand}",
    "why_relevant": "Warum relevant (1 Satz)"
  },
  "closing": {
    "headline": "Fazit",
    "summary": "Zusammenfassendes Fazit (3-4 Sätze)",
    "primary_cta": {
      "label": "{$product->title} ansehen",
      "url_key": "producten.show",
      "url_params": {"slug": "{$product->slug}"}
    }
  }
}

FEHLER - NIEMALS TUN:
{
  "sections": [
    {
      "type": "text",
      "paragraphs": ["..."],
      "type": "quote"  <-- FEHLER! Quote muss separate section sein!
    }
  ]
}

REGELN:
1. Erstelle 3-4 text sections basierend auf outline (NICHT mehr - halte es kompakt!)
2. Füge 1 quote section hinzu
3. Jede text section: 2 Absätze mit 3-4 Sätzen (kurz und bündig!)
4. Verwende "dieses {$niche}", "das Gerät", "{$product->brand} Modell" für Abwechslung - NICHT ständig den vollen Produktnamen wiederholen
5. Verwende Storytelling und praktische Beispiele
6. Insgesamt ~1000 Wörter (NICHT mehr, halte es kompakt!)
7. Verwende KEINE Tables/Markdown - nur Text
8. Halte es praktisch, inspirierend und umsetzbar
9. Gib NUR minified JSON zurück, KEINE Markdown-Blöcke

Beginne JETZT:
PROMPT;
        } else {
            $fullPrompt = <<<PROMPT
SYSTEM: Je bent een expert SEO content writer gespecialiseerd in product blogs met storytelling elementen. Je schrijft in het Nederlands.

OPDRACHT: Schrijf een PRODUCT BLOG over "{$product->title}" voor {$siteName}, een {$niche} website.

BELANGRIJK: Dit is een PRODUCT BLOG - geen algemene blog!
Een productblog gaat SPECIFIEK over hoe je DIT PRODUCT gebruikt, optimaliseert, en waarde uit haalt.

PRODUCT BLOG KENMERKEN:
- Tone: {$toneGuidance}
- Focus: {$scenarioGuidance}
- Storytelling: Gebruik "Stel je voor..." / "Ontdek hoe..." / "Transformeer je..."
- Praktisch: Concrete tips en adviezen voor DIT specifieke product
- Beleving: Hoe maakt {$product->title} het leven makkelijker/leuker/efficiënter?

PRODUCT INFORMATIE:
Naam: {$product->title}
Merk: {$product->brand}
Beschrijving: {$product->description}
Prijs: €{$product->price}

CONTENT OUTLINE (volg deze structuur):
{$outlineText}

TARGET:
- Woordenaantal: {$template->target_word_count} woorden
- SEO focus keyword: {$instantiated['seo_keyword']}

KRITISCH: Gebruik EXACT deze titel (niet aanpassen, geen jaartal veranderen!):
Titel: {$instantiated['title']}

KRITISCH: Volg EXACT deze JSON structuur. Elk section object moet op hetzelfde niveau staan in de sections array!

CORRECTE STRUCTURE (LET OP: quote is APART section, niet binnen text!):
{
  "version": "blog.v3",
  "locale": "de-DE",
  "author": "{$siteName}",
  "title": "{$instantiated['title']}",
  "standfirst": "Een pakkende opening (2-3 zinnen)",
  "sections": [
    {
      "type": "text",
      "heading": "Eerste H2 heading",
      "paragraphs": ["Paragraaf 1 tekst hier", "Paragraaf 2 tekst hier"]
    },
    {
      "type": "quote",
      "quote": {"text": "Quote tekst hier"}
    },
    {
      "type": "text",
      "heading": "Tweede H2 heading",
      "paragraphs": ["Paragraaf 1 tekst hier", "Paragraaf 2 tekst hier"]
    },
    {
      "type": "text",
      "heading": "Derde H2 heading",
      "paragraphs": ["Paragraaf 1 tekst hier", "Paragraaf 2 tekst hier"]
    }
  ],
  "product_context": {
    "name": "{$product->title}",
    "brand": "{$product->brand}",
    "why_relevant": "Waarom relevant (1 zin)"
  },
  "closing": {
    "headline": "Conclusie",
    "summary": "Samenvattende conclusie (3-4 zinnen)",
    "primary_cta": {
      "label": "Bekijk {$product->title}",
      "url_key": "producten.show",
      "url_params": {"slug": "{$product->slug}"}
    }
  }
}

FOUT - NOOIT DOEN:
{
  "sections": [
    {
      "type": "text",
      "paragraphs": ["..."],
      "type": "quote"  <-- FOUT! Quote moet apart section zijn!
    }
  ]
}

REGELS:
1. Maak 3-4 text sections gebaseerd op outline (NIET meer - keep it compact!)
2. Voeg 1 quote section toe
3. Elk text section: 2 paragraphs van 3-4 zinnen (kort en bondig!)
4. Gebruik "deze {$niche}", "het apparaat", "{$product->brand} model" voor variatie - NIET steeds de volledige productnaam herhalen
5. Gebruik storytelling en praktische voorbeelden
6. Totaal ~1000 woorden (NIET meer, keep it compact!)
7. Gebruik GEEN tables/markdown - alleen tekst
8. Houd het praktisch, inspirerend en actionable
9. Return ALLEEN minified JSON, GEEN markdown blokken

Begin NU:
PROMPT;
        }

        $response = $this->openAI->generateFromPrompt($fullPrompt, 'gpt-4o-mini');

        // Clean response (remove markdown code blocks if present)
        $response = trim($response);
        $response = preg_replace('/^```json\s*/m', '', $response);
        $response = preg_replace('/\s*```$/m', '', $response);
        $response = trim($response);

        // Fix common JSON encoding issues - same as general blog generation
        $testDecode = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Try removing control characters except newlines and tabs
            $response = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $response);

            // Try again
            $testDecode = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Last resort: remove ALL control characters
                $response = preg_replace('/[\x00-\x1F\x7F]/u', '', $response);
            }
        }

        return $response;
    }

    protected function getToneGuidance(string $tone): string
    {
        $locale = app()->getLocale();
        $isGerman = $locale === 'de';

        if ($isGerman) {
            return match($tone) {
                'inspirational' => 'Inspirierend und motivierend - begeistere den Leser für die Möglichkeiten',
                'practical' => 'Praktisch und hands-on - konkrete Tipps und Anleitungen',
                'storytelling' => 'Erzählend und fesselnd - verwende Szenarien und Beispiele aus dem echten Leben',
                'problem_solving' => 'Problemlösend - fokussiere darauf, wie dieses Produkt spezifische Herausforderungen angeht',
                default => 'Informativ und hilfreich mit einer Prise Begeisterung',
            };
        } else {
            return match($tone) {
                'inspirational' => 'Inspirerend en motiverend - maak de lezer enthousiast over de mogelijkheden',
                'practical' => 'Praktisch en hands-on - concrete tips en hoe-te-doen instructies',
                'storytelling' => 'Verhalend en meeslepend - gebruik scenario\'s en voorbeelden uit het echte leven',
                'problem_solving' => 'Probleemoplossend - focus op hoe dit product specifieke uitdagingen aanpakt',
                default => 'Informatief en behulpzaam met een vleugje enthousiasme',
            };
        }
    }

    protected function getScenarioGuidance(string $scenario): string
    {
        $locale = app()->getLocale();
        $isGerman = $locale === 'de';

        if ($isGerman) {
            return match($scenario) {
                'how_to' => 'Erkläre Schritt für Schritt, wie du das Produkt optimal nutzt',
                'benefits' => 'Betone die konkreten Vorteile und was das Produkt besonders macht',
                'mistakes' => 'Weise auf häufige Fehler hin und wie du sie vermeidest',
                'use_cases' => 'Beschreibe spezifische Nutzungsszenarien und Anwendungen',
                'comparison' => 'Vergleiche mit Alternativen und zeige, warum dieses Produkt besser ist',
                default => 'Fokussiere auf praktische Anwendungen und Nutzererfahrungen',
            };
        } else {
            return match($scenario) {
                'how_to' => 'Leg stap-voor-stap uit hoe je het product optimaal gebruikt',
                'benefits' => 'Benadruk de concrete voordelen en wat het product bijzonder maakt',
                'mistakes' => 'Wijs op veelgemaakte fouten en hoe je die vermijdt',
                'use_cases' => 'Beschrijf specifieke gebruik scenario\'s en toepassingen',
                'comparison' => 'Vergelijk met alternatieven en laat zien waarom dit product beter is',
                default => 'Focus op praktische toepassingen en gebruikservaringen',
            };
        }
    }
}
