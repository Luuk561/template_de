<?php

namespace App\Services;

use App\Services\OpenAIService;
use App\Models\InformationPage;
use Illuminate\Support\Facades\Log;

/**
 * V2: Clean rewrite of content blocks generator
 * - 1 OpenAI call per block (not all at once)
 * - Standard prompt + block-specific prompt
 * - Better control over forbidden words
 */
class ContentBlocksGeneratorServiceV2
{
    protected OpenAIService $openAI;

    public function __construct(OpenAIService $openAI)
    {
        $this->openAI = $openAI;
    }

    /**
     * Generate all content blocks for a site
     */
    public function generateContentBlocks(string $niche, string $siteName, ?string $uniqueFocus = null, $progressCallback = null, array $specificKeys = []): array
    {
        $blocks = [];
        $blockDefinitions = $this->getBlockDefinitions();

        // Filter block definitions if specific keys requested
        if (!empty($specificKeys)) {
            $blockDefinitions = array_filter($blockDefinitions, function($blockKey) use ($specificKeys) {
                foreach ($specificKeys as $specificKey) {
                    if (str_starts_with($blockKey, $specificKey) || $blockKey === $specificKey) {
                        return true;
                    }
                }
                return false;
            }, ARRAY_FILTER_USE_KEY);

            if (empty($blockDefinitions)) {
                Log::warning("No blocks match the requested keys", ['keys' => $specificKeys]);
                return [];
            }
        }

        $total = count($blockDefinitions);
        $current = 0;

        foreach ($blockDefinitions as $blockKey => $config) {
            $current++;

            Log::info("Generating block: {$blockKey}");

            if ($progressCallback) {
                $progressCallback($current, $total, $blockKey);
            }

            $blockContent = $this->generateSingleBlock(
                $blockKey,
                $config,
                $niche,
                $siteName,
                $uniqueFocus
            );

            // Store each unit separately (e.g., homepage.hero.title, homepage.hero.subtitle)
            foreach ($blockContent as $unitKey => $unitValue) {
                $fullKey = $blockKey . '.' . $unitKey;
                $blocks[$fullKey] = $unitValue;
            }
        }

        return $blocks;
    }

    /**
     * Generate a single block with 1 OpenAI call
     */
    private function generateSingleBlock(
        string $blockKey,
        array $config,
        string $niche,
        string $siteName,
        ?string $uniqueFocus
    ): array {
        $standardPrompt = $this->getStandardPrompt($niche, $siteName, $uniqueFocus);
        $blockPrompt = $this->getBlockPrompt($config, $niche);

        $fullPrompt = $standardPrompt . "\n\n" . $blockPrompt;

        // Make 1 OpenAI call for this block
        $response = $this->openAI->chat([
            ['role' => 'system', 'content' => 'You are a Dutch SEO copywriter. Return ONLY valid JSON.'],
            ['role' => 'user', 'content' => $fullPrompt],
        ], 'gpt-4o', 0.7, 4000);

        $content = trim($response['content'] ?? '{}');
        $content = $this->cleanJsonResponse($content);
        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            Log::error("Failed to decode JSON for block: {$blockKey}");
            return $this->getFallbackContent($config['units']);
        }

        return $decoded;
    }

    /**
     * Standard prompt used for ALL blocks
     */
    private function getStandardPrompt(string $niche, string $siteName, ?string $uniqueFocus): string
    {
        $currentMonth = now()->locale('nl')->translatedFormat('F');
        $currentYear = now()->year;

        $uniqueFocusContext = $uniqueFocus
            ? "UNIQUE FOCUS: {$uniqueFocus} (verwerk subtiel in content)"
            : '';

        // Fetch active informatie artikelen for internal linking
        $infoPages = InformationPage::active()->ordered()->get(['title', 'slug']);
        $infoLinksContext = '';
        if ($infoPages->isNotEmpty()) {
            $linksList = $infoPages->map(fn($page) => "- <a href=\"/informatie/{$page->slug}\">{$page->title}</a>")->join("\n");
            $infoLinksContext = "\n\nINTERNE LINKS (gebruik max 1-2 per SEO block als HTML <a> tags):\n{$linksList}\nVoorbeeld: <a href=\"/informatie/voorbeeld\">Anchor tekst</a>";
        }

        return <<<PROMPT
SEO COPYWRITER VOOR: {$siteName}
NICHE: {$niche}
{$uniqueFocusContext}
DATUM: {$currentMonth} {$currentYear}{$infoLinksContext}

SCHRIJFSTIJL (altijd)
- Schrijf alsof je het aan één persoon uitlegt: "je/jij"
- Korte zinnen. Geen brochuretaal. Geen marketingjargon
- Elke alinea bevat minstens 1 concreet detail (situatie, beperking, keuze-criterium of voorbeeld)

VERMIJD (niet gebruiken)
- Woorden: ideaal, handig, perfect, uitstekend, geweldig, optimaal, essentieel, cruciaal
- Zinnen: "na een lange werkdag", "voor veel mensen", "voor velen"
- Testclaims: "wij hebben getest", "onze testresultaten"

WEL DOEN
- Concreet benoemen wat iemand merkt/ervaart of waar iemand op let
- Normale productspecificaties/voorbeelden zijn prima (bijv. "12-16 km/u", "1,5m loopvlak", "voor gezin van 4")
- Geen percentages of 'onderzoek zegt'-claims

FUNNEL (alleen als het block een CTA vraagt)
- Eindig met verwijzing naar /producten
- Geen verwijzing naar blogs/reviews/top5 als eindbestemming

OUTPUT
- Return ONLY valid JSON
- Exact de gevraagde keys, geen extra tekst, geen Markdown, geen HTML

PROMPT;
    }

    /**
     * Block-specific prompt
     */
    private function getBlockPrompt(array $config, string $niche): string
    {
        $units = implode(', ', $config['units']);
        $role = $config['role'];
        $forbidden = isset($config['forbidden']) ? '- ' . implode("\n- ", $config['forbidden']) : 'Geen extra beperkingen';
        $mustTreat = isset($config['must_treat']) ? '- ' . implode("\n- ", $config['must_treat']) : 'Geen verplichte elementen';

        $lengthInstructions = '';
        if (isset($config['lengths'])) {
            foreach ($config['lengths'] as $unit => $length) {
                $lengthInstructions .= "- {$unit}: {$length}\n";
            }
        }

        return <<<PROMPT
═══════════════════════════════════════════════════════════════════
BLOCK OPDRACHT
═══════════════════════════════════════════════════════════════════

ROL: {$role}
NICHE: {$niche}

VERPLICHTE BEHANDELING:
{$mustTreat}

VERBODEN IN DIT BLOK:
{$forbidden}

LENGTES:
{$lengthInstructions}

STIJL: {$config['style']}

═══════════════════════════════════════════════════════════════════
OUTPUT
═══════════════════════════════════════════════════════════════════

Return JSON met keys: {$units}
Elke key = plain text (geen HTML/Markdown)

Voorbeeld:
{
  "title": "Jouw titel hier",
  "text": "Jouw tekst hier"
}

Genereer NU de content:
PROMPT;
    }

    /**
     * Clean JSON response from markdown artifacts
     */
    private function cleanJsonResponse(string $content): string
    {
        $content = preg_replace('/^```(?:json)?\s*/', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);
        $content = preg_replace('/^[^\{\[]*/', '', $content);

        if (preg_match('/([\}\]])/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $lastBrace = strrpos($content, '}');
            $lastBracket = strrpos($content, ']');
            $lastPos = max($lastBrace !== false ? $lastBrace : -1, $lastBracket !== false ? $lastBracket : -1);

            if ($lastPos !== -1) {
                $content = substr($content, 0, $lastPos + 1);
            }
        }

        return trim($content);
    }

    /**
     * Fallback content if generation fails
     */
    private function getFallbackContent(array $units): array
    {
        $fallback = [];
        foreach ($units as $unit) {
            $fallback[$unit] = "[Content generatie mislukt - vul handmatig aan]";
        }
        return $fallback;
    }

    /**
     * Block definitions - all 20 blocks
     */
    private function getBlockDefinitions(): array
    {
        return [
            'homepage.hero' => [
                'units' => ['title', 'subtitle'],
                'role' => 'Homepage hero: positionering + belofte + verwachting',
                'forbidden' => [
                    'GEEN "De beste X van [maand] [jaar]"',
                    'GEEN absolute claims ("beste", "nummer 1")',
                    'GEEN vage marketingwoorden zonder context',
                ],
                'must_treat' => [
                    'TITLE: Actie + {niche}. Focus op vergelijken/vinden, niet op claimen.',
                    'SUBTITLE: Leg concreet uit WAT je hier vergelijkt en WAAROP (specs, ervaringen, verschillen).',
                    'Subtitle voegt nieuwe informatie toe t.o.v. title.',
                ],
                'lengths' => [
                    'title' => '60-80 tekens',
                    'subtitle' => '80-120 tekens',
                ],
                'style' => 'Duidelijk, inhoudelijk, geen hype. Focus op keuze maken.',
            ],


            'homepage.info' => [
                'units' => ['title', 'text'],
                'role' => 'Wat doet deze site en hoe helpt dat bij kiezen?',
                'forbidden' => [
                    'GEEN productvoordelen of claims',
                    'GEEN koopaanbevelingen of “beste keuze”-uitspraken',
                    'GEEN testclaims',
                ],
                'must_treat' => [
                    'TITLE: Benoem dat de site helpt bij vergelijken en kiezen in {niche} (20–40 tekens).',
                    'TEXT: Leg uit dat de site inzicht en richting geeft door specificaties, verschillen en ervaringen te bundelen.',
                    'TEXT: Benadruk dat de gebruiker zélf kiest, met hulp van overzicht en context.',
                ],
                'lengths' => [
                    'title' => '20-40 tekens',
                    'text' => '80-100 woorden',
                ],
                'style' => 'Helpend en richtinggevend. Informatief zonder te verkopen.',
            ],

            'homepage.seo1' => [
                'units' => [
                    'title',
                    'intro',
                    'section1_title',
                    'section1_text',
                    'section2_title',
                    'section2_text',
                    'section3_title',
                    'section3_text',
                ],
                'role' => 'Waarom mensen kiezen voor {niche} (context + situaties)',
                'forbidden' => [
                    'GEEN keuzeadvies of koopadvies',
                    'GEEN vergelijkingen tussen modellen',
                    'GEEN claims of testuitspraken',
                    'GEEN superlatieven of marketingtaal',
                ],
                'must_treat' => [
                    'TITLE: Benoem de rol of waarde van {niche} in een concrete context (thuis/dagelijks leven/situatie). Vermijd "waarom mensen X gebruiken" voor obvious producten. (30–50 tekens)',
                    'INTRO: Beschrijf het probleem of de situatie die leidt tot gebruik van {niche}, zonder voordelen op te sommen.',
                    'SECTION 1: Situatiegedreven voordeel. Beschrijf een concrete gebruikssituatie en welk probleem dit product daarin oplost. Voeg 1-2 relevante interne links toe uit de INTERNE LINKS lijst als het past bij de context.',
                    'SECTION 2: Praktisch mechanisme. Leg uit WAT het product mogelijk maakt dat zonder dit product lastig is. Voeg 1-2 relevante interne links toe uit de INTERNE LINKS lijst als het past bij de context.',
                    'SECTION 3: Beperkingen wegnemen. Beschrijf welk veelvoorkomend bezwaar of probleem door dit type product wordt verminderd. Voeg 1-2 relevante interne links toe uit de INTERNE LINKS lijst als het past bij de context.',
                ],
                'lengths' => [
                    'title' => '30-50 tekens',
                    'intro' => '60-80 woorden',
                    'section1_title' => '20-40 tekens',
                    'section1_text' => '180-200 woorden',
                    'section2_title' => '20-40 tekens',
                    'section2_text' => '180-200 woorden',
                    'section3_title' => '20-40 tekens',
                    'section3_text' => '180-200 woorden',
                ],
                'style' => 'Contextueel en concreet. Geen voordelenlijst, maar situaties, oorzaken en effecten.',
            ],


            'homepage.seo2' => [
                'units' => [
                    'title',
                    'intro',
                    'section1_title',
                    'section1_text',
                    'section2_title',
                    'section2_text',
                    'section3_title',
                    'section3_text',
                    'section4_title',
                    'section4_text',
                ],
                'role' => 'Keuzehulp: hoe bepaal je welk type {niche} bij je past?',
                'forbidden' => [
                    'GEEN productvoordelen of pluspunten',
                    'GEEN merk- of modelnamen',
                    'GEEN aanbevelingen of rankings',
                    'GEEN test- of reviewclaims',
                ],
                'must_treat' => [
                    'TITLE: Hoe kies je een {niche}? (30–50 tekens)',
                    'INTRO: Leg uit dat verschillende situaties andere eisen stellen (30–40 woorden).',
                    'SECTION 1: Gebruikssituatie. Beschrijf hoe gebruiksfrequentie en manier van gebruik invloed hebben op de keuze. Voeg 1-2 relevante interne links toe uit de INTERNE LINKS lijst als het past bij de context.',
                    'SECTION 2: Ruimte en omgeving. Leg uit hoe beschikbare ruimte, plaatsing en omgeving meespelen. Voeg 1-2 relevante interne links toe uit de INTERNE LINKS lijst als het past bij de context.',
                    'SECTION 3: Belastbaarheid of capaciteit. Beschrijf welk type gebruiker of gebruiksniveau bepalend is. Voeg 1-2 relevante interne links toe uit de INTERNE LINKS lijst als het past bij de context.',
                    'SECTION 4: Budget en verwachtingen. Leg uit hoe prijs samenhangt met gebruik, zonder bedragen te noemen. Voeg 1-2 relevante interne links toe uit de INTERNE LINKS lijst als het past bij de context.',
                ],
                'lengths' => [
                    'title' => '30-50 tekens',
                    'intro' => '50-70 woorden',
                    'section1_title' => '20-40 tekens',
                    'section1_text' => '180-200 woorden',
                    'section2_title' => '20-40 tekens',
                    'section2_text' => '180-200 woorden',
                    'section3_title' => '20-40 tekens',
                    'section3_text' => '180-200 woorden',
                    'section4_title' => '20-40 tekens',
                    'section4_text' => '180-200 woorden',
                ],
                'style' => 'Beslisgericht en neutraal. Schrijf in “als-dan”-logica zonder oordeel.',
            ],


            'homepage.faq_1' => [
                'units' => ['question', 'answer'],
                'role' => 'FAQ: Voor wie is {niche} geschikt?',
                'forbidden' => [
                    'GEEN onderhoud, installatie of techniek',
                    'GEEN gebruikstoepassingen',
                    'GEEN koopadvies of aanbevelingen',
                ],
                'must_treat' => [
                    'QUESTION: Voor welke situaties of gebruikers is {niche} bedoeld?',
                    'ANSWER: Beschrijf concrete situaties waarin dit type product logisch is om te overwegen.',
                    'ANSWER: Gebruik herkenbare context (ruimte, huishouden, ervaring, frequentie), geen oordeel.',
                ],
                'lengths' => [
                    'question' => '60-100 tekens',
                    'answer' => '60-80 woorden',
                ],
                'style' => 'Verhelderend en feitelijk. Beschrijvend, niet overtuigend.',
            ],


            'homepage.faq_2' => [
                'units' => ['question', 'answer'],
                'role' => 'FAQ: Onderhoud en praktische zorg',
                'forbidden' => [
                    'GEEN geschiktheid of doelgroep',
                    'GEEN gebruikssituaties',
                    'GEEN marketingtaal',
                ],
                'must_treat' => [
                    'QUESTION: Hoe onderhoud je {niche} in de praktijk?',
                    'ANSWER: Beschrijf terugkerende onderhoudshandelingen en aandachtspunten.',
                    'ANSWER: Benoem acties met tijdsaanduiding (dagelijks, wekelijks, periodiek) zonder cijfers of schema’s.',
                ],
                'lengths' => [
                    'question' => '60-100 tekens',
                    'answer' => '60-80 woorden',
                ],
                'style' => 'Praktisch en technisch, maar laagdrempelig.',
            ],


            'homepage.faq_3' => [
                'units' => ['question', 'answer'],
                'role' => 'FAQ: Toepassingen en gebruik',
                'forbidden' => [
                    'GEEN geschiktheid of doelgroep',
                    'GEEN onderhoud of techniek',
                    'GEEN voordelen of pluspunten',
                ],
                'must_treat' => [
                    'QUESTION: Waarvoor wordt {niche} meestal gebruikt?',
                    'ANSWER: Geef meerdere concrete gebruiksscenario’s of toepassingen.',
                    'ANSWER: Focus op wat mensen ermee doen, niet waarom het beter is.',
                ],
                'lengths' => [
                    'question' => '60-100 tekens',
                    'answer' => '60-80 woorden',
                ],
                'style' => 'Beschrijvend en concreet. Voorbeelden zonder oordeel.',
            ],

            // PRODUCTEN BLOCKS
            'producten_index_hero_titel' => [
                'units' => ['title'],
                'role' => 'Productenoverzicht: positionering + actie',
                'forbidden' => [
                    'GEEN superlatieven of rankings',
                    'GEEN tijdsgebonden claims',
                    'GEEN koopadvies of aanbevelingen',
                ],
                'must_treat' => [
                    'TITLE: Actiegericht overzicht van {niche}, met nadruk op vergelijken en overzicht.',
                    'TITLE: Gebruik een werkwoord (Vergelijk / Bekijk / Ontdek) en benoem expliciet {niche}.',
                ],
                'lengths' => [
                    'title' => '40-60 tekens',
                ],
                'style' => 'Functioneel en duidelijk. Geen marketingtaal, puur overzicht.',
            ],
        
            'producten_index_info_blok_1' => [
                'units' => ['title', 'text'],
                'role' => 'Waarom deze pagina gebruiken om te vergelijken?',
                'forbidden' => [
                    'GEEN productvoordelen of eigenschappen',
                    'GEEN koopadvies of aanbevelingen',
                    'GEEN test- of reviewclaims',
                ],
                'must_treat' => [
                    'TITLE: Benoem waarom deze pagina geschikt is om {niche} te vergelijken (30–50 tekens).',
                    'TEXT: Leg uit HOE de pagina werkt: filters, sortering, specificaties naast elkaar.',
                    'TEXT: Beschrijf het proces (bekijken, filteren, vergelijken), niet het resultaat.',
                ],
                'lengths' => [
                    'title' => '30-50 tekens',
                    'text' => '70-90 woorden',
                ],
                'style' => 'Uitleggend en neutraal. Focus op functionaliteit, niet op overtuigen.',
            ],

            'producten_index_info_blok_2' => [
                'units' => ['title', 'text'],
                'role' => 'Vergelijkcriteria: waar let je op bij {niche}?',
                'forbidden' => [
                    'GEEN productvoordelen of aanbevelingen',
                    'GEEN specifieke merken of modellen',
                    'GEEN koopadvies',
                ],
                'must_treat' => [
                    'TITLE: Benoem dat dit gaat over aandachtspunten bij het vergelijken (30–50 tekens).',
                    'TEXT: Beschrijf objectieve criteria waarop {niche} van elkaar verschillen.',
                    'TEXT: Focus op meetbare of observeerbare aspecten (gebruik, ruimte, capaciteit, uitvoering), zonder oordeel.',
                ],
                'lengths' => [
                    'title' => '30-50 tekens',
                    'text' => '70-90 woorden',
                ],
                'style' => 'Praktisch en informatief. Helpt structureren, niet beslissen.',
            ],

            'producten_top_hero_titel' => [
                'units' => ['title'],
                'role' => 'Topselectie overzicht: actuele context + afbakening',
                'forbidden' => [
                    'GEEN superlatieven buiten de rangorde (geen “beste ooit”, “nummer 1 keuze”)',
                    'GEEN koopbelofte of aanbevelingstaal',
                ],
                'must_treat' => [
                    'TITLE: Benoem dat het om een Top 5 selectie van {niche} gaat.',
                    'TITLE: Voeg maand en jaar toe voor actualiteit en context.',
                    'TITLE: Houd het beschrijvend en feitelijk, geen claimende formulering.',
                ],
                'lengths' => [
                    'title' => '50-70 tekens',
                ],
                'style' => 'Zakelijk en actueel. Geeft context, geen oordeel.',
            ],

            'producten_top_seo_blok' => [
                'units' => [
                    'title',
                    'intro',
                    'section1_title',
                    'section1_text',
                    'section2_title',
                    'section2_text',
                    'section3_title',
                    'section3_text',
                ],
                'role' => 'Uitleg selectie: op basis waarvan deze Top 5 is samengesteld',
                'forbidden' => [
                    'GEEN test-, meet- of reviewclaims',
                    'GEEN aanbevelingen of koopadvies',
                    'GEEN superlatieven of waarderingen',
                    'GEEN merk- of modelvoorkeur uitspreken',
                ],
                'must_treat' => [
                    'TITLE: Leg uit dat dit blok de selectiecriteria van de Top 5 beschrijft (30–50 tekens).',
                    'INTRO: Beschrijf in algemene zin hoe een selectie tot stand komt (bronnen, vergelijking, afbakening), zonder claims.',
                    'SECTION 1: Objectief criterium 1 dat relevant is voor het vergelijken van {niche}. Voeg 1-2 relevante interne links toe uit de INTERNE LINKS lijst als het past bij de context.',
                    'SECTION 2: Objectief criterium 2 dat invloed heeft op de samenstelling van de Top 5. Voeg 1-2 relevante interne links toe uit de INTERNE LINKS lijst als het past bij de context.',
                    'SECTION 3: Objectief criterium 3 dat zorgt voor balans binnen de selectie. Voeg 1-2 relevante interne links toe uit de INTERNE LINKS lijst als het past bij de context.',
                ],
                'lengths' => [
                    'title' => '30-50 tekens',
                    'intro' => '60-80 woorden',
                    'section1_title' => '30-50 tekens',
                    'section1_text' => '180-200 woorden',
                    'section2_title' => '30-50 tekens',
                    'section2_text' => '180-200 woorden',
                    'section3_title' => '30-50 tekens',
                    'section3_text' => '180-200 woorden',
                ],
                'style' => 'Transparant en beschrijvend. Leg het selectieproces uit zonder oordeel of aanbeveling.',
            ],


            // MERKEN BLOCKS
            'merken_index_hero_titel' => [
                'units' => ['title', 'subtitle'],
                'role' => 'Merkenoverzicht: positionering + uitleg',
                'forbidden' => [
                    'GEEN superlatieven of aanbevelingen',
                    'GEEN koopadvies of voorkeuren',
                    'GEEN marketingtaal',
                ],
                'must_treat' => [
                    'TITLE: Benoem dat deze pagina merken binnen {niche} vergelijkt.',
                    'TITLE: Gebruik een actiegericht werkwoord (Vergelijk / Bekijk) en expliciet {niche}.',
                    'SUBTITLE: Leg uit waarom merken vergelijken zinvol is binnen deze productcategorie.',
                    'SUBTITLE: Focus op verschillen tussen merken, niet op kwaliteitsoordelen.',
                ],
                'lengths' => [
                    'title' => '40-60 tekens',
                    'subtitle' => '100-150 tekens',
                ],
                'style' => 'Functioneel en neutraal. Gericht op overzicht en inzicht.',
            ],


            'merken_index_info_blok' => [
                'units' => [
                    'title',
                    'intro',
                    'section1_title',
                    'section1_text',
                    'section2_title',
                    'section2_text',
                    'section3_title',
                    'section3_text',
                ],
                'role' => 'Uitleg: welke rol speelt merk bij {niche}?',
                'forbidden' => [
                    'GEEN aanbevelingen of voorkeuren',
                    'GEEN kwaliteitsclaims of waarderingen',
                    'GEEN koopadvies',
                ],
                'must_treat' => [
                    'TITLE: Introduceer het thema merkverschillen binnen {niche} (30–50 tekens).',
                    'INTRO: Leg uit dat merken binnen deze categorie verschillen in focus en aanpak.',
                    'SECTION 1: Aspect 1 waarin merken zich onderscheiden (bijv. positionering of doelgroep). Voeg 1-2 relevante interne links toe uit de INTERNE LINKS lijst als het past bij de context.',
                    'SECTION 2: Aspect 2 waarin merken structureel verschillen (bijv. aanbod of lijnbreedte). Voeg 1-2 relevante interne links toe uit de INTERNE LINKS lijst als het past bij de context.',
                    'SECTION 3: Aspect 3 dat invloed heeft op de ervaring of verwachting bij het kiezen van een merk. Voeg 1-2 relevante interne links toe uit de INTERNE LINKS lijst als het past bij de context.',
                ],
                'lengths' => [
                    'title' => '30-50 tekens',
                    'intro' => '60-80 woorden',
                    'section1_title' => '30-50 tekens',
                    'section1_text' => '180-200 woorden',
                    'section2_title' => '30-50 tekens',
                    'section2_text' => '180-200 woorden',
                    'section3_title' => '30-50 tekens',
                    'section3_text' => '180-200 woorden',
                ],
                'style' => 'Objectief en beschrijvend. Vergelijkend zonder oordeel.',
            ],

            // REVIEWS BLOCKS
            'reviews.hero' => [
                'units' => ['title', 'subtitle'],
                'role' => 'Reviewsoverzicht: context + verwachting',
                'forbidden' => [
                    'GEEN aanbevelingen of rankings',
                    'GEEN superlatieven',
                    'GEEN koopadvies',
                ],
                'must_treat' => [
                    'TITLE: Benoem dat dit een overzicht is van reviews binnen {niche}.',
                    'SUBTITLE: Leg uit wat iemand uit reviews haalt (ervaringen, aandachtspunten, verschillen), zonder oordeel.',
                ],
                'lengths' => [
                    'title' => '40-60 tekens',
                    'subtitle' => '100-150 tekens',
                ],
                'style' => 'Toegankelijk en informatief. Verwachtingsmanagement, geen verkoop.',
            ],


            'reviews_index_intro' => [
                'units' => ['title', 'text'],
                'role' => 'Waarom reviews lezen?',
                'forbidden' => [
                    'GEEN testclaims',
                    'GEEN koopadvies',
                    'GEEN productvoordelen',
                ],
                'must_treat' => [
                    'TITLE: Introduceer het nut van reviews bij het oriënteren (30–50 tekens).',
                    'TEXT: Leg uit welke inzichten reviews geven (ervaring, gebruik, beperkingen), zonder aanbeveling.',
                ],
                'lengths' => [
                    'title' => '30-50 tekens',
                    'text' => '80-100 woorden',
                ],
                'style' => 'Nuttig en neutraal. Helpt begrijpen, niet kiezen.',
            ],


            'reviews_index_seo_blok' => [
                'units' => [
                    'title',
                    'intro',
                    'section1_title',
                    'section1_text',
                    'section2_title',
                    'section2_text',
                    'section3_title',
                    'section3_text',
                ],
                'role' => 'Uitleg beoordelingskader voor reviews',
                'forbidden' => [
                    'GEEN test- of meetclaims',
                    'GEEN aanbevelingen of voorkeuren',
                    'GEEN merk- of modelvergelijkingen',
                ],
                'must_treat' => [
                    'TITLE: Leg uit dat dit blok beschrijft waarop reviews zijn gebaseerd (30–50 tekens).',
                    'INTRO: Beschrijf in algemene zin welke aspecten terugkomen in reviews, zonder claims.',
                    'SECTION 1: Aspect 1 dat vaak terugkomt in gebruikerservaringen. Voeg 1-2 relevante interne links toe uit de INTERNE LINKS lijst als het past bij de context.',
                    'SECTION 2: Aspect 2 dat invloed heeft op dagelijks gebruik. Voeg 1-2 relevante interne links toe uit de INTERNE LINKS lijst als het past bij de context.',
                    'SECTION 3: Aspect 3 dat verwachtingen en praktijk met elkaar verbindt. Voeg 1-2 relevante interne links toe uit de INTERNE LINKS lijst als het past bij de context.',
                ],
                'lengths' => [
                    'title' => '30-50 tekens',
                    'intro' => '60-80 woorden',
                    'section1_title' => '30-50 tekens',
                    'section1_text' => '180-200 woorden',
                    'section2_title' => '30-50 tekens',
                    'section2_text' => '180-200 woorden',
                    'section3_title' => '30-50 tekens',
                    'section3_text' => '180-200 woorden',
                ],
                'style' => 'Transparant en beschrijvend. Objectief kader, geen oordeel.',
            ],

            // BLOGS BLOCKS
            'blogs.hero' => [
                'units' => ['title', 'subtitle'],
                'role' => 'Blogoverzicht: educatie + context',
                'forbidden' => [
                    'GEEN koopadvies of aanbevelingen',
                    'GEEN superlatieven of claims',
                    'GEEN marketingtaal',
                ],
                'must_treat' => [
                    'TITLE: Benoem dat dit een overzicht is van blogs over {niche}.',
                    'SUBTITLE: Leg uit welk type kennis en inzichten blogs bieden (uitleg, verdieping, context).',
                ],
                'lengths' => [
                    'title' => '40-60 tekens',
                    'subtitle' => '100-180 tekens',
                ],
                'style' => 'Informatief en uitnodigend. Focus op leren, niet verkopen.',
            ],


            'blogs.intro' => [
                'units' => ['title', 'text'],
                'role' => 'Waarom blogs lezen binnen deze niche?',
                'forbidden' => [
                    'GEEN koopadvies',
                    'GEEN productvoordelen',
                    'GEEN testclaims',
                ],
                'must_treat' => [
                    'TITLE: Introduceer het doel van blogs in één korte zin (20–40 tekens).',
                    'TEXT: Leg uit welke rol blogs spelen bij oriënteren en begrijpen, zonder richting te geven.',
                ],
                'lengths' => [
                    'title' => '20-40 tekens',
                    'text' => '70-90 woorden',
                ],
                'style' => 'Toegankelijk en verhelderend. Neutraal van toon.',
            ],


            'blogs.seo' => [
                'units' => [
                    'title',
                    'intro',
                    'section1_title',
                    'section1_text',
                    'section2_title',
                    'section2_text',
                    'section3_title',
                    'section3_text',
                ],
                'role' => 'Overzicht van blogonderwerpen binnen {niche}',
                'forbidden' => [
                    'GEEN koopadvies of aanbevelingen',
                    'GEEN merk- of modelvoorkeuren',
                    'GEEN test- of reviewclaims',
                ],
                'must_treat' => [
                    'TITLE: Leg uit dat dit blok beschrijft welke onderwerpen aan bod komen (30-50 tekens).',
                    'INTRO: Beschrijf in algemene zin welke kennisgebieden of themas terugkomen in blogs.',
                    'SECTION 1: Thema 1 dat verdieping geeft in gebruik of achtergrond. Voeg 1-2 relevante interne links toe uit de INTERNE LINKS lijst als het past bij de context.',
                    'SECTION 2: Thema 2 dat inzicht geeft in verschillen of aandachtspunten. Voeg 1-2 relevante interne links toe uit de INTERNE LINKS lijst als het past bij de context.',
                    'SECTION 3: Thema 3 dat helpt bij begrijpen of oriënteren. Voeg 1-2 relevante interne links toe uit de INTERNE LINKS lijst als het past bij de context.',
                ],
                'lengths' => [
                    'title' => '30-50 tekens',
                    'intro' => '60-80 woorden',
                    'section1_title' => '30-50 tekens',
                    'section1_text' => '180-200 woorden',
                    'section2_title' => '30-50 tekens',
                    'section2_text' => '180-200 woorden',
                    'section3_title' => '30-50 tekens',
                    'section3_text' => '180-200 woorden',
                ],
                'style' => 'Informatief en gestructureerd. Overzicht zonder oordeel.',
            ],

        ];
    }
}
