<?php

namespace App\Services;

use Illuminate\Support\Str;
use OpenAI;
use Throwable;

class OpenAIService
{
    protected $client;
    protected OpenAICircuitBreaker $circuitBreaker;

    public function __construct(OpenAICircuitBreaker $circuitBreaker = null)
    {
        $this->client = OpenAI::client(config('services.openai.key'));
        $this->circuitBreaker = $circuitBreaker ?? new OpenAICircuitBreaker();
    }

    /**
     * Genereer een gestructureerde HTML-blog via vaste prompt (SEO geoptimaliseerd).
     * Optioneel: geef een ander model mee.
     *
     * Note: Gebruikt gpt-4o-mini voor kostenefficiëntie
     */
    public function generateProductBlog(string $title, string $description, string $brand, string $model = 'gpt-4o-mini'): string
    {
        // Get site context for universal prompt
        $siteNiche = getSetting('site_niche', env('APP_NAME', 'Premium Products'));
        $siteName = getSetting('site_name', config('app.name', 'Website'));
        $blogType = !empty($brand) && !empty($description) ? 'product' : 'general';

        $prompt = <<<PROMPT
Je bent een Nederlandse SEO content specialist die premium long-form artikelen schrijft die PERFECT ranken in Google. Output ALLEEN minified JSON volgens exact schema hieronder.

CONTENT FLOW MASTERCLASS:
1. Elk artikel moet een natuurlijke VERHAALBOOG hebben - van probleem naar oplossing
2. Alinea's MOETEN logisch op elkaar aansluiten - geen losstaande blokken tekst
3. Gebruik overgangszinnen tussen secties: "Nu we dit weten...", "Het volgende aspect...", "Daarom is het belangrijk..."
4. Elke H2 sectie bouwt voort op de vorige - geen willekeurige volgorde

SEO MASTERCLASS (ECHTE OPTIMALISATIE):
- PRIMARY KEYWORD in H1 + 2-3x in content (niet meer!) - NATUURLIJK weven
- SECONDARY KEYWORDS in H2s - elke H2 target een gerelateerd zoekwoord
- LONG-TAIL KEYWORDS in H3s - specifieke zoektermen die mensen typen
- LSI KEYWORDS (semantisch gerelateerd) door hele artikel heen
- SEARCH INTENT: Focus op WAAROM iemand dit zoekt - beantwoord hun echte vraag
- FEATURED SNIPPET ready: Begin elke sectie met direct antwoord op de H2 vraag

Context:
- site_niche: "{$siteNiche}"
- site_name: "{$siteName}"
- blog_type: "{$blogType}"
- target: Nederlandse consumenten die ACTIEF zoeken naar info over "{$title}"
- tone: Expert maar toegankelijk, GEEN sales-taal, WEL behulpzaam
- topic: "{$title}"
- context: "{$description}" (merk: {$brand})

HARDE EISEN (NON-NEGOTIABLE):
- PARAGRAAF LENGTE: Elke text sectie moet EEN uitgebreide paragraaf van 250-400 woorden bevatten - geen korte paragrafen!
- AANTAL SECTIONS: Minimaal 5, maximaal 6 sections voor complete coverage
- TOTALE LENGTE: Minimaal 2000 woorden totaal voor volledige SEO coverage
- Gebruik EXACTE zoektermen die mensen intypen ("beste [product] 2024", "hoe werkt [product]", etc.)
- Interne links naar: /producten (producten.index), /blogs (blogs.index), /reviews (reviews.index), /top-5 (top5)
- Natuurlijke keyword density: 0.5-1.5% voor primary, lager voor secondary
- GEEN keyword stuffing - Google straft dit af
- If blog_type="product" and a known product context is provided, you may add a subtle "product_context" note and 1 inline verwijzing; otherwise omit.
- Internal links: use url_keys from allowed set: producten.index | blogs.index | reviews.index | top5.

WRITING STYLE FOR LENGTH:
- Write expansive, detailed paragraphs with examples, explanations, and context
- Every section should thoroughly explore the topic with specific details
- Use transitional sentences to connect ideas within paragraphs
- Include practical examples and real-world scenarios
- Explain "why" and "how" extensively, not just "what"

Schema (BlogV3):

{
  "version": "blog.v3",
  "locale": "nl-NL",
  "author": "",              // = site_name
  "title": "",               // H1 ≤70 chars
  "standfirst": "",          // 2-3 zinnen, sterke intro
  "sections": [              // EXACTLY 5-6 sections total for SEO depth
    {
      "type": "text|image|quote|faq",
      "heading": "",         // H2 ≤60 chars with secondary keywords (text, faq), leeg voor image/quote
      "subheadings": [""],   // H3 ≤50 chars for text sections with long-tail keywords (optional)
      "paragraphs": [""],    // Array with ONE detailed paragraph of 250-400 words per text section (geen HTML)
      "image": {"url": "", "caption": ""},        // only if type=image
      "quote": {"text": ""},                    // only if type=quote
      "faq": [{"q": "", "a": ""}],               // only if type=faq (3-5 items with keyword-rich questions)
      "internal_links": [{"label": "", "url_key": "EXACT_URL_FROM_CONTEXT"}]
    }
  ],
  "closing": {
    "headline": "",          // afsluitende H2 ≤60 chars
    "summary": "",           // 2-3 alinea's van elk 150+ woorden met concrete waarde
    "primary_cta": {"label": "", "url_key": "producten.index|top5"} // exactly 1
  },
  "product_context": {       // ONLY when blog_type="product"; else omit or {}
    "name": "", "why_relevant": ""
  }
}

CONTENT FLOW CHECKLIST:
✅ Elke alinea sluit aan op de vorige - gebruik overgangszinnen
✅ Logische opbouw: probleem/vraag → uitleg → praktische tips → conclusie
✅ H2 secties bouwen een verhaal: basisbegrippen → diepere kennis → praktische toepassing
✅ Geen "losse blokken" - alles hangt samen als één verhaal

SEO EXECUTION:
✅ H1 met primary keyword (natuurlijk, niet geforceerd)
✅ H2s met secondary keywords die mensen zoeken
✅ H3s met long-tail zoektermen - NOOIT herhalen wat H2 al zegt!
✅ Begin elke sectie met direct antwoord op de vraag
✅ Gebruik LSI keywords (semantisch verwante woorden)
✅ Focus op SEARCH INTENT - beantwoord échte vragen

HEADING HIERARCHIE (KRITIEK):
❌ FOUT: H2 "Wat zijn groentefrietjes?" + H3 "Wat zijn krokante groentefrietjes?" (dubbel!)
✅ GOED: H2 "Wat zijn groentefrietjes?" + H3 "Voordelen tegenover gewone friet"
❌ FOUT: H2 "Hoe maak je groentefrietjes?" + H3 "Hoe maak je krokante groentefrietjes?" (dubbel!)
✅ GOED: H2 "Hoe maak je groentefrietjes?" + H3 "Voorbereiding groenten" + H3 "Bakproces stap-voor-stap"

REGEL: H3s behandelen ANDERE aspecten van het H2 onderwerp, NOOIT hetzelfde onderwerp!

KWALITEIT EISEN (VERPLICHT):
- PARAGRAAF LENGTE: Elke text sectie heeft EEN uitgebreide paragraaf van 250-400 woorden
- TOTAAL ARTIKEL: Minimaal 2000 woorden voor volledige SEO coverage
- DIEPGANG: Concrete, praktische informatie met voorbeelden en uitleg - geen vage algemene tekst
- Nederlandse SEO termen die mensen echt zoeken
- Natuurlijke keyword integratie (0.5-1.5% density)
- ABSOLUUT GEEN EMOJIS - dit is professionele content, geen social media

FINAL QUALITY CHECK BEFORE RETURNING:
- Count words in each text section paragraph - MUST be 250-400 words
- Count total sections - MUST be 5-6 sections
- Calculate total word count - MUST be 2000+ words
- If requirements not met, EXPAND content significantly before returning JSON

Return alleen minified JSON, niets anders.
PROMPT;

        $response = $this->chat([
            ['role' => 'system', 'content' => 'You are writing a comprehensive 2000+ word article. Return ONLY minified JSON with extensive, detailed paragraphs. Each text section MUST have a 250-400 word paragraph. NO short paragraphs allowed.'],
            ['role' => 'user', 'content' => $prompt],
        ], $model, 0.4, 8000); // Lage temperature voor consistentie, hoge tokens voor volledige Nederlandse content (8k voor no truncation)

        $content = trim($response['content'] ?? '{}');

        // Check if response was truncated or empty
        if (empty($content) || $content === '{}') {
            \Log::warning('OpenAI generateProductBlog returned empty content', [
                'title' => $title,
                'response_error' => $response['error'] ?? null,
                'model' => $model
            ]);
        }

        // Clean up any markdown artifacts and extra text
        $content = preg_replace('/^```(?:json)?\s*/', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);
        $content = preg_replace('/^[^{]*/', '', $content); // Remove any text before first {
        $content = preg_replace('/}[^}]*$/', '}', $content); // Remove any text after last }

        // Validate JSON and return
        $content = trim($content);
        $test = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Log the error for debugging
            \Log::error('OpenAI generateProductBlog returned invalid JSON', [
                'json_error' => json_last_error_msg(),
                'raw_content_preview' => substr($content, 0, 500),
                'title' => $title,
                'description' => substr($description, 0, 100),
            ]);

            // Return fallback JSON that matches blog.v3 schema requirements
            return json_encode([
                'version' => 'blog.v3',
                'locale' => 'nl-NL',
                'author' => getSetting('site_name', 'Redactie'),
                'title' => 'Content generatie mislukt - ' . date('Y-m-d H:i'),
                'standfirst' => 'Er is een technische fout opgetreden bij het genereren van deze content.',
                'is_fallback' => true, // Marker voor commands om fallback te detecteren
                'sections' => [
                    [
                        'type' => 'text',
                        'heading' => 'Technische fout',
                        'paragraphs' => ['Er is een fout opgetreden bij het genereren van content. Probeer het later opnieuw.'],
                    ]
                ],
                'closing' => [
                    'headline' => 'Onze excuses',
                    'summary' => 'We werken aan een oplossing.',
                    'primary_cta' => ['label' => 'Terug naar overzicht', 'url_key' => 'producten.index']
                ]
            ]);
        }

        return $content;
    }

    /**
     * Generates JSON-structured review content (v3 format)
     */
    public function generateProductReview(string $title, string $description, string $brand, string $model = 'gpt-4o-mini'): string
    {
        $prompt = <<<PROMPT
Je bent een Nederlandse SEO product specialist die reviews schrijft die PERFECT ranken EN converteren. Output ALLEEN minified JSON.

REVIEW FLOW MASTERCLASS:
1. Vertel een VERHAAL - van uitpakken tot dagelijks gebruik na weken
2. Elke sectie bouwt voort: verwachtingen → praktijk → oordeel
3. Gebruik overgangszinnen: "Na een week gebruik bleek...", "Wat ons opviel...", "In de praktijk betekent dit..."
4. CONCREET zijn - geen vage termen maar specifieke ervaringen

SEO OPTIMALISATIE VOOR REVIEWS:
- PRIMARY KEYWORD: "[product] review" of "[product] test" in titel
- SECONDARY KEYWORDS: "ervaringen", "voor en nadelen", "advies", "vergelijking"
- LONG-TAIL: "is [product] het waard", "[product] vs [concurrent]", "problemen met [product]"
- SEARCH INTENT: Mensen willen EERLIJKE mening van echte gebruiker
- FEATURED SNIPPET ready: Begin met direct antwoord "Is [product] aan te raden?"

Review over "{$title}" (merk: {$brand}) volgens exact JSON schema:

{
  "version": "review.v3", 
  "locale": "nl-NL",
  "intro": "Eerlijke, pakkende introductie in 2-3 zinnen over je ervaring met dit product",
  "sections": [
    {
      "type": "text",
      "heading": "Eerste indruk en verwachtingen",
      "paragraphs": ["Uitpakervaring en eerste indrukken", "Verwachtingen op basis van specificaties", "Context waarin je het product gaat testen"]
    },
    {
      "type": "pros-cons", 
      "heading": "Voor- en nadelen uit de praktijk",
      "pros": ["Concreet voordeel uit eigen ervaring", "Praktisch pluspunt", "Unieke eigenschap", "Positieve verrassing"],
      "cons": ["Eerlijk minpunt", "Praktische beperking", "Verbeterpunt"]
    },
    {
      "type": "quote",
      "quote": "Een opvallende bevinding of kernwaarde die het product definieert"
    },
    {
      "type": "text", 
      "heading": "Prestaties in de praktijk",
      "paragraphs": ["Concrete testresultaten", "Vergelijking met verwachtingen", "Hoe het presteert in dagelijks gebruik"]
    },
    {
      "type": "text",
      "heading": "Voor wie is dit geschikt?",
      "paragraphs": ["Ideale doelgroep en gebruikssituaties", "Wanneer zou je dit aanbevelen", "Alternatieven voor andere behoeften"]
    },
    {
      "type": "steps",
      "heading": "Koopbeslissing stap voor stap",
      "items": [
        {"title": "Definieer je behoeften", "detail": "Welke functies zijn echt belangrijk voor jouw situatie?"},
        {"title": "Vergelijk specificaties", "detail": "Waar moet je op letten bij het vergelijken van modellen?"},
        {"title": "Overweeg alternatieven", "detail": "Welke andere opties passen bij je budget en eisen?"},
        {"title": "Maak de definitieve keuze", "detail": "Finale afweging en waar je het beste kunt kopen"}
      ]
    },
    {
      "type": "faq",
      "items": [
        {"q": "Hoe lang gaat dit product mee?", "a": "Verwachte levensduur op basis van bouwkwaliteit"},
        {"q": "Is het geschikt voor beginners?", "a": "Gebruiksgemak en leercurve"},
        {"q": "Wat onderscheidt het van concurrenten?", "a": "Unieke voordelen ten opzichte van alternatieven"},
        {"q": "Waar moet je op letten bij aankoop?", "a": "Praktische aankoopadvies"}
      ]
    },
    {
      "type": "conclusion",
      "heading": "Eindoordeel",
      "paragraphs": ["Samenvatting van sterke en zwakke punten", "Eindaanbeveling en praktisch advies"]
    }
  ],
  "verdict": {
    "headline": "Onze eindconclusie",
    "buy_if": ["Koop als je situatie 1 hebt", "Perfect bij behoefte 2"],
    "skip_if": ["Sla over bij situatie 1", "Niet geschikt als je behoefte 2 hebt"],
    "bottom_line": "Eén zin die de kern van je aanbeveling samenvat"
  }
}

Productinformatie (context, niet letterlijk overnemen):
- Titel: {$title}
- Beschrijving: {$description}
- Merk: {$brand}

REVIEW FLOW CHECKLIST:
✅ Vertel een chronologisch verhaal: eerste indruk → dagelijks gebruik → eindoordeel
✅ Elke sectie refereert aan vorige: "Zoals eerder genoemd...", "Dit bleek in de praktijk..."
✅ Concreet zijn: "Na 3 weken gebruik", "Bij dagelijkse taken van 2 uur"
✅ Persoonlijke ervaring: "Wat ons opviel", "Onze ervaring was"

SEO OPTIMALISATIE REVIEWS:
✅ Titel met primary keyword: "[Product] Review" of "[Product] Test"
✅ H2s beantwoorden directe vragen: "Is [product] zijn geld waard?"
✅ Featured snippet ready: Begin met direct JA/NEE antwoord
✅ Long-tail keywords: "problemen met [product]", "[product] vs [concurrent]"
✅ LSI keywords: "ervaring", "advies", "aanbeveling", "vergelijking"

🚫 LINKING REGELS (VERPLICHT):
- Plaats NOOIT links in lopende tekst (paragraphs)
- Vermeld producten/merken wel, maar NIET linken
- Geen interne links - CTA knoppen in template doen dat werk
- Content is puur informatie - navigatie is apart

KWALITEIT EISEN:
- Elke paragraaf 120+ woorden voor SEO authority
- Specifieke details, geen vage algemene opmerkingen
- Eerlijke voor/nadelen gebaseerd op echte gebruikssituaties
- Duidelijke doelgroep aanbevelingen
- Nederlandse tone-of-voice: professioneel maar persoonlijk
- ABSOLUUT GEEN EMOJIS - dit is serieuze product review content

Return alleen minified JSON, niets anders.
PROMPT;

        $response = $this->chat([
            ['role' => 'system', 'content' => 'Return ONLY minified JSON. No markdown, no commentary.'],
            ['role' => 'user', 'content' => $prompt],
        ], $model, 0.4, 8000); // Lage temperature voor consistentie, hoge tokens voor volledige Nederlandse reviews (8k voor no truncation)

        $content = trim($response['content'] ?? '{}');

        // Check if response was truncated or empty
        if (empty($content) || $content === '{}') {
            \Log::warning('OpenAI generateProductReview returned empty content', [
                'title' => $title,
                'response_error' => $response['error'] ?? null,
                'model' => $model
            ]);
        }

        // Clean up any markdown artifacts and extra text
        $content = preg_replace('/^```(?:json)?\s*/', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);
        $content = preg_replace('/^[^{]*/', '', $content); // Remove any text before first {
        $content = preg_replace('/}[^}]*$/', '}', $content); // Remove any text after last }

        // Validate JSON and return
        $content = trim($content);
        $test = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Return fallback JSON if parsing fails
            return json_encode([
                'version' => 'review.v3',
                'locale' => 'nl-NL', 
                'intro' => 'Er is een fout opgetreden bij het genereren van content.',
                'sections' => [],
                'verdict' => ['headline' => 'Conclusie', 'bottom_line' => 'Review niet beschikbaar']
            ]);
        }
        
        return $content;
    }

    /**
     * Generates JSON-structured review for custom affiliate products (Moovv, etc.)
     * Uses neutral, objective tone without hands-on testing claims
     */
    public function generateCustomProductReview(string $title, string $description, string $brand, string $niche, string $model = 'gpt-4o-mini'): string
    {
        $prompt = <<<PROMPT
Je bent een Nederlandse product specialist die objectieve productanalyses schrijft voor {$niche} websites.
Output ALLEEN minified JSON volgens het exacte schema hieronder.

SCHRIJFSTIJL - BELANGRIJK:
- NOOIT schrijven alsof je het product fysiek hebt getest
- Gebruik GEEN: "ik heb getest", "na het uitpakken", "in mijn ervaring", "ik merkte", "wij testten"
- Gebruik WEL: "dit product biedt", "op basis van de specificaties", "gebruikers rapporteren", "de features laten zien"
- Focus op objectieve analyse van specs, features en logische verwachtingen
- Professioneel en informatief, niet persoonlijk

CONTENT FOCUS:
- Analyseer specificaties en wat ze betekenen voor de gebruiker
- Bespreek features en hun praktische toepassingen
- Vergelijk met alternatieven op basis van specs
- Geef eerlijke voor- en nadelen gebaseerd op productinformatie
- Help consumenten een weloverwogen beslissing te maken

Product: "{$title}"
Merk: {$brand}
Categorie: {$niche}
Productinformatie: {$description}

Genereer een review volgens dit exacte JSON schema:

{
  "version": "review.v3",
  "locale": "nl-NL",
  "intro": "Objectieve opening over wat dit product biedt en waarom het relevant is (2-3 zinnen, GEEN persoonlijke ervaring)",
  "sections": [
    {
      "type": "text",
      "heading": "Wat dit product biedt",
      "paragraphs": ["Wat je krijgt volgens de fabrikant, belangrijkste features", "Toegevoegde waarde en unieke eigenschappen"]
    },
    {
      "type": "text",
      "heading": "Specificaties en features in detail",
      "paragraphs": ["Technische specs uitgelegd en wat ze betekenen", "Hoe de features werken en wat je ermee kunt", "Vergelijking met vergelijkbare producten"]
    },
    {
      "type": "pros-cons",
      "heading": "Sterke punten en aandachtspunten",
      "pros": ["Concreet voordeel op basis van specs", "Praktisch sterk punt", "Unieke feature of eigenschap", "Goede prijs-kwaliteit aspect"],
      "cons": ["Eerlijk aandachtspunt of beperking", "Mogelijk nadeel voor specifieke gebruikers", "Aspect waar concurrentie beter scoort"]
    },
    {
      "type": "quote",
      "quote": "Kernpunt van de analyse - wat maakt dit product bijzonder of belangrijk om te weten"
    },
    {
      "type": "text",
      "heading": "Geschiktheid en toepassingen",
      "paragraphs": ["Voor welke gebruikers en situaties dit product het meest geschikt is", "Wanneer dit de juiste keuze is en wanneer niet"]
    },
    {
      "type": "text",
      "heading": "Verwachtingen voor gebruik",
      "paragraphs": ["Wat je kunt verwachten op basis van de specs en features", "Hoe dit zich vertaalt naar dagelijks gebruik", "Potentiële voordelen en beperkingen in de praktijk"]
    },
    {
      "type": "steps",
      "heading": "Aankoopoverweging stap voor stap",
      "items": [
        {"title": "Bepaal je behoeften", "detail": "Welke functies zijn belangrijk voor jouw situatie?"},
        {"title": "Vergelijk alternatieven", "detail": "Hoe verhoudt dit zich tot andere opties in deze prijsklasse?"},
        {"title": "Check de specificaties", "detail": "Voldoen de specs aan jouw eisen?"},
        {"title": "Maak je keuze", "detail": "Is dit de beste optie voor jouw budget en behoeften?"}
      ]
    },
    {
      "type": "faq",
      "items": [
        {"q": "Voor wie is dit product geschikt?", "a": "Doelgroep en ideale gebruikssituaties"},
        {"q": "Wat zijn de belangrijkste voordelen?", "a": "Kernvoordelen gebaseerd op features"},
        {"q": "Waar moet je op letten?", "a": "Belangrijke aandachtspunten bij aankoop"},
        {"q": "Hoe verhoudt het zich tot alternatieven?", "a": "Positionering ten opzichte van concurrentie"}
      ]
    },
    {
      "type": "conclusion",
      "heading": "Conclusie",
      "paragraphs": ["Samenvatting van de analyse: sterke punten en aandachtspunten", "Aanbeveling: voor wie is dit een goede keuze?"]
    }
  ],
  "verdict": {
    "headline": "Onze beoordeling",
    "buy_if": [
      "Koop als je [specifieke behoefte/situatie]",
      "Perfect bij [specifieke use case]"
    ],
    "skip_if": [
      "Sla over als je [specifieke situatie waar dit niet past]",
      "Niet geschikt als [specifieke beperking]"
    ],
    "bottom_line": "Kernboodschap: waarde propositie in één zin"
  }
}

🚫 LINKING REGELS (VERPLICHT):
- Plaats NOOIT links in lopende tekst (paragraphs)
- Vermeld producten/merken wel, maar NIET linken
- Geen interne links - CTA knoppen in template doen dat werk
- Content is puur informatie - navigatie is apart

KRITISCHE REGELS:
1. Gebruik NOOIT persoonlijke ervaringen of test-taal
2. Baseer alles op specs, features en logische analyse
3. Blijf objectief en informatief
4. Voor/nadelen moeten concreet en specifiek zijn
5. Sections moeten natuurlijk lezen, niet robotachtig
6. Totaal 1200-1600 woorden
7. Return ALLEEN minified JSON, geen markdown

Genereer nu de JSON:
PROMPT;

        $response = $this->chat([
            ['role' => 'system', 'content' => 'Je bent een professionele product analist die objectieve productanalyses schrijft.'],
            ['role' => 'user', 'content' => $prompt],
        ], $model, 0.7, 4000);

        return trim($response['content'] ?? '');
    }

    /**
     * Genereer meta title en meta description in JSON-formaat (SEO lengtegrenzen).
     * Optioneel: geef een ander model mee.
     */
    public function generateMetaTags(string $title, string $description, string $brand, string $model = 'gpt-4o-mini'): array
    {
        $prompt = "Je bent een SEO-expert die meta tags schrijft die Google AI Overview vriendelijk zijn.

Schrijf pakkende meta tags die conversie stimuleren:

META TITLE (exact 60 tekens):
- Integreer kernzoekwoord + merk + USP
- Gebruik separators (| of •) voor structuur  
- Eindig met actie-CTA (\"Vergelijk\", \"Ontdek\", \"Bekijk\")
- Voorbeelden: \"Beste Philips Airfryer {huidige jaar} | Vergelijk Modellen\" of \"Samsung TV Aanbieding • Ontdek Kortingen\"

META DESCRIPTION (exact 160 tekens):
- Beantwoord de kernvraag van de zoeker
- Voeg USP + voordeel + CTA toe
- Gebruik actiewoorden (\"ontdek\", \"vergelijk\", \"bespaar\")  
- Integreer sociale proof indien mogelijk
- Sluit af met duidelijke call-to-action

Optimaliseer voor Google AI Overview:
- Geef directe antwoorden op zoekvragen
- Gebruik contextueel rijke informatie
- Integreer relevante synoniemen natuurlijk
- Focus op gebruikersintentie en voordelen

Productdata:
Titel: {$title}
Beschrijving: {$description}  
Merk: {$brand}

Output exact als JSON (zonder extra tekst):
{
  \"meta_title\": \"...\",
  \"meta_description\": \"...\"
}";

        $response = $this->chat([
            ['role' => 'system', 'content' => 'Je bent een behulpzame assistent.'],
            ['role' => 'user', 'content' => $prompt],
        ], $model, 0.3, 200); // Zeer lage temperature voor consistente meta tags

        $content = trim($response['content'] ?? '');
        $decoded = $this->decodeJsonFromContent($content);

        if (empty($decoded) || (!isset($decoded['meta_title']) && !isset($decoded['meta_description']))) {
            \Log::warning('OpenAI generateMetaTags failed or incomplete', [
                'title' => $title,
                'raw_response' => $content,
                'decoded_json' => $decoded,
                'response_error' => $response['error'] ?? null,
                'model' => $model
            ]);
        }

        $metaTitle = isset($decoded['meta_title']) ? Str::limit(trim((string) $decoded['meta_title']), 60, '') : null;
        $metaDesc = isset($decoded['meta_description']) ? Str::limit(trim((string) $decoded['meta_description']), 160, '') : null;

        // Extra schoonmaak (geen quotes aan de randen)
        $metaTitle = $metaTitle ? trim($metaTitle, " \t\n\r\0\x0B\"'") : null;
        $metaDesc = $metaDesc ? trim($metaDesc, " \t\n\r\0\x0B\"'") : null;

        return [
            'meta_title' => $metaTitle,
            'meta_description' => $metaDesc,
        ];
    }

    /**
     * UNIEKE productbeschrijving genereren in semantische HTML + korte samenvatting.
     * Retour: ['html' => string, 'summary' => string, 'model' => string]
     *
     * $payload vereiste keys:
     * - title (string)
     * - brand (string|null)
     * - niche (string)
     * - source_description (string)  // ruwe Bol-tekst (onbewerkte bron)
     * - specs (array<string,string>)  // samengevatte top-specs voor context
     * - site_name (string)
     *
     * Optioneel: $payload['model'] voor override.
     */
    public function rewriteProductDescription(array $payload): array
    {
        $title = $payload['title'] ?? '';
        $brand = $payload['brand'] ?? '';
        $niche = $payload['niche'] ?? '';
        $source = (string) ($payload['source_description'] ?? '');
        $specs = (array) ($payload['specs'] ?? []);
        $site = $payload['site_name'] ?? config('app.name');
        $model = $payload['model'] ?? 'gpt-4o-mini';

        // Beperk bron tot compacte context (NIET kopiëren)
        $sourceSummary = Str::limit(strip_tags($source), 1500, '');

        // Spec regels voor in de prompt
        $specLines = collect($specs)->map(fn ($v, $k) => "$k: $v")->implode("\n");

        $system = <<<'SYS'
Je bent een product copywriter die teksten schrijft voor Google AI Overview optimalisatie.
Schrijf unieke, feitelijk kloppende, conversiegerichte productteksten in het Nederlands.

Regels:
- Neem geen zinnen letterlijk over uit aangeleverde bron.
- Gebruik semantische HTML (geen inline CSS), geschikt voor directe publicatie.
- Geen verwijzingen naar bol.com of externe bronnen.
- Geen misleidende claims of ononderbouwde superlatieven.
- Toon: professioneel, helder, behulpzaam, conversiegericht.
- Schrijf scanbaar: korte alinea's (max 3-4 zinnen), bullet points, duidelijke koppen.
- SEO: verwerk focuszoekwoorden uit titel/merk/niche natuurlijk; geen keyword stuffing.
- Lengte: 600–900 woorden voor diepgaande, waardevolle content.

Google AI Overview optimalisatie:
- Beantwoord impliciete vragen van zoekers direct en concreet.
- Gebruik contextueel rijke, feitelijk correcte informatie.
- Integreer vraag-antwoordstijl voor betere vindbaarheid.  
- Focus op praktische voordelen en concrete gebruikssituaties.
- Geef directe antwoorden op "waarom", "hoe" en "wat" vragen.
SYS;

        $user = <<<USR
Context:
- Producttitel: {$title}
- Merk: {$brand}
- Niche: {$niche}
- Site/afzender: {$site}

Belangrijkste specificaties:
{$specLines}

Samenvatting van aangeleverde bron (alleen als context, NIET kopiëren):
{$sourceSummary}

Taken:
1) Schrijf een volledige productbeschrijving van 600-900 woorden in schone semantische HTML:

<section>
  <h2>Introductie</h2>
  <p>Beantwoord direct de kernvraag: wat maakt dit product bijzonder? (2-3 zinnen)</p>

  <h2>Belangrijkste voordelen</h2>
  <p>Concrete voordelen met praktische voorbeelden...</p>
  <ul>
    <li>Voordeel 1 met meetbare meerwaarde</li>
    <li>Voordeel 2 met gebruikssituatie</li>
    <li>Voordeel 3 met concreet resultaat</li>
  </ul>
  
  <h3>Voor welke doelgroep?</h3>
  <p>Specifieke gebruikssituaties en doelgroepen...</p>

  <h2>Praktische voordelen t.o.v. alternatieven</h2>
  <p>Wat onderscheidt dit product van concurrenten?</p>
  <ul>
    <li>Unieke eigenschap 1 vs. alternatief</li>
    <li>Praktisch voordeel 2 in dagelijks gebruik</li>
    <li>Meerwaarde 3 op lange termijn</li>
  </ul>

  <h2>Gebruik & praktische tips</h2>
  <p>Concrete adviezen voor optimaal resultaat...</p>
  <h3>Installatie & setup</h3>  
  <p>Praktische stappen voor gebruik...</p>

  <h2>Specificaties in mensentaal</h2>
  <ul>
    <li>Specificatie 1: wat betekent dit praktisch?</li>
    <li>Specificatie 2: waarom is dit belangrijk?</li>
  </ul>

  <h2>Veelgestelde vragen</h2>
  <h3>Hoe lang gaat dit product mee?</h3>
  <p>Concreet antwoord met verwachte levensduur.</p>
  <h3>Is dit geschikt voor [specifieke toepassing]?</h3>
  <p>Direct antwoord met praktische toelichting.</p>
  <h3>Wat zijn de belangrijkste verschillen met [alternatief]?</h3>
  <p>Objectieve vergelijking met kernpunten.</p>
  
  <h2>Conclusie</h2>
  <p>Samenvatting kernpunten en aanbeveling...</p>
  <p><strong>Ontdek alle specificaties en vergelijk prijzen op {$site}. Kies bewust wat perfect past bij jouw behoeften!</strong></p>
</section>

2) Maak het volledig uniek. Herformuleer, interpreteer, leg uit in eigen woorden.
3) Schrijf Google AI Overview vriendelijk: beantwoord "waarom", "hoe" en "voor wie" vragen.
4) Integreer natuurlijke synoniemen van kernzoekwoorden zonder keyword stuffing.
5) Lever een ultra-korte samenvatting (max. 35 woorden) voor snippets.

Antwoord in JSON met exact deze keys: 
{
  "html": "<section>...</section>",
  "summary": "korte samenvatting"
}
USR;

        $response = $this->chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $user],
        ], $model, 0.7, 2500);

        $raw = trim($response['content'] ?? '{}');
        $json = $this->decodeJsonFromContent($raw);

        // Fallbacks indien model geen valide JSON terugstuurt
        if (! is_array($json) || empty($json['html'])) {
            \Log::error('OpenAI rewriteProductDescription failed', [
                'title' => $payload['title'] ?? 'unknown',
                'raw_response' => $raw,
                'decoded_json' => $json,
                'response_error' => $response['error'] ?? null,
                'model' => $model
            ]);
            
            $fallbackHtml = '<section>'
                .'<h2>Introductie</h2>'
                .'<p>'.e(Str::limit(strip_tags($sourceSummary), 450)).'</p>'
                .'<h2>Conclusie</h2>'
                ."<p>Bekijk alle details en vergelijk modellen op {$site}.</p>"
                .'</section>';

            return [
                'html' => $fallbackHtml,
                'summary' => Str::limit(strip_tags($sourceSummary), 140),
                'model' => $model,
            ];
        }

        // Sanitize + normaliseer
        $cleanHtml = $this->stripHtmlBodyTags($json['html']);
        if ($cleanHtml !== '' && $cleanHtml === strip_tags($cleanHtml)) {
            $cleanHtml = $this->normalizeAiPlainTextToHtml($cleanHtml);
        }
        $cleanHtml = $this->sanitizeHtml($cleanHtml);

        return [
            'html' => $cleanHtml,
            'summary' => isset($json['summary']) ? Str::limit(strip_tags($json['summary']), 200) : Str::limit(strip_tags($cleanHtml), 140),
            'model' => $model,
        ];
    }

    /**
     * Algemene prompt-functionaliteit (vrij gebruik). Optioneel ander model.
     */
    public function generateFromPrompt(string $prompt, string $model = 'gpt-4o-mini'): string
    {
        $response = $this->chat([
            ['role' => 'system', 'content' => 'Je bent een professionele Nederlandse tekstschrijver.'],
            ['role' => 'user', 'content' => $prompt],
        ], $model, 0.7, 1200);

        return trim($response['content'] ?? '');
    }

    /**
     * Genereer GSC-gebaseerde blog met E-E-A-T optimalisatie
     */
    public function generateGscOpportunityBlog(array $themeData, string $niche, string $internalLinkContext, string $model = 'gpt-4o-mini'): string
    {
        $siteName = getSetting('site_name', config('app.name'));
        $currentYear = now()->year;
        $primaryKeyword = $themeData['primary_keyword'];
        $relatedKeywords = implode(', ', array_slice($themeData['related_keywords'], 0, 5));
        $contentType = $themeData['content_type'];
        $suggestedAngle = $themeData['suggested_angle'];

        $prompt = <<<PROMPT
You are a senior product expert and content strategist writing for {$siteName}, a trusted {$niche} specialist website. Output ONLY minified JSON per the schema. No markdown, no commentary.

EXPERTISE CONTEXT:
- You are an experienced {$niche} specialist with years of hands-on testing experience
- {$siteName} has tested and reviewed hundreds of {$niche} products
- Your content is based on real-world usage, not just specifications
- You understand consumer needs and market trends in the {$niche} industry

CONTENT ASSIGNMENT:
- Primary keyword: "{$primaryKeyword}" 
- Related keywords: {$relatedKeywords}
- Content type: {$contentType}
- Angle: {$suggestedAngle}
- Niche: {$niche}
- Target audience: Nederlandse consumenten die {$primaryKeyword} zoeken

E-E-A-T REQUIREMENTS (hard):
- Experience: Include phrases like "Uit onze tests blijkt", "Na maanden gebruik", "In onze ervaring"
- Expertise: Show deep product knowledge, mention specifications, explain technical aspects
- Authoritativeness: Reference industry trends, compare multiple brands, cite common user issues
- Trustworthiness: Be honest about limitations, mention both pros and cons, avoid overselling

CONTENT REQUIREMENTS (CRITICAL):
- Wordcount: MINIMUM 1200 words, target 1500-1800 words for comprehensive coverage
- Structure: Clear H1 (≤70 chars), exactly 5-6 detailed H2 sections (≤60 chars), H3 subsections where needed
- Each paragraph MUST be substantial (150+ words) with detailed expertise
- Write in-depth sections, not brief summaries - this is comprehensive expert content
- Tone: Professional but approachable, confident expertise without sales pressure
- SEO: Natural keyword integration, semantic variations, long-tail phrases

TITLE REQUIREMENTS (CRITICAL):
- Write NATURAL, human-like blog titles that people would actually search for
- Avoid product specification lists or technical jargon in titles
- Use conversational language: "Welke [product] past bij jou?", "Alles over [keyword]", "[Keyword]: Complete Gids"
- Examples of GOOD titles: "Welke Loopband Past Bij Jouw Thuisgym?", "Alles Over Elektrische Loopbanden", "Loopband Kopen: Complete Gids {$currentYear}"
- Examples of BAD titles: "Elektrische Loopband met Hendel - Met Afstandsbediening - 1-10km/u Product Info en Reviews"
- Make it feel like content a human expert would write, not a product listing
- INTERNAL LINKING (CRITICAL): Use EXACT URLs from context below:

{$internalLinkContext}

IMPORTANT: In internal_links sections, use the EXACT URL provided in parentheses as url_key, NOT generic routes like "producten.index". 
Example: If context shows "Digitale Airfryer XXL 10L (https://example.com/producten/product-slug)", use "producten/product-slug" as url_key.

BANNED PHRASES (never use):
- "AI-generated", "According to sources", "Research shows" (without specifics)
- Generic statements without expertise backing
- Overly promotional language

EXPERTISE LANGUAGE TO USE:
- "In onze uitgebreide tests van [product]..."
- "Na jaren ervaring in de {$niche} industrie hebben we ontdekt..."
- "Klanten vragen ons vaak over [specific issue]..."
- "Uit onze database van [number]+ product reviews blijkt..."
- "Een veel voorkomend probleem dat we tegenkomen is..."
- "Professionals in de sector weten dat..."

Schema (BlogV3):

{
  "version": "blog.v3",
  "locale": "nl-NL",
  "author": "{$siteName} Redactie",
  "title": "",               // NATURAL blog title ≤70 chars (NOT a product specification list!)
  "standfirst": "",          // 2-3 zinnen opening met expertise claim
  "sections": [              // 4-6 sections for comprehensive coverage
    {
      "type": "text",
      "heading": "",         // H2 ≤60 chars
      "subheadings": [""],   // H3 ≤50 chars (optional)
      "paragraphs": [""],    // Rich content >100 woorden per paragraaf
      "internal_links": [{"label": "", "url_key": "EXACT_URL_FROM_CONTEXT"}]
    }
  ],
  "closing": {
    "headline": "",          // H2 ≤60 chars
    "summary": "",           // 2-3 alinea's met concrete expert advice
    "primary_cta": {"label": "", "url_key": "producten.index|top5"}
  }
}

TASK:
Write a comprehensive {$suggestedAngle} about "{$primaryKeyword}" that showcases deep {$niche} expertise. Include real-world insights, specific product knowledge, and practical advice that only an experienced {$niche} specialist would know. Make it clear this content comes from hands-on experience and industry knowledge, not generic research.

CRITICAL: Create a NATURAL blog title that sounds like something a human would write and search for. Think "Welke Loopband Kies Je in {$currentYear}?" NOT "Elektrische Loopband met Hendel - Met Afstandsbediening - 1-10km/u Product Info en Reviews".

Return only minified JSON, nothing else.
PROMPT;

        $response = $this->chat([
            ['role' => 'system', 'content' => 'You are an expert content writer with deep product knowledge. Return ONLY minified JSON.'],
            ['role' => 'user', 'content' => $prompt],
        ], $model, 0.4, 8000); // Lage temperature voor expertise consistentie, hoge tokens voor E-E-A-T diepgang (8k voor no truncation)

        $content = trim($response['content'] ?? '{}');

        // Clean up any markdown artifacts
        $content = preg_replace('/^```(?:json)?\s*/', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);
        $content = preg_replace('/^[^{]*/', '', $content);
        $content = preg_replace('/}[^}]*$/', '}', $content);
        
        // Validate JSON
        $content = trim($content);
        $test = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Return enhanced fallback with expertise
            return json_encode([
                'version' => 'blog.v3',
                'locale' => 'nl-NL',
                'author' => $siteName . ' Redactie',
                'title' => 'Expert Gids: ' . ucfirst($primaryKeyword),
                'standfirst' => "Uit onze jarenlange ervaring met {$niche} producten hebben we deze uitgebreide gids samengesteld.",
                'sections' => [
                    [
                        'type' => 'text',
                        'heading' => 'Wat u moet weten over ' . $primaryKeyword,
                        'paragraphs' => [
                            "Als {$niche} specialisten hebben we uitgebreide ervaring met {$primaryKeyword}. In deze gids delen we onze belangrijkste inzichten.",
                        ],
                        'internal_links' => [
                            ['label' => 'Bekijk alle ' . $niche . ' producten', 'url_key' => 'producten.index']
                        ]
                    ]
                ],
                'closing' => [
                    'headline' => 'Onze expert aanbeveling',
                    'summary' => 'Gebaseerd op onze ervaring raden we aan om grondig te vergelijken voordat u een keuze maakt.',
                    'primary_cta' => ['label' => 'Ontdek onze aanbevelingen', 'url_key' => 'top5']
                ]
            ]);
        }
        
        return $content;
    }

    /**
     * Generieke helper voor vrije prompts (kortere alias voor generateFromPrompt)
     */
    public function generate(string $prompt, string $model = 'gpt-4o-mini'): string
    {
        return $this->generateFromPrompt($prompt, $model);
    }

    /* =======================
     *   PRIVATE HELPERS
     * ======================= */

    /**
     * Robuuste chat-aanroep met retry logic, exponential backoff en circuit breaker.
     *
     * Multi-site vriendelijk: max 5 retries met exponential backoff om server resources te sparen.
     * Circuit breaker voorkomt cascade failures bij OpenAI outages.
     * Geschikt voor 20+ affiliate sites die dezelfde codebase gebruiken.
     */
    public function chat(array $messages, string $model, float $temperature = 0.8, int $maxTokens = 2000, ?array $responseFormat = null): array
    {
        // Circuit breaker check - skip als API down is
        if ($this->circuitBreaker->isOpen()) {
            \Log::warning('OpenAI API call skipped - circuit breaker is OPEN', [
                'model' => $model,
                'status' => $this->circuitBreaker->getStatus()
            ]);

            return [
                'content' => '',
                'error' => 'Circuit breaker is open - OpenAI API temporarily unavailable',
                'circuit_breaker' => 'open'
            ];
        }

        $maxAttempts = 5;
        $baseDelay = 1000000; // 1 seconde in microseconden
        $lastError = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $params = [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => $temperature,
                    'max_tokens' => $maxTokens,
                ];

                // Add response format if provided (for Structured Outputs)
                if ($responseFormat !== null) {
                    $params['response_format'] = $responseFormat;
                }

                $res = $this->client->chat()->create($params);

                $content = $res->choices[0]->message->content ?? '';

                // Success - reset circuit breaker en log bij retries
                $this->circuitBreaker->recordSuccess();

                if ($attempt > 1) {
                    \Log::info("OpenAI API call successful after {$attempt} attempts", [
                        'model' => $model,
                        'attempts' => $attempt
                    ]);
                }

                return [
                    'content' => $content,
                    'usage' => $res->usage ?? null,
                    'attempts' => $attempt,
                ];

            } catch (Throwable $e) {
                $lastError = $e;

                \Log::warning("OpenAI API call attempt {$attempt}/{$maxAttempts} failed", [
                    'model' => $model,
                    'error' => $e->getMessage(),
                    'attempt' => $attempt
                ]);

                // Als dit niet de laatste poging is, wacht met exponential backoff
                if ($attempt < $maxAttempts) {
                    $delay = $baseDelay * pow(2, $attempt - 1); // Exponential: 1s, 2s, 4s, 8s, 16s
                    usleep($delay);
                }
            }
        }

        // Alle pogingen gefaald - registreer bij circuit breaker
        $this->circuitBreaker->recordFailure();

        \Log::error("OpenAI API call failed after {$maxAttempts} attempts", [
            'model' => $model,
            'final_error' => $lastError?->getMessage(),
            'circuit_breaker_status' => $this->circuitBreaker->getStatus()
        ]);

        return [
            'content' => '',
            'error' => $lastError?->getMessage(),
            'attempts' => $maxAttempts
        ];
    }

    /**
     * Trek JSON uit een LLM-antwoord (ook als er tekst omheen staat).
     */
    protected function decodeJsonFromContent(string $content): array
    {
        // Probeer code fences eerst (schoner)
        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $content, $m)) {
            $decoded = json_decode($m[1], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        // Dan het eerste JSON-blok
        if (preg_match('/\{.*\}/s', $content, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * Verwijder <html> en <body> wrappers indien aanwezig (sanity).
     */
    protected function stripHtmlBodyTags(string $html): string
    {
        $html = trim($html);
        $search = ['<html>', '</html>', '<body>', '</body>'];

        return str_ireplace($search, ['', '', '', ''], $html);
    }

    /**
     * Basissanitisatie: verwijder <script> en dubieuze tags/attributen.
     * (Zeer conservatief; breidt uit indien nodig.)
     */
    protected function sanitizeHtml(string $html): string
    {
        // Script tags weg
        $html = preg_replace('#<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>#i', '', $html) ?? $html;

        // Onverhoopt style/script attributes strippen
        $html = preg_replace('/\son\w+="[^"]*"/i', '', $html) ?? $html; // onClick etc.
        $html = preg_replace('/\son\w+=\'[^\']*\'/i', '', $html) ?? $html;
        $html = preg_replace('/\sstyle=("|\').*?\1/i', '', $html) ?? $html;

        // Whitelist van basistags (optioneel; hier laten we het vrij, want we vragen semantische HTML)
        return trim($html);
    }

    /**
     * Zet AI-plain-text met kopjes om naar semantische HTML.
     * Herkent NL-kopjes als "Introductie", "Belangrijkste voordelen", "Wie kiest voor dit model?",
     * "Gebruik & praktische tips", "Specificaties in mensentaal", "Veelgestelde vragen", "Conclusie", "CTA:".
     */
    protected function normalizeAiPlainTextToHtml(string $text): string
    {
        $lines = preg_split("/\R+/", trim($text)) ?: [];
        $html = [];
        $inSpecs = false;
        $inFaq = false;
        $specItems = [];

        $openSection = function () use (&$html) {
            if (empty($html)) {
                $html[] = '<section>';
            }
        };
        $closeSection = function () use (&$html, &$inSpecs, &$specItems) {
            if ($inSpecs) {
                $html[] = '<ul>';
                foreach ($specItems as $li) {
                    $html[] = '<li>'.e($li).'</li>';
                }
                $html[] = '</ul>';
                $inSpecs = false;
                $specItems = [];
            }
            if (! empty($html) && substr(end($html), -10) !== '</section>') {
                $html[] = '</section>';
            }
        };

        $headingMap = [
            'introductie' => 'h2',
            'belangrijkste voordelen' => 'h2',
            'wie kiest voor dit model?' => 'h3',
            'gebruik & praktische tips' => 'h2',
            'specificaties in mensentaal' => 'h2',
            'veelgestelde vragen' => 'h2',
            'conclusie' => 'h2',
        ];

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);
            if ($line === '') {
                continue;
            }

            $lower = mb_strtolower($line);

            // CTA: apart afhandelen
            if (str_starts_with($lower, 'cta:')) {
                $openSection();
                $cta = trim(mb_substr($line, 4));
                $html[] = '<p><strong>CTA:</strong> '.e($cta).'</p>';

                continue;
            }

            // FAQ-vragen herkennen in "Veelgestelde vragen" sectie (regels die eindigen op '?')
            if ($inFaq && str_ends_with($line, '?')) {
                $openSection();
                $html[] = '<h3>'.e($line).'</h3>';

                continue;
            }

            // Kopjes herkennen
            $matchedHeading = null;
            foreach ($headingMap as $label => $tag) {
                if ($lower === $label) {
                    $matchedHeading = [$tag, $label];
                    break;
                }
            }

            if ($matchedHeading) {
                // Sluit specs-lijst indien open
                if ($inSpecs) {
                    $html[] = '<ul>';
                    foreach ($specItems as $li) {
                        $html[] = '<li>'.e($li).'</li>';
                    }
                    $html[] = '</ul>';
                    $inSpecs = false;
                    $specItems = [];
                }

                $openSection();
                [$tag, $label] = $matchedHeading;
                $html[] = "<{$tag}>".e($line)."</{$tag}>";

                // Modus-schakelaars
                $inSpecs = ($label === 'specificaties in mensentaal');
                $inFaq = ($label === 'veelgestelde vragen');

                continue;
            }

            // Specregel herkennen: "Naam: waarde"
            if ($inSpecs && preg_match('/^[^:]{2,}:\s*.+$/u', $line)) {
                $specItems[] = $line;

                continue;
            }

            // Normale paragraaf
            $openSection();
            $html[] = '<p>'.e($line).'</p>';
        }

        $closeSection();

        if (empty($html)) {
            $paras = array_filter(preg_split("/\R{2,}/", $text) ?: []);
            if ($paras) {
                $out = '<section>';
                foreach ($paras as $p) {
                    $out .= '<p>'.e(trim($p)).'</p>';
                }
                $out .= '</section>';

                return $out;
            }

            return '<section><p>'.e($text).'</p></section>';
        }

        return implode("\n", $html);
    }
}
