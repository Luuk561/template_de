<?php

namespace App\Console\Commands\Blog;

use App\Models\BlogPost;
use App\Services\OpenAIService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateCustomProductBlog extends Command
{
    protected $signature = 'generate:custom-blog
                            {--content= : Product content (description, specs, features)}
                            {--product-name= : Product name}
                            {--affiliate= : Affiliate link URL}
                            {--discount-code= : Optional discount code}
                            {--discount-percentage=10 : Discount percentage (default: 10)}
                            {--discount-text= : Optional custom discount text}
                            {--angle= : Content angle (review, comparison, why-buy, use-cases)}';

    protected $description = 'Genereer een custom product blog (bijv. Moovv) met eigen affiliate link en kortingscode';

    protected OpenAIService $openAI;

    public function __construct(OpenAIService $openAI)
    {
        parent::__construct();
        $this->openAI = $openAI;
    }

    public function handle()
    {
        $content = $this->option('content');
        $productName = $this->option('product-name');
        $affiliateLink = $this->option('affiliate');
        $discountCode = $this->option('discount-code');
        $discountPercentage = (int) $this->option('discount-percentage');
        $discountText = $this->option('discount-text');
        $angle = $this->option('angle') ?: 'review';

        // Validation
        if (!$content || !$productName || !$affiliateLink) {
            $this->error('Verplichte parameters ontbreken!');
            $this->info('Gebruik: php artisan generate:custom-blog --content="..." --product-name="..." --affiliate="..."');
            return 1;
        }

        $this->info("Genereren van blog voor: {$productName}");
        $this->info('Content lengte: ' . strlen($content) . ' karakters');
        $this->info("Invalshoek: {$angle}");
        if ($discountCode) {
            $this->info("Kortingscode: {$discountCode} ({$discountPercentage}%)");
        }

        // Get site settings
        $niche = trim(getSetting('site_niche', 'loopbanden'));
        $toneOfVoice = trim(getSetting('tone_of_voice', 'professioneel en toegankelijk'));
        $targetAudience = trim(getSetting('target_audience', 'Nederlandse consumenten'));
        $siteName = trim(getSetting('site_name', 'loopbandentest.nl'));

        // Build custom discount block if provided
        $discountBlock = '';
        if ($discountCode) {
            if ($discountText) {
                $discountBlock = $discountText;
            } else {
                // Dynamic default text based on site name and niche
                $productType = str_contains(strtolower($niche), 'loopband') ? 'loopband' : 'product';
                $discountBlock = "{$discountPercentage}% korting voor lezers van {$siteName}\nBen je enthousiast geworden over dit {$productType}? We mogen {$discountPercentage}% korting geven. Gebruik hiervoor de code {$discountCode} bij het afrekenen.";
            }
        }

        // Generate blog via OpenAI
        $prompt = $this->buildPrompt($content, $productName, $niche, $toneOfVoice, $targetAudience, $discountBlock, $angle);

        $this->info('OpenAI generatie gestart...');
        $jsonResponse = $this->openAI->generateFromPrompt($prompt, 'gpt-4o-mini');

        // Clean JSON response (remove markdown code blocks if present)
        $jsonResponse = trim($jsonResponse);
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

        // Validate JSON
        $decoded = json_decode($jsonResponse, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($decoded)) {
            $this->error('Generated content is not valid JSON');
            $this->error('JSON Error: ' . json_last_error_msg());

            $debugFile = storage_path('logs/failed_custom_blog_' . time() . '.txt');
            file_put_contents($debugFile, $jsonResponse);
            $this->warn('Full response saved to: ' . $debugFile);
            return 1;
        }

        if (empty($decoded['title']) || empty($decoded['sections'])) {
            $this->error('JSON missing required fields (title or sections)');
            return 1;
        }

        $title = $decoded['title'];
        $this->info("Titel: {$title}");

        // Check for duplicate titles
        if (BlogPost::where('title', $title)->exists()) {
            $this->warn("Titel '{$title}' bestaat al.");
            $title .= ' ' . date('Y');
        }

        // Generate slug
        $baseSlug = Str::slug($title);
        $slug = $baseSlug;
        $counter = 1;

        while (BlogPost::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        // Generate meta description
        $metaPrompt = <<<PROMPT
Schrijf een perfecte meta description (max 155 karakters) voor dit blog artikel:

Titel: {$title}
Product: {$productName}

Regels:
- Maximaal 155 karakters
- Prikkelend en klikbaar
- Geen quotes of rare tekens
- Nederlands

Geef ALLEEN de meta description, niets anders:
PROMPT;

        $metaDescription = $this->openAI->generateFromPrompt($metaPrompt, 'gpt-4o-mini');
        $metaDescription = trim($metaDescription, '"\'');

        if (mb_strlen($metaDescription) > 155) {
            $metaDescription = mb_substr($metaDescription, 0, 152) . '...';
        }

        // Store custom affiliate data in JSON
        $decoded['custom_affiliate'] = [
            'link' => $affiliateLink,
            'product_name' => $productName,
        ];

        if ($discountCode) {
            $decoded['custom_affiliate']['discount_code'] = $discountCode;
            $decoded['custom_affiliate']['discount_percentage'] = $discountPercentage;
            $decoded['custom_affiliate']['discount_block'] = $discountBlock;
        }

        // Save blog post
        $blogPost = BlogPost::create([
            'title' => $title,
            'slug' => $slug,
            'content' => json_encode($decoded, JSON_UNESCAPED_UNICODE),
            'meta_description' => $metaDescription,
            'meta_title' => $title,
            'excerpt' => Str::limit($title, 150),
            'status' => 'published',
            'type' => 'product', // Use 'product' type for custom affiliate blogs
            'product_id' => null, // No bol.com product (custom affiliate instead)
        ]);

        $this->info('');
        $this->info('Blog succesvol aangemaakt!');
        $this->info("ID: {$blogPost->id}");
        $this->info("Slug: {$blogPost->slug}");
        $this->info("URL: " . route('blogs.show', $blogPost->slug));
        $this->info("Woorden: " . str_word_count(strip_tags($jsonResponse)));

        return 0;
    }

    protected function buildPrompt(
        string $content,
        string $productName,
        string $niche,
        string $toneOfVoice,
        string $targetAudience,
        string $discountBlock,
        string $angle
    ): string {
        $wordCount = rand(1000, 2000);

        // Define angle-specific instructions
        $angleInstructions = match($angle) {
            'comparison' => <<<ANGLE
INVALSHOEK: VERGELIJKING
Focus op waarom dit product zich onderscheidt van alternatieven:
- Vergelijk met generieke {$niche} op de markt
- Belicht unieke features en voordelen
- "Waarom deze loopband beter is dan..." perspectief
- Bespreek prijs/kwaliteit verhouding
- Sectie: "Hoe verhoudt dit zich tot andere opties?"
ANGLE,
            'why-buy' => <<<ANGLE
INVALSHOEK: WAAROM KOPEN
Focus op de redenen en voordelen van aanschaf:
- Welke problemen lost het op?
- Voor wie is dit DE oplossing?
- Langetermijn waarde en ROI
- Lifestyle benefits (gezondheid, productiviteit)
- "Waarom je dit product ECHT nodig hebt" perspectief
- Sectie: "5 redenen om voor deze loopband te kiezen"
ANGLE,
            'use-cases' => <<<ANGLE
INVALSHOEK: GEBRUIKSSITUATIES
Focus op praktische toepassingen en scenario's:
- Verschillende gebruikssituaties (thuiswerk, fitness, revalidatie)
- Dag-in-het-leven voorbeelden
- Doelgroep specifieke use cases
- Tips voor optimaal gebruik per scenario
- "Hoe gebruik je dit product in jouw situatie?" perspectief
- Sectie: "Gebruiksscenario's: van kantoor tot sportschool"
ANGLE,
            default => <<<ANGLE
INVALSHOEK: UITGEBREIDE REVIEW
Dit is een klassieke, uitgebreide product review:
- Eerste indruk en unboxing ervaring
- Diepgaande analyse van specificaties
- Hands-on gebruikservaring
- Voor- en nadelen objectief gewogen
- Conclusie en aanbeveling
ANGLE,
        };

        return <<<PROMPT
Je bent een expert SEO content writer. Genereer een uitgebreide product blog in JSON format (V3 schema).

PRODUCT INFORMATIE:
{$content}

PRODUCT NAAM: {$productName}
NICHE: {$niche}
DOELGROEP: {$targetAudience}
TONE OF VOICE: {$toneOfVoice}
DOEL WOORDENAANTAL: {$wordCount} woorden

{$angleInstructions}

BELANGRIJK: Dit is een CUSTOM PRODUCT BLOG - geen bol.com affiliate!
Dit is een diepgaand, informatief artikel over een specifiek product.

KENMERKEN:
- Tone: deskundig, eerlijk, objectief
- Diepgang: uitgebreide analyse afgestemd op de invalshoek
- Praktisch: concrete informatie en aanbevelingen
- SEO: natuurlijke keyword integratie
- Structuur: logische opbouw met duidelijke secties

VERPLICHTE JSON STRUCTUUR (blog.v3):
{
  "version": "blog.v3",
  "locale": "nl-NL",
  "author": "loopbandentest.nl",
  "title": "Pakkende titel afgestemd op de INVALSHOEK",
  "standfirst": "Korte, pakkende opening (2-3 zinnen) die de INVALSHOEK introduceert",
  "sections": [
    {
      "type": "text",
      "heading": "H2 heading die past bij de INVALSHOEK (NIET generiek!)",
      "paragraphs": ["Paragraaf 1 (4-5 zinnen)", "Paragraaf 2 (4-5 zinnen)"]
    },
    {
      "type": "text",
      "heading": "Tweede H2 afgestemd op INVALSHOEK",
      "paragraphs": ["Bespreek relevante aspecten", "Extra paragraaf"]
    },
    {
      "type": "quote",
      "quote": {"text": "Quote die de INVALSHOEK versterkt"}
    },
    {
      "type": "text",
      "heading": "Derde H2 specifiek voor deze INVALSHOEK",
      "paragraphs": ["Praktische informatie", "Verdieping"]
    },
    {
      "type": "text",
      "heading": "Vierde H2 die INVALSHOEK ondersteunt",
      "paragraphs": ["Belangrijke details", "Extra context"]
    },
    {
      "type": "text",
      "heading": "Vijfde H2 afsluitend voor INVALSHOEK",
      "paragraphs": ["Samenvattende informatie", "Praktische tips"]
    }
  ],
  "product_context": {
    "name": "{$productName}",
    "why_relevant": "Waarom dit product relevant is (1 zin)"
  },
  "closing": {
    "headline": "Conclusie",
    "summary": "Samenvattende conclusie met aanbeveling (3-4 zinnen)",
    "primary_cta": {
      "label": "Bekijk {$productName}",
      "url_key": "custom_affiliate"
    }
  }
}

KRITISCHE REGELS:
1. Maak 5-6 text sections die PERFECT passen bij de INVALSHOEK
2. H2 headings MOETEN uniek en specifiek zijn voor de gekozen invalshoek
3. Voeg 1 quote section toe met kernpunt
4. Elke text section: 2-3 paragrafen van 4-5 zinnen
5. Totaal {$wordCount} woorden (informatief en compleet!)
6. Tone: {$toneOfVoice}
7. Gebruik GEEN tables/markdown - alleen tekst
8. Return ALLEEN minified JSON, GEEN markdown blokken
9. Titel en headings moeten de INVALSHOEK weerspiegelen

BEGIN NU met het genereren van de JSON:
PROMPT;
    }
}
