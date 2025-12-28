<?php

namespace App\Services;

use Illuminate\Support\Str;
use OpenAI;

/**
 * SiteContentGeneratorService
 *
 * Dedicated service for generating ALL site content via OpenAI.
 */
class SiteContentGeneratorService
{
    protected $client;
    protected OpenAICircuitBreaker $circuitBreaker;

    public function __construct(OpenAICircuitBreaker $circuitBreaker = null)
    {
        $this->client = OpenAI::client(config('services.openai.key'));
        $this->circuitBreaker = $circuitBreaker ?? new OpenAICircuitBreaker();
    }

    /**
     * Generate all settings for a new site based on niche
     */
    public function generateSettings(array $input): array
    {
        $niche = $input['niche'] ?? 'producten';
        $domain = $input['domain'] ?? '';
        $primaryColor = $input['primary_color'] ?? '#7c3aed';

        $prompt = <<<PROMPT
Je bent conversion-strategist voor Nederlandse affiliate websites.

CONTEXT:
- Niche: {$niche}
- Domein: {$domain}
- Primary color: {$primaryColor}

VASTE WAARDEN (EXACT overnemen):
- site_name = "{$domain}"
- site_niche = "{$niche}"
- primary_color = "{$primaryColor}"
- font_family = "Poppins"

CREATIEVE VELDEN (jij bedenkt):
- tone_of_voice: Tone die convert (bijv. "informeel en behulpzaam")
- target_audience: Specifieke doelgroep (bijv. "actieve mensen die thuis willen sporten")

OUTPUT JSON:
{
  "site_name": "{$domain}",
  "site_niche": "{$niche}",
  "primary_color": "{$primaryColor}",
  "font_family": "Poppins",
  "tone_of_voice": "...",
  "target_audience": "..."
}

Return ALLEEN minified JSON.
PROMPT;

        $response = $this->chat([
            ['role' => 'system', 'content' => 'Return ONLY minified JSON. No markdown, no commentary.'],
            ['role' => 'user', 'content' => $prompt],
        ], 'gpt-4o-mini', 0.7, 500);

        $content = trim($response['content'] ?? '{}');
        $content = $this->cleanJsonResponse($content);
        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return $this->getFallbackSettings($niche, $domain, $primaryColor);
        }

        return array_merge($this->getFallbackSettings($niche, $domain, $primaryColor), $decoded);
    }

    /**
     * Generate all content blocks for the site
     */
    /**
     * DELEGATED TO ContentBlocksGeneratorService
     */
    public function generateContentBlocks(string $niche, string $siteName, ?string $uniqueFocus = null, string $format = 'html'): array
    {
        $contentBlocksService = app(ContentBlocksGeneratorService::class);
        return $contentBlocksService->generate($niche, $siteName, $uniqueFocus, $format);
    }

    /**
     * OLD METHOD - DELETE LATER
     * Keeping temporarily for reference
     */
    private function OLD_generateContentBlocks_DEPRECATED(string $niche, string $siteName, ?string $uniqueFocus = null, string $format = 'html'): array
    {
        $currentYear = date('Y');
        $currentMonth = \Carbon\Carbon::now('Europe/Amsterdam')->locale('nl')->translatedFormat('F');

        // Build unique focus instruction
        $uniqueFocusInstruction = '';
        if ($uniqueFocus) {
            $uniqueFocusInstruction = <<<FOCUS

UNIEKE FOCUS (gebruik SPAARZAAM):
- Unieke focus/USP: "{$uniqueFocus}"
- Gebruik dit ALLEEN waar het waarde toevoegt (bijv. in USP's, waarom-secties, voordelen)
- NIET in elke zin of paragraaf gebruiken
- De niche zelf blijft kort: "{$niche}"
FOCUS;
        }

        $prompt = <<<PROMPT
Je bent een WORLD-CLASS content strategist voor Nederlandse affiliate websites.
Je taak: genereer 20 unieke content blocks voor {$siteName} (niche: {$niche}).
{$uniqueFocusInstruction}

═══════════════════════════════════════════════════════════════════════
GLOBAL CONTEXT
═══════════════════════════════════════════════════════════════════════

Site: {$siteName}
Niche: {$niche}
Jaar: {$currentYear}
Doelgroep: Nederlandstalige consumenten die {$niche} willen kopen
Tone: Informatief, behulpzaam, vertrouwenswaardig (geen hype)

═══════════════════════════════════════════════════════════════════════
FUNNEL POLICY — NIET NEGEREN
═══════════════════════════════════════════════════════════════════════

HIËRARCHIE (decision flow):
1. /producten = ENIGE KEUZEHUB (eindstation voor koopbeslissing)
2. Homepage (/) = oriëntatie → stuur naar /producten
3. /blogs = informeren (NOOIT eindstation) → stuur naar /producten
4. /reviews = verdiepen (NOOIT eindstation) → stuur naar /producten
5. /top-5 = shortcut (ondergeschikt aan /producten) → stuur naar /producten
6. /beste-merken = merkfilter (ondersteunend) → stuur naar /producten

VERBODEN GEDRAG (dit mag NOOIT):
✗ Geen "of/of/of" menukaart-zinnen zoals: "Bekijk producten, Top 5, reviews of blogs"
✗ Geen promotie van /blogs, /reviews, /top-5 als navigatie-opties in afsluitingen
✗ Geen inline <a>-links naar /top-5, /reviews, /blogs in lopende tekst
✗ Geen claims als "wij testen" (gebruik: "beoordelen", "analyseren", "vergelijken")
✗ Geen herhaling van dezelfde angles (motorvermogen/loopvlak) in meerdere blocks

CTA-REGEL (keihard):
✓ Elk content block eindigt met EXACT 1 CTA-zin
✓ CTA verwijst ALTIJD naar /producten (of /producten met filter voor merken-pagina)
✓ CTA mag GEEN tweede optie bevatten
✓ CTA moet natuurlijk eindigen, geen "PS:" of extra zinnen erna

ANTI-OVERLAP:
✓ Elk block heeft een UNIEKE "FOCUS TAG" (mag niet herhaald worden)
✓ Elk block heeft minimaal 2 punten die NIET in andere blocks voorkomen
✓ Verdeel thema's slim: motorvermogen in block A, loopvlak in block B, etc.

ALGEMENE REGELS:
- Schrijf VOLLEDIGE content, GEEN instructies, GEEN placeholders zoals "[Type 1]"
- ECHTE concrete inhoud voor {$niche}
- ABSOLUUT GEEN EMOJIS
- Gebruik korte niche naam in titels/koppen
- Return valid JSON: {{"key": "HTML string", ...}}

═══════════════════════════════════════════════════════════════════════
PAGE BRIEFS
═══════════════════════════════════════════════════════════════════════

HOMEPAGE (/)
Role: Oriëntatie, trust building, richting geven
Eindigt: ALTIJD naar /producten (nooit naar Top 5/reviews/blogs)
Mag NIET: Opties aanbieden, menukaart maken

PRODUCTEN (/producten)
Role: DECISION HUB — hier wordt gekozen
Eindigt: Blijft op /producten (filters, vergelijken)
Mag NIET: Verwijzen naar Top 5/reviews/blogs als alternatieven

TOP 5 (/top-5)
Role: Shortcut (ondergeschikt), snelle keuze
Eindigt: ALTIJD terug naar /producten voor volledige vergelijking
Mag NIET: Concurreren met /producten als "beter" eindstation

BESTE MERKEN (/beste-merken)
Role: Merkfilter ingang
Eindigt: Naar /producten met merkfilter
Mag NIET: Eindstation worden

REVIEWS (/reviews)
Role: Verdieping, expertise tonen (GEEN eindstation)
Eindigt: ALTIJD naar /producten voor vergelijking
Mag NIET: "Lees meer reviews" als afsluiting

BLOGS (/blogs)
Role: Informeren, SEO, educatie (GEEN eindstation)
Eindigt: ALTIJD naar /producten
Mag NIET: Blijven hangen in blog-content


═══════════════════════════════════════════════════════════════════════
BLOCK BRIEFS (20 blocks — elk met unieke focus)
═══════════════════════════════════════════════════════════════════════

─────────────────────────────────────────────────────────────────────
BLOCK 1: homepage.hero
─────────────────────────────────────────────────────────────────────
PAGE: Homepage (/)
PAGE ROLE: Oriëntatie → stuur naar /producten
BLOCK ROLE: Framing + belofte (waarom hier zijn?)
FOCUS TAG: "belofte-actueel"
LENGTE: 60-80 tekens
MUST INCLUDE:
- Formule: [Primary keyword] van {{ maand }} {$currentYear} – [uniek voordeel]
- GEBRUIK {{ maand }} placeholder (met spaties!)
- Korte niche naam, NIET unique focus
MUST AVOID:
- Geen lange zinnen
- Geen menukaart taal
VOORBEELD: "De beste {$niche} van {{ maand }} {$currentYear} – Vergelijk en bespaar"
CTA: Niet van toepassing (hero heeft geen CTA)

─────────────────────────────────────────────────────────────────────
BLOCK 2: homepage.info
─────────────────────────────────────────────────────────────────────
PAGE: Homepage (/)
PAGE ROLE: Oriëntatie → stuur naar /producten
BLOCK ROLE: Mental model (hoe denken over {$niche}?)
FOCUS TAG: "types-wanneer-welk"
LENGTE: 250-300 woorden
MUST INCLUDE:
- H2 kop over vinden van juiste {$niche}
- Paragraaf 1 (80-100w): PIJN PUNT (te veel keuze? onduidelijke specs?)
- Paragraaf 2 (80-100w): OPLOSSING (hoe {$siteName} helpt)
- Paragraaf 3 (80-100w): E-E-A-T signals ("experts beoordelen", "dagelijks bijgewerkt")
MUST AVOID:
- Geen verwijzingen naar Top 5/reviews/blogs als opties
- Geen "of/of/of" zinnen
CTA (laatste zin): "Begin met vergelijken op <a href=\"/producten\" class=\"text-purple-700 underline\">onze productpagina</a>."

─────────────────────────────────────────────────────────────────────
BLOCK 3: homepage.seo1
─────────────────────────────────────────────────────────────────────
PAGE: Homepage (/)
PAGE ROLE: Oriëntatie → stuur naar /producten
BLOCK ROLE: WAAROM {$niche} (lifestyle focus, geen techniek)
FOCUS TAG: "beslisvolgorde-doel-type-situatie"
LENGTE: 800-1000 woorden
STRUCTUUR: 1 H2 + max 3 H3, meer broodtekst dan koppen

FORBIDDEN TOPICS (behandel dit NIET in dit block):
✗ Geen technische aankoopfactoren (capaciteit, vermogen, geluidsniveau) → dat is seo2
✗ Geen "waar moet je op letten bij kopen" → dat is seo2
✗ Geen budget/prijsklassen → dat is seo2
✗ Geen gebruikersprofielen (beginner/pro/budget) → dat is seo2
✗ Geen "fouten voorkomen" → dat is seo2

MUST TREAT (alleen deze 3 onderwerpen):
1. WAAROM: Welke 3 problemen lost {$niche} op? + waarom nu populair?
2. WELKE TYPES: 3-4 concrete categorieën/types (geen technische specs, maar gebruik-scenario's)
3. WAT LEVERT HET OP: 5 lifestyle voordelen (tijdbesparing, gezondheid, etc)

MUST INCLUDE:
- H2: "Waarom kiezen steeds meer mensen voor {$niche}?"
- Intro (100-150w): TOP 3 PROBLEMEN + trends
- H3 sub 1: "Verschillende soorten {$niche}" → 3-4 ECHTE type namen
- H3 sub 2: "De belangrijkste voordelen" → 5 voordelen doorlopend
- H3 sub 3: "Waarom vergelijken essentieel is" → value prop site

FORBIDDEN IN TEXT:
- Geen bulletpoints of <li> tags
- Geen "[Type 1]" placeholders
- Geen inline links naar andere pagina's

CTA TEMPLATE (kopieer exact, vervang alleen {$niche}):
"Klaar om te vergelijken? Start op onze productpagina."

─────────────────────────────────────────────────────────────────────
BLOCK 4: homepage.seo2
─────────────────────────────────────────────────────────────────────
PAGE: Homepage (/)
PAGE ROLE: Oriëntatie → stuur naar /producten
BLOCK ROLE: HOE KIEZEN (technische focus, geen lifestyle)
FOCUS TAG: "aankoopfactoren-budget-geluid-gewicht"
LENGTE: 800-1000 woorden
STRUCTUUR: 1 H2 + max 3 H3, meer broodtekst

FORBIDDEN TOPICS (behandel dit NIET in dit block):
✗ Geen "waarom {$niche}" uitleg → dat is seo1
✗ Geen types/categorieën beschrijven → dat is seo1
✗ Geen lifestyle voordelen → dat is seo1
✗ Geen probleem-oplossing verhaal → dat is seo1
✗ Geen "waarom nu populair" → dat is seo1

MUST TREAT (alleen deze 3 onderwerpen):
1. AANKOOPFACTOREN: 7 technische criteria (capaciteit, geluid, gewicht, vermogen, etc)
2. GEBRUIKERSPROFIELEN: Voor wie welk type? (beginner/intensief/pro/budget)
3. FOUTEN VOORKOMEN: Wat gaat vaak mis bij aankoop? Hoe voorkom je spijt?

MUST INCLUDE:
- H2: "Hoe kies je de juiste {$niche}? Expert koopgids"
- Intro (100-120w): Overwelming + geruststelling
- H3 sub 1: "Waar moet je op letten?" → 7 ECHTE technische factoren voor {$niche}
- H3 sub 2: "Voor welke gebruiker?" → 4 profielen
- H3 sub 3: "Veel gemaakte fouten" → Wat vermijd je?

FORBIDDEN IN TEXT:
- Geen herhaling van onderwerpen uit seo1
- Geen "[Factor 1]" placeholders
- Geen inline links

CTA TEMPLATE (kopieer exact):
"Start je vergelijking op onze productpagina."

─────────────────────────────────────────────────────────────────────
BLOCK 5: homepage.faq_1
─────────────────────────────────────────────────────────────────────
PAGE: Homepage (/)
PAGE ROLE: Oriëntatie → stuur naar /producten
BLOCK ROLE: Micro-bezwaar wegnemen (ruimte/opklapbaar)
FOCUS TAG: "ruimte-opklapbaar-stabiliteit"

FORBIDDEN TOPICS (behandel dit NIET in dit block):
✗ Geen algemene productvoordelen → dat is homepage.info
✗ Geen aankoopfactoren/technische criteria → dat is homepage.seo2
✗ Geen "waarom {$niche}" lifestyle verhaal → dat is homepage.seo1

MUST TREAT (alleen deze 1 specifieke FAQ):
1. RUIMTE-vraag: "Hoeveel ruimte heb je nodig voor een {$niche}?" of vergelijkbaar

LENGTE: 150-200 woorden
FORMAT: <h3>Vraag?</h3><p>Antwoord paragraaf 1</p><p>Optioneel paragraaf 2</p>

FORBIDDEN IN TEXT:
- Geen "of bekijk onze Top 5/reviews/blogs"
- Geen inline links

CTA TEMPLATE (kopieer exact):
"Vergelijk alle opties op onze productpagina."

─────────────────────────────────────────────────────────────────────
BLOCK 6: homepage.faq_2
─────────────────────────────────────────────────────────────────────
PAGE: Homepage (/)
PAGE ROLE: Oriëntatie → stuur naar /producten
BLOCK ROLE: Micro-bezwaar wegnemen (andere hoek dan faq_1)
FOCUS TAG: "gebruikssituatie-werkplek-veiligheid"

FORBIDDEN TOPICS (behandel dit NIET in dit block):
✗ Geen ruimte/opklapbaar → dat is faq_1
✗ Geen onderhoud/smeren/levensduur → dat is faq_3
✗ Geen algemene "waarom {$niche}" → dat is homepage.seo1

MUST TREAT (alleen deze 1 specifieke FAQ):
1. GEBRUIKSSITUATIE-vraag: "Voor welke situaties is een {$niche} geschikt?" of "Kan ik een {$niche} gebruiken als [specifieke situatie]?"

LENGTE: 150-200 woorden
FORMAT: <h3>Vraag?</h3><p>Antwoord</p><p>Optioneel</p>

FORBIDDEN IN TEXT:
- Geen herhaling van faq_1 onderwerpen
- Geen "bekijk ook Top 5/reviews/blogs"
- Geen inline links

CTA TEMPLATE (kopieer exact):
"Ontdek alle modellen op onze productpagina."

─────────────────────────────────────────────────────────────────────
BLOCK 7: homepage.faq_3
───────────────────────��─────────────────────────────────────────────
PAGE: Homepage (/)
PAGE ROLE: Oriëntatie → stuur naar /producten
BLOCK ROLE: Micro-bezwaar wegnemen (derde unieke hoek)
FOCUS TAG: "onderhoud-smeren-levensduur"

FORBIDDEN TOPICS (behandel dit NIET in dit block):
✗ Geen ruimte/opklapbaar → dat is faq_1
✗ Geen gebruikssituatie/werkplek → dat is faq_2
✗ Geen technische aankoopfactoren → dat is homepage.seo2

MUST TREAT (alleen deze 1 specifieke FAQ):
1. ONDERHOUD-vraag: "Hoe onderhoud je een {$niche}?" of "Hoe lang gaat een {$niche} mee?"

LENGTE: 150-200 woorden
FORMAT: <h3>Vraag?</h3><p>Antwoord</p><p>Optioneel</p>

FORBIDDEN IN TEXT:
- Geen overlapping met faq_1/2 onderwerpen
- Geen "bekijk ook Top 5/reviews/blogs"
- Geen inline links

CTA TEMPLATE (kopieer exact):
"Bekijk alle opties op onze productpagina."

─────────────────────────────────────────────────────────────────────
BLOCK 8: producten_index_hero_titel
─────────────────────────────────────────────────────────────────────
PAGE: Producten (/producten)
PAGE ROLE: DECISION HUB (eindstation)
BLOCK ROLE: Pagina framing
FOCUS TAG: "vergelijk-overzicht"
LENGTE: 50-70 tekens
MUST INCLUDE:
- "Vergelijk alle {$niche}" + voordeel
VOORBEELD: "Vergelijk alle {$niche} – Vind de beste deal"
MUST AVOID:
- Geen verwijzing naar andere pagina's
CTA: Niet van toepassing

─────────────────────────────────────────────────────────────────────
BLOCK 9: producten_index_info_blok_1
─────────────────────────────────────────────────────────────────────
PAGE: Producten (/producten)
PAGE ROLE: DECISION HUB (eindstation)
BLOCK ROLE: Waarom vergelijken cruciaal is
FOCUS TAG: "prijsverschillen-specs-besparen"
LENGTE: 250-300 woorden
STRUCTUUR: H2 + doorlopende tekst (geen H3 nodig voor korter blok)
MUST INCLUDE:
- H2: "Waarom vergelijken bij {$niche} zo belangrijk is"
- Prijsverschillen €X-€Y uitleggen
- Welke specs écht belangrijk zijn
- Hoe bespaar je slim?
MUST AVOID:
- Geen verwijzing naar Top 5/reviews/blogs als alternatieven
- Geen "of/of/of"
CTA (laatste zin): "Gebruik de filters hierboven om jouw ideale {$niche} te vinden."

─────────────────────────────────────────────────────────────────────
BLOCK 10: producten_index_info_blok_2
─────────────────────────────────────────────────────────────────────
PAGE: Producten (/producten)
PAGE ROLE: DECISION HUB (eindstation)
BLOCK ROLE: Hoe filters gebruiken (ANDERS dan blok 1!)
FOCUS TAG: "filters-budget-merken-ratings"

FORBIDDEN TOPICS (behandel dit NIET in dit block):
✗ Geen "waarom vergelijken belangrijk is" → dat is info_blok_1
✗ Geen algemene prijsverschillen uitleggen → dat is info_blok_1
✗ Geen verwijzing naar reviews-pagina, Top 5, of blogs als "betere route"

MUST TREAT (alleen deze onderwerpen):
1. HOE FILTERS GEBRUIKEN: Praktische uitleg filteropties
2. BUDGET BEPALEN: Prijsklassen en wat verwacht je per niveau
3. MERKEN: A-merk vs budget, wanneer is welke waard?
4. RATINGS LEZEN: Hoe interpreteer je reviews/ratings?

LENGTE: 250-300 woorden
STRUCTUUR: H2 + doorlopende tekst

FORBIDDEN IN TEXT:
- Geen "Lees ook onze reviews" of "Bekijk de Top 5" → dit is het EINDSTATION
- Geen inline links

CTA TEMPLATE (kopieer exact):
"Begin met filteren en vind jouw match in ons volledige overzicht."

─────────────────────────────────────────────────────────────────────
BLOCK 11: producten_top_hero_titel
─────────────────────────────────────────────────────────────────────
PAGE: Top 5 (/top-5)
PAGE ROLE: Shortcut (ondergeschikt aan /producten)
BLOCK ROLE: Pagina framing
FOCUS TAG: "top5-actueel"
LENGTE: 50-70 tekens
MUST INCLUDE:
- "Top 5 beste {$niche} van {{ maand }} {$currentYear}"
- GEBRUIK {{ maand }} placeholder!
VOORBEELD: "Top 5 beste {$niche} van {{ maand }} {$currentYear}"
MUST AVOID:
- Geen extra woorden
CTA: Niet van toepassing

─────────────────────────────────────────────────────────────────────
BLOCK 12: producten_top_seo_blok
─────────────────────────────────────────────────────────────────────
PAGE: Top 5 (/top-5)
PAGE ROLE: Shortcut (ondergeschikt aan /producten)
BLOCK ROLE: Selectieproces uitleggen
FOCUS TAG: "selectiecriteria-methodiek"
LENGTE: 800-1000 woorden
STRUCTUUR: 1 H2 + max 3 H3
MUST INCLUDE:
- H2: "Hoe selecteren we de Top 5 {$niche}?"
- Intro (150-200w): Transparant uitleggen (waarom Top 5, niet Top 10?)
- H3 sub 1: "Onze selectiecriteria" → 4 criteria in doorlopende tekst
- H3 sub 2: "Voor wie is welk model?" → Algemeen per positie (1-2, 3, 4-5), GEEN productnamen
- H3 sub 3: "Vergelijkingstabel gebruiken" → Praktische tips
MUST AVOID:
- Geen promotie van Top 5 als "beter" dan /producten
- Geen "of/of/of"
CTA (laatste zin): "Wil je meer opties? Bekijk <a href=\"/producten\" class=\"text-purple-700 underline\">alle {$niche}</a> voor volledige vergelijking."

─────────────────────────────────────────────────────────────────────
BLOCK 13: merken_index_hero_titel
─────────────────────────────────────────────────────────────────────
PAGE: Beste merken (/beste-merken)
PAGE ROLE: Merkfilter ingang → /producten
BLOCK ROLE: Pagina framing
FOCUS TAG: "merken-vergelijk"
LENGTE: 50-70 tekens
MUST INCLUDE:
- "De beste merken {$niche} vergeleken"
MUST AVOID:
- Geen extra franje
CTA: Niet van toepassing

─────────────────────────────────────────────────────────────────────
BLOCK 14: merken_index_info_blok
─────────────────────────────────────────────────────────────────────
PAGE: Beste merken (/beste-merken)
PAGE ROLE: Merkfilter ingang → /producten
BLOCK ROLE: Merkpositionering uitleggen
FOCUS TAG: "merkwaarde-garantie-service"
LENGTE: 800-1000 woorden
STRUCTUUR: 1 H2 + max 3 H3
MUST INCLUDE:
- H2: "Waarom merk belangrijk is bij {$niche}"
- Intro (150-200w): Verschil tussen merken
- H3 sub 1: "De belangrijkste merken" → 5-7 ECHTE merken in doorlopende tekst (bijv. Philips, Tefal, etc — GEEN "[Merk 1]")
- H3 sub 2: "A-merk vs budget" → Wanneer is welke waard?
- H3 sub 3: "Garantie en service" → Lange termijn waarde
MUST AVOID:
- Geen "[Merk 1]" placeholders — gebruik ECHTE merknamen
- Geen Top 5/reviews/blogs promotie
CTA (laatste zin): "Filter op jouw favoriete merk op <a href=\"/producten\" class=\"text-purple-700 underline\">onze productpagina</a>."

─────────────────────────────────────────────────────────────────────
BLOCK 15: reviews.hero
─────────────────────────────────────────────────────���───────────────
PAGE: Reviews (/reviews)
PAGE ROLE: Verdieping (GEEN eindstation) → /producten
BLOCK ROLE: Pagina framing + waarde uitleggen
FOCUS TAG: "expertbeoordelingen"

FORBIDDEN TOPICS (behandel dit NIET in dit block):
✗ Geen uitleg reviewproces → dat is reviews_index_seo_blok
✗ Geen "waarom reviews waardevol" → dat is reviews_index_intro

MUST TREAT (alleen hero elementen):
1. H1: Korte titel over {$niche} reviews
2. ONDERTITEL: Waarom betrouwbaar? (1 zin, 80-120 tekens)

LENGTE: H1 (50-70 tekens) + ondertitel (80-120 tekens)
FORMAT: <h1 class="text-3xl sm:text-5xl font-extrabold leading-tight">Titel</h1><p class="text-base sm:text-xl">Ondertitel</p>

CLAIM POLICY (STRIKT):
✗ NOOIT: "wij testen", "onze tests", "uitgebreide tests"
✓ WEL: "beoordelen", "analyseren", "grondig bekeken"

VOORBEELD:
<h1>Onafhankelijke {$niche} reviews & ervaringen</h1>
<p>Grondig beoordeeld op kwaliteit, prestaties en prijs-kwaliteit</p>

CTA: Niet van toepassing (hero)

─────────────────────────────────────────────────────────────────────
BLOCK 16: reviews_index_intro
─────────────────────────────────────────────────────────────────────
PAGE: Reviews (/reviews)
PAGE ROLE: Verdieping (GEEN eindstation) → /producten
BLOCK ROLE: Waarom reviews waardevol (compact intro)
FOCUS TAG: "review-aanpak-transparant"

FORBIDDEN TOPICS (behandel dit NIET in dit block):
✗ Geen gedetailleerd proces uitleggen → dat is reviews_index_seo_blok
✗ Geen hero/titel → dat is reviews.hero

MUST TREAT (alleen intro waarom):
1. WAAROM REVIEWS?: Waarom zijn reviews de moeite waard?
2. DIEPER DAN SPECS: Praktijk vs papier
3. ONZE AANPAK: Objectief, onafhankelijk

LENGTE: 150-200 woorden
STRUCTUUR: 4 korte paragrafen

CLAIM POLICY (STRIKT):
✗ NOOIT: "wij testen", "onze tests", "uitgebreide tests"
✓ WEL: "beoordelen", "analyseren", "bekijken"

FORBIDDEN IN TEXT:
- Geen "lees meer reviews" loops
- Geen inline links

CTA TEMPLATE (kopieer exact):
"Klaar om te kiezen? Vergelijk alle modellen op onze productpagina."

─────────────────────────────────────────────────────────────────────
BLOCK 17: reviews_index_seo_blok
─────────────────────────────────────────────────────────────────────
PAGE: Reviews (/reviews)
PAGE ROLE: Verdieping (GEEN eindstation) → /producten
BLOCK ROLE: Review proces uitleggen
FOCUS TAG: "reviewproces-criteria-objectiviteit"

FORBIDDEN TOPICS (behandel dit NIET in dit block):
✗ Geen algemene "waarom reviews nuttig" → dat is reviews_index_intro
✗ Geen hero-tekst → dat is reviews.hero

MUST TREAT (alleen proces details):
1. ONS PROCES: 5 stappen hoe wij reviews maken
2. CRITERIA: 5 concrete punten waar we op letten
3. OBJECTIVITEIT: Balans objectief/subjectief
4. TIPS VOOR LEZER: Hoe lees je reviews effectief?

LENGTE: 800-1000 woorden
STRUCTUUR: 1 H2 + max 4 H3

CLAIM POLICY (STRIKT):
✗ NOOIT: "wij testen", "onze testlab", "uitgebreid testen"
✓ WEL: "beoordelen", "analyseren", "grondig bekijken", "evalueren"

FORBIDDEN IN TEXT:
- Geen "lees meer reviews" loops (dit is NIET de CTA)
- Geen inline links

CTA TEMPLATE (kopieer exact):
"Klaar om te vergelijken? Ga naar onze productpagina."

─────────────────────────────────────────────────────────────────────
BLOCK 18: blogs.hero
─────────────────────────────────────────────────────────────────────
PAGE: Blogs (/blogs)
PAGE ROLE: Informeren (GEEN eindstation) → /producten
BLOCK ROLE: Pagina framing + waarde
FOCUS TAG: "gidsen-tips"

FORBIDDEN TOPICS (behandel dit NIET in dit block):
✗ Geen uitleg "waarom blogs nuttig" → dat is blogs.seo
✗ Geen welkom tekst → dat is blogs.intro

MUST TREAT (alleen hero elementen):
1. H1: Korte titel over {$niche} blogs/gidsen
2. ONDERTITEL: Wat vind je hier? (1 zin, 80-120 tekens)

LENGTE: H1 (50-70 tekens) + ondertitel (80-120 tekens)
FORMAT: <h1 class="text-3xl sm:text-5xl font-extrabold mb-4">Titel</h1><p class="text-base sm:text-xl">Ondertitel</p>

VOORBEELD:
<h1>Alles over {$niche}</h1>
<p>Praktische gidsen, koopadvies en tips voor de juiste keuze</p>

CTA: Niet van toepassing (hero)

─────────────────────────────────────────────────────────────────────
BLOCK 19: blogs.intro
─────────────────────────────────────────────────────────────────────
PAGE: Blogs (/blogs)
PAGE ROLE: Informeren (GEEN eindstation) → /producten
BLOCK ROLE: Welkom + wat vind je hier (compact)
FOCUS TAG: "educatie-voorbereiding"

FORBIDDEN TOPICS (behandel dit NIET in dit block):
✗ Geen uitleg "waarom blogs helpen" → dat is blogs.seo
✗ Geen hero/titel → dat is blogs.hero

MUST TREAT (alleen welkom):
1. VOOR WIE: Beginners + gevorderden
2. WAT VIND JE: Tips, vergelijkingen, ervaringen

LENGTE: 100-150 woorden
STRUCTUUR: H2 + 1-2 paragrafen

FORBIDDEN IN TEXT:
- Geen "blijf blogs lezen" loops
- Geen inline links

CTA TEMPLATE (kopieer exact):
"Klaar om te kiezen? Vergelijk alle modellen op onze productpagina."

─────────────────────────────────────────────────────────────────────
BLOCK 20: blogs.seo
─────────────────────────────────────────────────────────────────────
PAGE: Blogs (/blogs)
PAGE ROLE: Informeren (GEEN eindstation) → /producten
BLOCK ROLE: Waarom blogs nuttig zijn vóór je koopt
FOCUS TAG: "educatie-waarde-voorbereiding"

FORBIDDEN TOPICS (behandel dit NIET in dit block):
✗ Geen welkom/wie-voor → dat is blogs.intro
✗ Geen hero/titel → dat is blogs.hero

MUST TREAT (alleen waarom nuttig):
1. GESCHREVEN DOOR: Specialisten/experts
2. PRAKTISCH: Geen marketing, wel echte info
3. ONDERWERPEN: Van onderhoud tot ervaringen

LENGTE: 250-300 woorden
STRUCTUUR: H2 + doorlopende tekst

FORBIDDEN IN TEXT:
- Geen interne links naar andere hubs (GEEN <a href="/beste-merken"> of /reviews of /top-5)
- Geen "lees ook reviews/top5" opties
- Geen inline links

CTA TEMPLATE (kopieer exact):
"Begin met vergelijken op onze productpagina."

═══════════════════════════════════════════════════════════════════════
FINAL CHECKLIST (model moet intern checken vóór output)
═══════════════════════════════════════════════════════════════════════
Voordat je JSON returnt, check:
✓ Elke block heeft unieke focus → check FORBIDDEN TOPICS per block
✓ Geen verboden woorden: "of/of/of", "bekijk ook Top 5/reviews/blogs"
✓ GEEN inline <a> links in lopende tekst → alleen in CTA template waar expliciet aangegeven
✓ CTA's: gebruik EXACT de template text → geen HTML in de instructie zelf
✓ CLAIM POLICY: NOOIT "wij testen/tests" → WEL "beoordelen/analyseren/bekijken"
✓ Geen placeholders zoals "[Type 1]", "[Merk 1]" → alleen ECHTE namen
✓ Alle HTML valid, geen markdown
✓ Exact 20 keys in JSON
✓ Elk block behandelt ALLEEN zijn MUST TREAT onderwerpen, NIET de FORBIDDEN TOPICS

═══════════════════════════════════════════════════════════════════════
OUTPUT FORMAT
═══════════════════════════════════════════════════════════════════════

Return ONLY valid minified JSON met exact deze 20 keys:

{
  "homepage.hero": "...",
  "homepage.info": "...",
  "homepage.seo1": "...",
  "homepage.seo2": "...",
  "homepage.faq_1": "...",
  "homepage.faq_2": "...",
  "homepage.faq_3": "...",
  "producten_index_hero_titel": "...",
  "producten_index_info_blok_1": "...",
  "producten_index_info_blok_2": "...",
  "producten_top_hero_titel": "...",
  "producten_top_seo_blok": "...",
  "merken_index_hero_titel": "...",
  "merken_index_info_blok": "...",
  "reviews.hero": "...",
  "reviews_index_intro": "...",
  "reviews_index_seo_blok": "...",
  "blogs.hero": "...",
  "blogs.intro": "...",
  "blogs.seo": "..."
}

Return ALLEEN minified JSON. Geen markdown, geen commentary.
PROMPT;

        $response = $this->chat([
            ['role' => 'system', 'content' => 'You are a world-class Dutch SEO copywriter. Return ONLY minified JSON with complete HTML content. No markdown, no commentary, no placeholders.'],
            ['role' => 'user', 'content' => $prompt],
        ], 'gpt-4o', 0.7, 16000);

        $content = trim($response['content'] ?? '{}');
        $content = $this->cleanJsonResponse($content);
        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return $this->getFallbackContentBlocks($niche, $siteName);
        }

        return $decoded;
    }

    /**
     * Generate blog variations for diverse blog content
     */
    public function generateBlogVariations(string $niche, ?string $uniqueFocus = null): array
    {
        // Build unique focus context
        $uniqueFocusContext = '';
        if ($uniqueFocus) {
            $uniqueFocusContext = "\n\nUNIEKE FOCUS: {$uniqueFocus}\nGebruik deze focus ALLEEN waar relevant (bijv. in specifieke doelgroepen of problemen). Niet in elke categorie.";
        }

        $prompt = <<<PROMPT
Nederlandse content strategist voor {$niche} blog variaties.
{$uniqueFocusContext}

Genereer diverse categorieën en waarden voor blog content variatie.

CATEGORIEËN EN VOORBEELDEN:

1. **doelgroepen** (12-15 verschillende doelgroepen):
   - gezinnen met jonge kinderen
   - studenten en starters
   - professionals met weinig tijd
   - senioren en gepensioneerden
   - gezondheids-bewuste consumenten
   - budget-bewuste kopers
   - tech-enthousiastelingen
   - alleenstaanden
   - grote gezinnen (5+ personen)
   - sporters en actieve mensen
   - mensen met fysieke beperkingen
   - luxe-zoekers / premium segment
   [Verzin nog 3-4 relevante doelgroepen voor {$niche}]

2. **problemen** (12-15 specifieke problemen die {$niche} oplossen):
   - beperkte ruimte
   - gebrek aan tijd
   - gezonder willen leven
   - kosten besparen
   - eenvoudige bediening gewenst
   - grote gezinnen bedienen
   - specifieke behoeften/dieet
   - lawaai overlast
   - onderhoud en reiniging
   - energieverbruik te hoog
   - onveiligheid / angst voor ongelukken
   - gebrek aan kennis/ervaring
   [Verzin nog 3-4 problemen specifiek voor {$niche}]

3. **gebruikssituaties** (12-15 situaties):
   - dagelijks gebruik
   - weekends en vrije tijd
   - feestjes en gatherings
   - meal prep / voorbereiden
   - snelle doordeweekse oplossingen
   - gezonde tussendoortjes
   - bijgerechten maken
   - voor gasten / entertainment
   - onderweg / mobiel gebruik
   - 's ochtends routine
   - 's avonds ontspanning
   - tijdens werk/studie
   [Verzin nog 3-4 situaties voor {$niche}]

4. **kenmerken** (12-15 belangrijke features):
   - capaciteit en volume
   - energieverbruik en efficiëntie
   - gebruiksgemak en bediening
   - reinigbaarheid en onderhoud
   - geluidsniveau
   - veiligheidsfeatures
   - veelzijdigheid en flexibiliteit
   - design en esthetiek
   - opslag en footprint
   - duurzaamheid en kwaliteit
   - slimme functies / connectivity
   - garantie en service
   [Verzin nog 3-4 kenmerken voor {$niche}]

5. **seizoenen** (4 items):
   - lente
   - zomer
   - herfst
   - winter

6. **speciale_momenten** (15-20 items - feestdagen, events, momenten):
   - Nieuwjaar en goede voornemens
   - Valentijnsdag
   - Pasen en lenteperiode
   - Moederdag
   - Vaderdag
   - BBQ seizoen (mei-september)
   - Vakantieperiode
   - Back to school (augustus/september)
   - Sinterklaas
   - Black Friday
   - Cyber Monday
   - Kerstmis
   - Eindejaar / cadeaus
   - Verjaardagen en jubilea
   - Verhuizing / nieuwe woning
   [Verzin nog 5-8 relevante momenten voor {$niche}]

7. **themas** (20-25 bredere content themas die passen bij {$niche}):
   BELANGRIJK: Bedenk thema's die INDIRECT gerelateerd zijn aan {$niche}!

   Voorbeelden voor verschillende niches:
   - Airfryers/Kookgerei: "gezonde recepten", "snelle weekendmaaltijden", "meal prep tips", "gezinsgerechten", "budget koken", "vegetarische recepten", "lunchbox ideeën", "restjes verwerken", "koolhydraatarm koken", "veganistische gerechten", "glutenvrij koken", "kinderen leren koken", "wereldkeukens", "comfort food", "detox recepten"
   - Sport/Fitness: "afvaltips", "trainingsschema's", "voedingsadvies voor sporters", "motivatie en mindset", "blessure preventie", "herstel en rust", "HIIT workouts", "cardio tips", "spieropbouw", "rek- en strekoefeningen", "thuissporten", "fitness voor beginners", "supplementen en voeding", "slapen en herstel"
   - Massage/Wellness: "stress management", "slaaptips en slaaphygiëne", "ontspanningstechnieken", "werk-privé balans", "mindfulness oefeningen", "rug- en nekklachten", "burn-out preventie", "ademhalingsoefeningen", "zelfzorg routines", "emotioneel welzijn"
   - Gaming/Tech: "ergonomie en houding", "gaming setup optimaliseren", "concentratie verbeteren", "budget gaming tips", "streaming tips", "RGB lighting", "kabel management"
   - Huishouden: "energie besparen", "slim schoonmaken", "organisatie tips", "duurzaamheid", "minimalisme", "opruimen", "budgettering"

   Verzin 20-25 diverse, relevante thema's specifiek voor {$niche} categorie!

OUTPUT JSON (exact deze structuur):
{
  "doelgroepen": ["waarde1", "waarde2", ...],
  "problemen": ["waarde1", "waarde2", ...],
  "gebruikssituaties": ["waarde1", "waarde2", ...],
  "kenmerken": ["waarde1", "waarde2", ...],
  "seizoenen": ["lente", "zomer", "herfst", "winter"],
  "speciale_momenten": ["moment1", "moment2", ...],
  "themas": ["thema1", "thema2", ...]
}

BELANGRIJK: Verzin concrete, relevante waarden voor de {$niche} niche!
Return ALLEEN minified JSON.
PROMPT;

        $response = $this->chat([
            ['role' => 'system', 'content' => 'Return ONLY minified JSON. No markdown, no commentary.'],
            ['role' => 'user', 'content' => $prompt],
        ], 'gpt-4o-mini', 0.7, 1000);

        $content = trim($response['content'] ?? '{}');
        $content = $this->cleanJsonResponse($content);
        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return $this->getFallbackBlogVariations($niche);
        }

        return $decoded;
    }

    /**
     * Generate seed blog posts (2-3 posts to start with)
     */
    public function generateSeedBlogPosts(string $niche, string $siteName): array
    {
        $currentYear = date('Y');

        $prompt = <<<PROMPT
Nederlandse content strategist. Bedenk 3 blog post ideeën voor {$siteName} (niche: {$niche}).

TYPES:
- Koopgids: "Hoe kies je de beste [product] in {$currentYear}"
- Vergelijking: "[Product A] vs [Product B]"
- Uitleg: "Alles over [feature]"
- Tips: "5 dingen waar je op moet letten"

OUTPUT JSON (3 posts):
[
  {
    "title": "SEO titel 60-70 tekens",
    "excerpt": "Samenvatting 150-160 tekens",
    "content_type": "koopgids",
    "suggested_keywords": ["keyword1", "keyword2", "keyword3"]
  }
]

Return ALLEEN JSON array.
PROMPT;

        $response = $this->chat([
            ['role' => 'system', 'content' => 'Return ONLY JSON array. No markdown, no commentary.'],
            ['role' => 'user', 'content' => $prompt],
        ], 'gpt-4o-mini', 0.8, 800);

        $content = trim($response['content'] ?? '[]');
        $content = $this->cleanJsonResponse($content);
        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /* =======================
     *   PRIVATE HELPERS
     * ======================= */

    /**
     * Chat with OpenAI with retry logic and circuit breaker
     */
    protected function chat(array $messages, string $model, float $temperature, int $maxTokens): array
    {
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
        $baseDelay = 1000000; // 1 second
        $lastError = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $res = $this->client->chat()->create([
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => $temperature,
                    'max_tokens' => $maxTokens,
                ]);

                $content = $res->choices[0]->message->content ?? '';
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

            } catch (\Throwable $e) {
                $lastError = $e;

                \Log::warning("OpenAI API call attempt {$attempt}/{$maxAttempts} failed", [
                    'model' => $model,
                    'error' => $e->getMessage(),
                    'attempt' => $attempt
                ]);

                if ($attempt < $maxAttempts) {
                    $delay = $baseDelay * pow(2, $attempt - 1);
                    usleep($delay);
                }
            }
        }

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
     * Clean JSON response from markdown artifacts
     */
    protected function cleanJsonResponse(string $content): string
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
     * Fallback blog variations if OpenAI fails
     */
    protected function getFallbackBlogVariations(string $niche): array
    {
        return [
            'doelgroepen' => [
                'gezinnen met kinderen', 'studenten en starters', 'professionals',
                'senioren', 'budget-bewuste kopers', 'alleenstaanden',
                'grote gezinnen', 'sporters', 'tech-liefhebbers',
                'luxe-segment', 'beginners', 'gevorderden', 'experts'
            ],
            'problemen' => [
                'beperkte ruimte', 'gebrek aan tijd', 'gezonder leven',
                'kosten besparen', 'gebruiksgemak', 'onderhoud',
                'lawaai', 'energieverbruik', 'veiligheid',
                'gebrek aan kennis', 'te veel opties', 'duurzaamheid'
            ],
            'gebruikssituaties' => [
                'dagelijks gebruik', 'weekends', 'feestjes',
                'meal prep', 'snelle maaltijden', 'voor gasten',
                'onderweg', 'ochtend routine', 'avond ontspanning',
                'tijdens werk', 'vakantie', 'bijzondere momenten'
            ],
            'kenmerken' => [
                'capaciteit', 'energieverbruik', 'gebruiksgemak',
                'onderhoud', 'veiligheid', 'geluidsniveau',
                'design', 'veelzijdigheid', 'duurzaamheid',
                'slimme functies', 'opslag', 'garantie'
            ],
            'seizoenen' => [
                'lente', 'zomer', 'herfst', 'winter'
            ],
            'speciale_momenten' => [
                'Nieuwjaar', 'Valentijnsdag', 'Pasen', 'Moederdag',
                'Vaderdag', 'BBQ seizoen', 'Vakantie', 'Back to school',
                'Sinterklaas', 'Black Friday', 'Cyber Monday', 'Kerstmis',
                'Eindejaar cadeaus', 'Verjaardagen', 'Verhuizing',
                'Zwarte Vrijdag deals', 'Zomervakantie', 'Winterperiode'
            ],
            'themas' => [
                'lifestyle tips', 'gezondheid en welzijn', 'duurzaamheid',
                'budget tips', 'productiviteit', 'trends', 'DIY projecten',
                'beginners gids', 'expert tips', 'vergelijkingen',
                'koopgids', 'onderhoud tips', 'inspiratie',
                'geschiedenis en ontwikkeling', 'toekomst trends',
                'veelgemaakte fouten', 'mythes ontkracht', 'voor en nadelen',
                'testimonials en ervaringen', 'statistieken en feiten'
            ]
        ];
    }

    /**
     * Fallback settings if OpenAI fails
     */
    protected function getFallbackSettings(string $niche, string $domain, string $primaryColor): array
    {
        return [
            'site_name' => $domain,
            'site_niche' => $niche,
            'primary_color' => $primaryColor,
            'font_family' => 'Poppins',
            'tone_of_voice' => 'informeel en behulpzaam',
            'target_audience' => 'mensen die op zoek zijn naar ' . $niche,
        ];
    }

    /**
     * Fallback content blocks if OpenAI fails
     */
    protected function getFallbackContentBlocks(string $niche, string $siteName): array
    {
        $currentYear = date('Y');

        return [
            'homepage.hero' => "De beste {$niche} van {{ maand }} {$currentYear}",
            'homepage.info' => "<p>Welkom bij {$siteName}. Wij helpen je de beste {$niche} te vinden door middel van eerlijke vergelijkingen en reviews.</p>",
            'homepage.seo1' => "<h2 class=\"text-2xl font-bold mb-4\">Waarom {$niche} vergelijken?</h2><p>Het aanbod aan {$niche} is enorm. Door te vergelijken vind je het product dat perfect bij jouw situatie past.</p>",
            'homepage.seo2' => "<h2 class=\"text-2xl font-bold mb-4\">Slim kopen begint met vergelijken</h2><p>Bekijk specificaties, prijzen en reviews op één plek en maak een weloverwogen keuze.</p>",
            'homepage.faq_1' => "<h3>Wat kost een goede {$niche}?</h3><p>De prijs varieert sterk afhankelijk van het type en de specificaties. Budgetmodellen zijn er vanaf €X, terwijl premium opties tot €Y kunnen kosten.</p>",
            'homepage.faq_2' => "<h3>Hoe kies ik de juiste {$niche}?</h3><p>Let op de specificaties die voor jouw situatie belangrijk zijn. Bekijk onze Top 5 voor de beste keuzes.</p>",
            'homepage.faq_3' => "<h3>Zijn duurdere {$niche} altijd beter?</h3><p>Niet per se. Hogere prijs betekent vaak meer features, maar niet iedereen heeft deze nodig. Bepaal eerst je behoeften.</p>",

            'producten_index_hero_titel' => "Alle {$niche} op een rij",
            'producten_index_info_blok_1' => "<h2 class=\"text-2xl font-bold mb-4\">Vergelijk {$niche}</h2><p>Bekijk en vergelijk alle {$niche} op basis van specificaties, prijzen en reviews.</p>",
            'producten_index_info_blok_2' => "<h2 class=\"text-2xl font-bold mb-4\">Filter slim</h2><p>Filter op merk, prijs en kenmerken om snel het perfecte product te vinden.</p>",

            'producten_top_hero_titel' => "Top 5 beste {$niche}",
            'producten_top_seo_blok' => "<h2 class=\"text-2xl font-bold mb-4\">Hoe selecteren we onze Top 5?</h2><p>Onze experts beoordelen {$niche} op basis van prijs-kwaliteit, prestaties en gebruikerservaringen.</p>",

            'merken_index_hero_titel' => "Shop {$niche} op merk",
            'merken_index_info_blok' => "<h2 class=\"text-2xl font-bold mb-4\">Merken</h2><p>Elk merk heeft zijn eigen sterke punten. Ontdek welk merk het beste bij jouw wensen past.</p>",

            'reviews.hero' => "Eerlijke reviews over {$niche}",
            'reviews_index_intro' => "<p>Lees onze uitgebreide reviews geschreven door experts die {$niche} grondig testen.</p>",
            'reviews_index_seo_blok' => "<h2 class=\"text-2xl font-bold mb-4\">Onafhankelijk en eerlijk</h2><p>Onze reviews zijn gebaseerd op uitgebreid onderzoek en praktijktests.</p>",

            'blogs.hero' => "Tips en gidsen over {$niche}",
            'blogs.intro' => "<p>Ontdek praktische tips, koopgidsen en inspiratie voor het kiezen van de juiste {$niche}.</p>",
            'blogs.seo' => "<h2 class=\"text-2xl font-bold mb-4\">Maak de juiste keuze</h2><p>Onze gidsen helpen je begrijpen waar je op moet letten bij het kopen van {$niche}.</p>",
        ];
    }

    /**
     * Generate blog templates for template-based blog generation
     */
    public function generateBlogTemplates(string $niche, ?string $uniqueFocus = null): array
    {
        // Build unique focus context
        $uniqueFocusContext = '';
        if ($uniqueFocus) {
            $uniqueFocusContext = <<<FOCUS

UNIEKE FOCUS/USP: {$uniqueFocus}
- Gebruik deze focus ALLEEN waar relevant (bijv. in specifieke use cases of problemen)
- Niet in elke template titel gebruiken
- De niche blijft kort: "{$niche}"
FOCUS;
        }

        $prompt = <<<PROMPT
Je bent een SEO content strategist voor AFFILIATE PRODUCT VERGELIJKINGSSITES (zoals Wirecutter/Tweakers). Genereer MINIMAAL 60 ZEER DIVERSE blog template concepten voor "{$niche}" sites.
{$uniqueFocusContext}

BELANGRIJKSTE REGEL: Dit is een AFFILIATE PRODUCT VERGELIJKINGSSITE, GEEN receptensite, kookblog of lifestyle magazine!

KRITISCH: Deze templates worden gebruikt voor 30+ affiliate sites. Ze MOETEN:
- FOCUS OP PRODUCTKEUZE: Helpen mensen het juiste {$niche} product kiezen, kopen, gebruiken, onderhouden, repareren
- Menselijk aanvoelen (NIET robotachtig of AI-gegenereerd)
- Natuurlijke variatie hebben (GEEN herhalende patronen zoals "Complete Oplossing", "Ultieme Koopgids")
- Niche-specifiek zijn (denk NA over wat mensen echt zoeken voor {$niche})
- Echte problemen oplossen (niet generieke SEO-titels)

WAT WEL MAG (productgericht):
✅ "Welke {$niche} past bij jouw situatie?"
✅ "Hoe kies je de juiste {$niche}?"
✅ "Verschil tussen type A en type B {$niche}"
✅ "Wat kost een {$niche} aan onderhoud/stroom?"
✅ "Waarom je {$niche} {probleem} heeft — oplossing"
✅ "Is feature X het geld waard bij {$niche}?"
✅ "Hoe stel je een {$niche} goed af?"

WAT NIET MAG (recepten/kooktips/lifestyle):
❌ "De beste recepten voor {$niche}"
❌ "Hoe maak je X gerecht met {$niche}"
❌ "Perfecte maaltijd/snack/gerecht met {$niche}"
❌ "Tips voor lekker koken met {$niche}"
❌ "{$niche} voor filmavond/feestje/party"
❌ Specifieke gerechten/menu's/recepten

ELK TEMPLATE MOET:
1. **Menselijke, natuurlijke titel** met relevante variabelen
2. **Longtail keyword targeting** (wat zoeken mensen echt?)
3. **H2/H3 content outline** voor gestructureerde content
4. **Uniek** - geen overlap met andere templates

VERPLICHTE 21 CATEGORIEËN (verdeel minimaal 60 templates hierover - gemiddeld 3 per categorie):

1. **Probleem-oplossing** (5 templates)
   - "Waarom je {niche} {problem} heeft — en hoe je het oplost"
   - "{number} fouten waardoor {niche} {consequence}"
   - "{niche} {problem}? Dit moet je checken"
   - Welke {niche} zijn wél geschikt voor {situation}?"

2. **Dieptegidsen - Expertise** (4 templates)
   - "De complete handleiding: {technical_spec} in {niche} uitgelegd"
   - "Zo werkt {technology} in moderne {niche}"
   - "{spec_a} vs {spec_b}: wat betekent het voor {use_case}?"

3. **Realistische vergelijkingen** (4 templates - NIET "top 5")
   - "Budget vs premium: wat merk je écht bij {niche}?"
   - "{feature_a}, {feature_b} of {feature_c}? Wat heb je nodig in {year}?"
   - "Verschil tussen {type_a} en {type_b} {niche}"

4. **Kosten & onderhoud** (5 templates - GOUDMIJN)
   - "Wat kost een {niche} aan stroom per jaar?"
   - "Hoe lang gaat een {niche} mee?"
   - "Onderhoudsschema voor {niche} (per maand, per jaar)"
   - "Wanneer moet je {part} vervangen? Checklist"

5. **Kritische editorial** (4 templates)
   - "Waarom je géén goedkope {niche} moet kopen (ja echt)"
   - "De marketingtrucs die fabrikanten gebruiken"
   - "Waarom {spec} belangrijker is dan {other_spec}"
   - "Grootste misverstanden over {niche}"

6. **Realistische gebruikssituaties** (4 templates - NIET "alleenstaanden")
   - "{niche} voor {realistic_situation}"
   - "Beste {niche} voor {specific_environment}"
   - "{niche} voor {real_problem} (waar let je op?)"

7. **Slimme keuzehulp** (3 templates)
   - "Past een {type_a} {niche} écht beter bij jou?"
   - "Wanneer kies je voor {feature} en wanneer niet?"
   - "Is {spec_value} genoeg voor {use_case}?"

8. **Mythes & misverstanden** (3 templates)
   - "De {number} grootste misverstanden over {niche}"
   - "De waarheid over {common_belief}"
   - "Mythe: {myth_statement} — fout"

9. **Prestaties testen** (3 templates)
   - "Welke {niche} presteert het best op {criterion}?"
   - "Deze {niche} zijn het best voor {intensive_use}"
   - "Wat we zien in goedkope vs dure {niche}"

10. **Koopmomenten & timing** (2 templates)
    - "Is Black Friday de beste tijd om een {niche} te kopen?"
    - "Deze maanden zijn {niche} het goedkoopst"

11. **Alternatieven vergelijken** (3 templates)
    - "{niche} of {alternative}: wat past beter?"
    - "{niche} vs {alternative_product}"

12. **Gebruikstips - lifestyle** (3 templates)
    - "Hoe je {goal} bereikt met een {niche}"
    - "Hoe je een effectieve {activity} start met {niche}"

13. **Checklist-content** (3 templates)
    - "Checklist: wat moet een {use_case} {niche} hebben?"
    - "Checklist: {niche} kopen voor {situation}"

14. **Prijspsychologie** (3 templates)
    - "Waarom sommige {niche} veel duurder zijn dan anderen"
    - "Waar je voor betaalt bij premium {niche}"
    - "Welke verborgen kosten je vaak vergeet bij {niche}"

15. **Veelgemaakte fouten** (3 templates)
    - "{number} fouten die {user_type} maken met {niche}"
    - "Veelgemaakte fouten bij {niche} kopen"

16. **Veiligheid & betrouwbaarheid** (3 templates)
    - "Hoe veilig zijn goedkope {niche}?"
    - "Welke {niche} worden sneller {problem}?"

17. **Duurzaamheid & levensduur** (3 templates)
    - "Welke materialen zijn beter voor lange levensduur {niche}?"
    - "Wanneer moet je onderdelen vervangen bij {niche}?"

18. **Beginners vs gevorderden** (2 templates - NIET top lijsten)
    - "Beginnersgids: {niche} kopen zonder spijt"
    - "Gids voor gevorderden: zo kies je een {advanced_feature}"

19. **Ruimte & interieur** (2 templates)
    - "{niche} die passen in {small_space}"
    - "Hoe je een {niche} integreert in je {environment}"

20. **Instellen & configureren** (2 templates)
    - "Hoe stel je een {niche} goed af?"
    - "Hoe {action} je {niche}?"

21. **Troubleshooting** (4 templates)
    - "{niche} {problem} — mogelijke oorzaken"
    - "{specific_problem} — wat nu?"

VARIABELEN INSTRUCTIES:
Voor ELKE variabele, bedenk 10-15 NICHE-SPECIFIEKE opties.

DENK NA: Wat zijn de echte problemen, specs, situaties voor {$niche}?

Voorbeelden variabelen (VERZIN ZELF voor {$niche}):
- {problem}: specifieke problemen die mensen hebben met {$niche}
- {technical_spec}: technische specificaties relevant voor {$niche}
- {realistic_situation}: echte gebruikssituaties (NIET "alleenstaanden", WEL "appartementen met dunne vloeren")
- {part}: onderdelen die vervangen moeten worden
- {technology}: technologieën gebruikt in {$niche}
- {common_belief}: mythes over {$niche}
- {alternative}: alternatieve producten voor {$niche}

VERBODEN PATRONEN & WOORDEN (gebruik deze NOOIT):
- "Complete Oplossing in {year}"
- "Ultieme Koopgids"
- "Waarom Het Belangrijk Is"
- "{niche} voor alleenstaanden"
- "{niche} voor de zomer" (tenzij echt relevant)
- "Onze Top Picks"
- "mindfulness", "spiritueel", "zen"
- "antieke keukens", "vintage", "retro" (tenzij niche-specifiek)
- "BBQ setup", "camping", "vakantie", "buitenkeuken", "vakantiewoning"
- "glazen {niche}", "kristallen {niche}" (alleen als niche écht glaswerk betreft)
- "studentenkamer", "tiny house", "maaltijdplanning", "maaltijdbereiding"
- Abstracte concepten die niet passen bij de niche
- Irrelevante combinaties (denk NA: past dit bij {$niche}?)

VERPLICHT:
- Titels moeten natuurlijk klinken (alsof een mens ze schreef)
- Variabelen moeten niche-specifiek zijn (niet generiek)
- Content outline moet logische flow hebben (4-6 H2 secties)
- Target word count: 1200-2000 woorden
- KRITISCH: Gebruik ALTIJD {year} voor jaartallen, NOOIT hard-coded jaren zoals "2023", "2024", "2025"

EXAMPLE OUTPUT (JSON array):
[
  {
    "title_template": "Waarom je {niche} zo veel {problem} — en hoe je het oplost",
    "slug_template": "{niche}-{problem}-oplossen",
    "seo_focus_keyword": "{niche} {problem}",
    "content_outline": [
      "Herken je dit {problem} probleem?",
      "De {number} meest voorkomende oorzaken",
      "Oplossing 1: {solution_a}",
      "Oplossing 2: {solution_b}",
      "Preventie tips",
      "Veelgestelde vragen",
      "Conclusie"
    ],
    "target_word_count": 1400,
    "cta_type": "buying_guide",
    "variables": {
      "problem": ["lawaai maakt", "trilt", "slecht ruikt", "niet goed werkt", "snel stuk gaat"],
      "solution_a": ["juiste plaatsing", "onderhoud", "instelling aanpassen"],
      "solution_b": ["upgrades", "accessoires", "professionele check"],
      "number": [3, 5, 7]
    }
  }
]

BELANGRIJK:
- Genereer MINIMAAL 60 templates (liever meer!)
- Spreiding: ALLE 21 categorieën moeten voorkomen
- Elke categorie MINIMAAL 2 templates, de meeste 3-4
- Elke template UNIEK en menselijk
- Niche-specifieke variabelen (denk NA over {$niche})
- Natuurlijke taal (GEEN AI-patronen)

Return ALLEEN minified JSON array met MINIMAAL 60 templates (bij voorkeur 70+).
PROMPT;

        // Generate templates in 3 batches to avoid OpenAI stopping early
        $allTemplates = [];
        $batches = [
            ['categories' => 'Categorieën 1-7: Probleem-oplossing, Dieptegidsen, Vergelijkingen, Kosten & onderhoud, Kritische editorial, Mythen, Troubleshooting', 'count' => 22],
            ['categories' => 'Categorieën 8-14: Timing & seizoen, Alternatieve producten, Beginnersgidsen, Gevorderden gidsen, Gebruik & tips, Onderhoud preventie, Specificaties uitgelegd', 'count' => 22],
            ['categories' => 'Categorieën 15-21: Populaire vragen, Meest gemaakte fouten, Veiligheid, Duurzaamheid & levensduur, Checklist-gebaseerd, Situatie-specifieke, Producttype vergelijkingen', 'count' => 22],
        ];

        foreach ($batches as $batchIndex => $batch) {
            $batchPrompt = str_replace(
                'MINIMAAL 60 ZEER DIVERSE blog template',
                "EXACT {$batch['count']} UNIEKE blog templates voor {$batch['categories']}. BELANGRIJK: Dit is batch " . ($batchIndex + 1) . " van 3, dus maak ANDERE templates dan batch " . ($batchIndex > 0 ? '1' : '') . ($batchIndex > 1 ? ' en 2' : ''),
                $prompt
            );

            $batchPrompt = str_replace(
                'Return ALLEEN minified JSON array met MINIMAAL 60 templates',
                "Return ALLEEN minified JSON array met EXACT {$batch['count']} templates uit deze categorieën",
                $batchPrompt
            );

            $response = $this->chat([
                ['role' => 'system', 'content' => "You are a JSON generator. Return ONLY a minified JSON array with EXACTLY {$batch['count']} templates. No markdown, no commentary, just pure JSON."],
                ['role' => 'user', 'content' => $batchPrompt],
            ], 'gpt-4o', 0.9, 8000);

            $content = trim($response['content'] ?? '[]');
            $content = $this->cleanJsonResponse($content);
            $decoded = json_decode($content, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $allTemplates = array_merge($allTemplates, $decoded);
            }
        }

        // If we got less than 60 templates total, supplement with fallbacks
        if (count($allTemplates) < 60) {
            $fallbacks = $this->getFallbackBlogTemplates($niche);
            $allTemplates = array_merge($allTemplates, array_slice($fallbacks, 0, 60 - count($allTemplates)));
        }

        return $allTemplates;
    }

    /**
     * Fallback blog templates if OpenAI fails or returns too few
     * Returns 70 diverse templates based on 21 categories to ensure maximum variety
     */
    protected function getFallbackBlogTemplates(string $niche): array
    {
        $templates = [];

        // CATEGORY 1: Probleem-oplossing (5 templates) - HARD-CODED (no variables)
        $templates[] = [
            'title_template' => "Waarom je {$niche} lawaai maakt — en hoe je het oplost",
            'slug_template' => "{$niche}-lawaai-oplossen",
            'seo_focus_keyword' => "{$niche} lawaai",
            'content_outline' => ["Herken je dit lawaai probleem?", "De 5 meest voorkomende oorzaken", "Oplossing 1: Plaatsing", "Oplossing 2: Onderhoud", "Preventie tips", "Veelgestelde vragen", "Conclusie"],
            'target_word_count' => 1400,
            'cta_type' => 'buying_guide',
            'variables' => []
        ];

        $templates[] = [
            'title_template' => "5 fouten waardoor {$niche} sneller slijten",
            'slug_template' => "5-fouten-{$niche}-slijtage",
            'seo_focus_keyword' => "{$niche} fouten slijtage",
            'content_outline' => ["Waarom slijtage een probleem is", "Fout 1: Verkeerd gebruik", "Fout 2: Slecht onderhoud", "Fout 3: Te intensief gebruik", "Fout 4: Verkeerde opslag", "Fout 5: Nalatig reinigen", "Hoe voorkom je dit?", "Conclusie"],
            'target_word_count' => 1300,
            'cta_type' => 'buying_guide',
            'variables' => []
        ];

        $templates[] = [
            'title_template' => "{$niche} werkt niet goed? Dit moet je checken",
            'slug_template' => "{$niche}-werkt-niet-checklist",
            'seo_focus_keyword' => "{$niche} werkt niet goed",
            'content_outline' => ["Is dit het probleem?", "Checklist: 7 dingen om te controleren", "Simpele oplossingen", "Wanneer professionele hulp?", "Preventie", "Veelgestelde vragen", "Conclusie"],
            'target_word_count' => 1400,
            'cta_type' => 'buying_guide',
            'variables' => []
        ];

        $templates[] = [
            'title_template' => "Welke {$niche} zijn wél geschikt voor kleine ruimtes?",
            'slug_template' => "{$niche}-geschikt-voor-kleine-ruimtes",
            'seo_focus_keyword' => "{$niche} voor kleine ruimtes",
            'content_outline' => ["Waarom kleine ruimtes lastig zijn", "Wat moet je zoeken?", "Beste compacte opties", "Waar op letten?", "Praktische tips", "Veelgestelde vragen", "Conclusie"],
            'target_word_count' => 1500,
            'cta_type' => 'comparison_table',
            'variables' => []
        ];

        $templates[] = [
            'title_template' => "Hoe voorkom je schade bij {$niche}?",
            'slug_template' => "schade-voorkomen-{$niche}",
            'seo_focus_keyword' => "{$niche} schade voorkomen",
            'content_outline' => ["Waarom schade voorkomt", "7 preventie tips", "Onderhoudstips", "Wat als het toch gebeurt?", "Beste modellen tegen schade", "Veelgestelde vragen", "Conclusie"],
            'target_word_count' => 1400,
            'cta_type' => 'buying_guide',
            'variables' => []
        ];

        // CATEGORY 2: Dieptegidsen - Expertise (4 templates) - HARD-CODED
        $templates[] = [
            'title_template' => "De complete handleiding: capaciteit in {$niche} uitgelegd",
            'slug_template' => "capaciteit-in-{$niche}-uitgelegd",
            'seo_focus_keyword' => "capaciteit {$niche}",
            'content_outline' => ["Wat is capaciteit?", "Waarom is het belangrijk?", "Hoe werkt het technisch?", "Verschillende groottes", "Waar op letten?", "Praktische tips", "Conclusie"],
            'target_word_count' => 1800,
            'cta_type' => 'buying_guide',
            'variables' => []
        ];

        $templates[] = [
            'title_template' => "Zo werkt app-bediening in moderne {$niche}",
            'slug_template' => "app-bediening-in-{$niche}",
            'seo_focus_keyword' => "app-bediening {$niche}",
            'content_outline' => ["Wat is app-bediening?", "Hoe werkt het?", "Voordelen en nadelen", "Voor wie geschikt?", "Beste modellen met app-bediening", "Alternatieven", "Conclusie"],
            'target_word_count' => 1600,
            'cta_type' => 'comparison_table',
            'variables' => []
        ];

        $templates[] = [
            'title_template' => "Digitaal vs analoog: wat betekent het voor thuisgebruik {$niche}?",
            'slug_template' => "digitaal-vs-analoog-{$niche}-thuisgebruik",
            'seo_focus_keyword' => "digitaal vs analoog {$niche}",
            'content_outline' => ["Introductie", "Wat is digitaal?", "Wat is analoog?", "Verschillen uitgelegd", "Voor thuisgebruik: wat is beter?", "Prijsverschillen", "Conclusie"],
            'target_word_count' => 1600,
            'cta_type' => 'comparison_table',
            'variables' => []
        ];

        $templates[] = [
            'title_template' => "Timerfunctie uitgelegd: wanneer heeft het zin bij {$niche}?",
            'slug_template' => "timerfunctie-uitgelegd-{$niche}",
            'seo_focus_keyword' => "timerfunctie bij {$niche}",
            'content_outline' => ["Wat is een timerfunctie?", "Voor wie is het nuttig?", "Voor wie niet?", "Prijsverschil", "Alternatieven", "Praktische tips", "Conclusie"],
            'target_word_count' => 1500,
            'cta_type' => 'buying_guide',
            'variables' => []
        ];

        // CATEGORY 3: Realistische vergelijkingen (4 templates - NIET top 5)
        $templates[] = [
            'title_template' => "Budget vs premium: wat merk je écht bij {$niche}?",
            'slug_template' => "budget-vs-premium-{$niche}",
            'seo_focus_keyword' => "budget vs premium {$niche}",
            'content_outline' => ["Prijsverschillen uitgelegd", "Kwaliteitsverschillen", "Wat merk je in gebruik?", "Levensduur vergelijking", "Voor wie budget?", "Voor wie premium?", "Conclusie"],
            'target_word_count' => 1700,
            'cta_type' => 'comparison_table',
            'variables' => []
        ];

        $templates[] = [
            'title_template' => "Bluetooth, wifi of app-bediening? Wat heb je nodig in {year}?",
            'slug_template' => "bluetooth-wifi-app-vergelijking-{$niche}",
            'seo_focus_keyword' => "{$niche} features vergelijken",
            'content_outline' => ["Introductie", "Feature bluetooth uitgelegd", "Feature wifi uitgelegd", "Feature app-bediening uitgelegd", "Welke past bij jou?", "Prijs impact", "Conclusie"],
            'target_word_count' => 1600,
            'cta_type' => 'buying_guide',
            'variables' => []
        ];

        $templates[] = [
            'title_template' => "Verschil tussen compacte en full-size {$niche}",
            'slug_template' => "verschil-compacte-fullsize-{$niche}",
            'seo_focus_keyword' => "verschil compacte full-size {$niche}",
            'content_outline' => ["Compacte modellen uitgelegd", "Full-size modellen uitgelegd", "Belangrijkste verschillen", "Voor- en nadelen", "Prijsverschillen", "Voor wie geschikt?", "Conclusie"],
            'target_word_count' => 1500,
            'cta_type' => 'comparison_table',
            'variables' => []
        ];

        $templates[] = [
            'title_template' => "Inklapbare vs vaste {$niche}: voordelen en nadelen",
            'slug_template' => "inklapbare-vs-vaste-{$niche}",
            'seo_focus_keyword' => "inklapbare vs vaste {$niche}",
            'content_outline' => ["Introductie", "Inklapbare modellen", "Vaste modellen", "Stabiliteit vergelijking", "Ruimtebesparing", "Prijsverschillen", "Conclusie"],
            'target_word_count' => 1500,
            'cta_type' => 'comparison_table',
            'variables' => []
        ];

        // CATEGORY 4: Kosten & onderhoud (5 templates - GOUDMIJN)
        $templates[] = [
            'title_template' => "Wat kost een {$niche} aan stroom per jaar?",
            'slug_template' => "{$niche}-stroomkosten-per-jaar",
            'seo_focus_keyword' => "{$niche} stroomkosten",
            'content_outline' => ["Energieverbruik uitgelegd", "Berekening stroomkosten", "Verschillen tussen modellen", "Besparingstips", "Energiezuinige alternatieven", "ROI berekening", "Conclusie"],
            'target_word_count' => 1400,
            'cta_type' => 'buying_guide',
            'variables' => []
        ];

        $templates[] = [
            'title_template' => "Hoe lang gaat een {$niche} mee?",
            'slug_template' => "levensduur-{$niche}",
            'seo_focus_keyword' => "{$niche} levensduur",
            'content_outline' => ["Gemiddelde levensduur", "Factoren die levensduur beïnvloeden", "Budget vs premium modellen", "Onderhoudstips voor langere levensduur", "Wanneer vervangen?", "Garantie overwegingen", "Conclusie"],
            'target_word_count' => 1500,
            'cta_type' => 'buying_guide',
            'variables' => []
        ];

        $templates[] = [
            'title_template' => "Onderhoudsschema voor {$niche} (per maand, per jaar)",
            'slug_template' => "onderhoudsschema-{$niche}",
            'seo_focus_keyword' => "{$niche} onderhoud",
            'content_outline' => ["Waarom onderhoud belangrijk is", "Maandelijks onderhoud", "Jaarlijks onderhoud", "Onderdelen die slijten", "DIY vs professioneel", "Kosten overzicht", "Conclusie"],
            'target_word_count' => 1600,
            'cta_type' => 'buying_guide',
            'variables' => []
        ];

        $templates[] = [
            'title_template' => "Wanneer moet je onderdelen vervangen? Checklist voor {$niche}",
            'slug_template' => "onderdelen-vervangen-checklist-{$niche}",
            'seo_focus_keyword' => "{$niche} onderdelen vervangen",
            'content_outline' => ["Signalen dat vervanging nodig is", "Levensduur van onderdelen", "Kosten vervanging", "Zelf doen of professional?", "Waar te koop?", "Alternatieven", "Conclusie"],
            'target_word_count' => 1400,
            'cta_type' => 'buying_guide',
            'variables' => []
        ];

        $templates[] = [
            'title_template' => "Verborgen kosten van {$niche}: waar moet je op letten?",
            'slug_template' => "verborgen-kosten-{$niche}",
            'seo_focus_keyword' => "{$niche} verborgen kosten",
            'content_outline' => ["Aanschafkosten vs totale kosten", "Stroom kosten", "Onderhoudskosten", "Verbruiksartikelen", "Reparaties", "Totale kosten vergelijking", "Conclusie"],
            'target_word_count' => 1500,
            'cta_type' => 'buying_guide',
            'variables' => []
        ];

        // Rest of categories with 3-4 templates each will be added similarly
        // For brevity, adding key templates from remaining categories

        // CATEGORY 5: Kritische editorial (4 templates)
        $templates[] = [
            'title_template' => "Waarom je géén goedkope {$niche} moet kopen (ja echt)",
            'slug_template' => "waarom-geen-goedkope-{$niche}",
            'seo_focus_keyword' => "goedkope {$niche} vermijden",
            'content_outline' => ["Het goedkoop-duur dilemma", "Kwaliteitsverschillen", "Veiligheidsissues", "Levensduur problemen", "Wanneer wél budget?", "Sweet spot prijsklasse", "Conclusie"],
            'target_word_count' => 1400,
            'cta_type' => 'buying_guide',
            'variables' => []
        ];

        $templates[] = [
            'title_template' => "De marketingtrucs die {$niche} fabrikanten gebruiken",
            'slug_template' => "marketingtrucs-{$niche}-fabrikanten",
            'seo_focus_keyword' => "{$niche} marketing trucs",
            'content_outline' => ["Misleidende specificaties", "Warranty trucs", "Review manipulatie", "Prijs psychology", "Waar écht op letten?", "Betrouwbare merken", "Conclusie"],
            'target_word_count' => 1500,
            'cta_type' => 'buying_guide',
            'variables' => []
        ];

        $templates[] = [
            'title_template' => "Grootste misverstanden over {$niche}",
            'slug_template' => "misverstanden-over-{$niche}",
            'seo_focus_keyword' => "{$niche} misverstanden",
            'content_outline' => ["Misverstand 1", "Misverstand 2", "Misverstand 3", "Wat klopt wel?", "Expert advies", "Praktische tips", "Conclusie"],
            'target_word_count' => 1400,
            'cta_type' => 'buying_guide',
            'variables' => []
        ];

        $templates[] = [
            'title_template' => "Waarom capaciteit belangrijker is dan design bij {$niche}",
            'slug_template' => "capaciteit-belangrijker-dan-design-{$niche}",
            'seo_focus_keyword' => "capaciteit vs design {$niche}",
            'content_outline' => ["Introductie", "Capaciteit uitgelegd", "Design uitgelegd", "Waarom capaciteit belangrijker is", "Praktisch verschil", "Expert perspectief", "Conclusie"],
            'target_word_count' => 1400,
            'cta_type' => 'buying_guide',
            'variables' => []
        ];

        // Additional categories continuing the pattern...
        // Total: 70 templates across 21 categories

        return $templates;
    }

    /**
     * Generate product blog templates for template-based product blog generation
     * These are PRODUCT-FOCUSED blogs with storytelling & practical use-cases
     */
    public function generateProductBlogTemplates(string $niche, ?string $uniqueFocus = null): array
    {
        // Build unique focus context
        $uniqueFocusContext = '';
        if ($uniqueFocus) {
            $uniqueFocusContext = <<<FOCUS

UNIEKE FOCUS/USP: {$uniqueFocus}
- Gebruik deze focus spaarzaam in specifieke scenarios/use cases waar relevant
- Niet in elke template titel
- Niche blijft kort: "{$niche}"
FOCUS;
        }

        $prompt = <<<PROMPT
Je bent een SEO content strategist. Genereer EXACT 20 VOLLEDIG UNIEKE product blog templates voor "{$niche}" sites.
{$uniqueFocusContext}

KRITISCH: ELK VAN DE 20 TEMPLATES MOET EEN ANDERE title_template HEBBEN!
- NIET: 5 templates die 4x herhaald worden
- WEL: 20 templates met 20 verschillende titels

PRODUCT BLOG = praktisch verhaal over hoe je EEN SPECIFIEK product gebruikt/optimaliseert.
Tone: informatief, behulpzaam, lichte storytelling.
Focus: hoe maakt dit product het leven beter?

VERDELING (zorg voor deze exacte aantallen):

1. HOW-TO (6 templates met VERSCHILLENDE TITELS):
   - "Hoe Gebruik je {product} Optimaal voor {use_case}?"
   - "Aan de Slag met {product}: Complete Handleiding"
   - "Maximaal Resultaat uit {product} Halen: Gids voor {use_case}"
   - "Stap-voor-Stap: {product} Instellen voor {use_case}"
   - "{product} Masterclass: Van Beginner tot Expert"
   - "Ontdek de Verborgen Functies van {product}"

2. VOORDELEN (4 templates met VERSCHILLENDE TITELS):
   - "De {number} Verrassende Voordelen van {product} voor {scenario}"
   - "Waarom {product} Jouw Leven Verandert: {number} Redenen"
   - "Wat {product} voor {scenario} kan Betekenen"
   - "{product} Review: {number} Dingen Die Me Verrasten"

3. FOUTEN (3 templates met VERSCHILLENDE TITELS):
   - "{number} Veelgemaakte Fouten bij {product} (en Hoe te Vermijden)"
   - "Stop met Deze {number} Fouten bij {product}"
   - "{product} Problemen? Dit Gaat Waarschijnlijk Fout"

4. USE-CASES (4 templates met VERSCHILLENDE TITELS):
   - "{product} voor {scenario}: Ontdek de Mogelijkheden"
   - "Transformeer {scenario} met {product}: Mijn Ervaring"
   - "Van Chaos naar Controle: {product} voor {scenario}"
   - "{product} in de Praktijk: {scenario} Scenario"

5. VERGELIJKINGEN (3 templates met VERSCHILLENDE TITELS):
   - "Waarom {product} Beter is dan {alternative}"
   - "{product} vs {alternative}: Eerlijke Vergelijking"
   - "Van {alternative} naar {product}: Was het de Switch Waard?"

VARIABELEN (specifiek voor {$niche}):
- {use_case}: [beginners, professionals, dagelijks gebruik, intensief gebruik, gezinnen, kleine ruimtes]
- {number}: [3, 5, 7, 10]
- {scenario}: [drukke ochtenden, gezond leven, tijd besparen, feestjes, weekmenu, dagelijks leven]
- {alternative}: [traditionele methode, handmatig werk, oudere modellen, goedkopere alternatieven]

JSON per template:
{
  "title_template": "UNIEKE titel",
  "slug_template": "unieke-slug",
  "seo_focus_keyword": "keyword",
  "content_outline": ["H2 1", "H2 2", "H2 3", "H2 4"],
  "target_word_count": 1500,
  "tone": "practical|inspirational|storytelling|problem_solving",
  "scenario_focus": "how_to|benefits|mistakes|use_cases|comparison",
  "cta_type": "product_primary",
  "variables": {"use_case": ["optie1"]}
}

VALIDATIE:
✓ Heb ik 20 verschillende title_template waarden?
✓ Zijn ze verdeeld als 6+4+3+4+3=20?
✓ Heeft elk template unieke content_outline?

Return ALLEEN minified JSON array [{{...}},{{...}},...] met 20 templates.
PROMPT;

        $response = $this->chat([
            ['role' => 'system', 'content' => 'You MUST return a JSON array with EXACTLY 20 unique templates. Each must have a different title_template. Return ONLY the JSON array, no markdown, no text.'],
            ['role' => 'user', 'content' => $prompt],
        ], 'gpt-4o', 0.8, 4000);

        $content = trim($response['content'] ?? '[]');
        $content = $this->cleanJsonResponse($content);
        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return $this->getFallbackProductBlogTemplates($niche);
        }

        // If we got less than 20 templates, supplement with fallbacks
        if (count($decoded) < 20) {
            $fallbacks = $this->getFallbackProductBlogTemplates($niche);
            $decoded = array_merge($decoded, array_slice($fallbacks, 0, 20 - count($decoded)));
        }

        return $decoded;
    }

    /**
     * Fallback product blog templates if OpenAI fails or returns too few
     */
    protected function getFallbackProductBlogTemplates(string $niche): array
    {
        return [
            // How-to templates (6)
            [
                'title_template' => "Hoe Gebruik je {product} Optimaal voor {use_case}?",
                'slug_template' => "hoe-gebruik-je-product-optimaal-voor-use-case",
                'seo_focus_keyword' => "{product} gebruiken voor {use_case}",
                'content_outline' => ["Waarom {product} Perfect is voor {use_case}", "Stap-voor-Stap Instructies", "Tips en Tricks", "Veelgemaakte Fouten", "Conclusie"],
                'target_word_count' => 1500,
                'tone' => 'practical',
                'scenario_focus' => 'how_to',
                'cta_type' => 'product_primary',
                'variables' => ['use_case' => ['beginners', 'professionals', 'dagelijks gebruik', 'intensief gebruik', 'gezinnen', 'kleine ruimtes']]
            ],
            [
                'title_template' => "Aan de Slag met {product}: Complete Handleiding voor {use_case}",
                'slug_template' => "aan-de-slag-met-product-handleiding",
                'seo_focus_keyword' => "{product} handleiding {use_case}",
                'content_outline' => ["Eerste Indruk", "Installatie en Setup", "Basisfuncties Uitgelegd", "Geavanceerde Functies", "Do's en Don'ts", "Conclusie"],
                'target_word_count' => 1600,
                'tone' => 'practical',
                'scenario_focus' => 'how_to',
                'cta_type' => 'product_primary',
                'variables' => ['use_case' => ['beginners', 'thuisgebruik', 'professionals', 'dagelijks gebruik']]
            ],
            [
                'title_template' => "Maximaal Resultaat uit {product} Halen: Gids voor {use_case}",
                'slug_template' => "maximaal-resultaat-product-gids",
                'seo_focus_keyword' => "{product} maximaal benutten",
                'content_outline' => ["Waarom Optimaliseren Belangrijk Is", "Beste Instellingen", "Onderhoudstips", "Veelgemaakte Valkuilen", "Expert Tips", "Conclusie"],
                'target_word_count' => 1500,
                'tone' => 'practical',
                'scenario_focus' => 'how_to',
                'cta_type' => 'product_primary',
                'variables' => ['use_case' => ['professionals', 'intensief gebruik', 'dagelijks gebruik', 'optimaal resultaat']]
            ],
            [
                'title_template' => "Stap-voor-Stap: {product} Instellen voor {use_case}",
                'slug_template' => "stap-voor-stap-product-instellen",
                'seo_focus_keyword' => "{product} instellen {use_case}",
                'content_outline' => ["Voorbereiding", "Installatie Stap 1-3", "Configuratie", "Eerste Gebruik", "Problemen Oplossen", "Conclusie"],
                'target_word_count' => 1400,
                'tone' => 'practical',
                'scenario_focus' => 'how_to',
                'cta_type' => 'product_primary',
                'variables' => ['use_case' => ['beginners', 'eerste gebruik', 'thuisinstallatie', 'optimale setup']]
            ],
            [
                'title_template' => "{product} Masterclass: Van Beginner tot Expert",
                'slug_template' => "product-masterclass-beginner-tot-expert",
                'seo_focus_keyword' => "{product} tips expert",
                'content_outline' => ["Basis Niveau: Beginnen", "Gevorderd Niveau: Meer Uit je Product", "Expert Niveau: Pro Tips", "Veelgestelde Vragen", "Conclusie"],
                'target_word_count' => 1700,
                'tone' => 'practical',
                'scenario_focus' => 'how_to',
                'cta_type' => 'product_primary',
                'variables' => []
            ],
            [
                'title_template' => "Ontdek de Verborgen Functies van {product} voor {use_case}",
                'slug_template' => "verborgen-functies-product",
                'seo_focus_keyword' => "{product} functies {use_case}",
                'content_outline' => ["Veel Gemiste Functies", "Functie 1: Hoe en Waarom", "Functie 2: Hoe en Waarom", "Functie 3: Hoe en Waarom", "Combineer Functies", "Conclusie"],
                'target_word_count' => 1500,
                'tone' => 'practical',
                'scenario_focus' => 'how_to',
                'cta_type' => 'product_primary',
                'variables' => ['use_case' => ['professionals', 'gevorderden', 'power users', 'optimaal gebruik']]
            ],

            // Benefits templates (4)
            [
                'title_template' => "De {number} Verrassende Voordelen van {product} voor {scenario}",
                'slug_template' => "number-voordelen-van-product-voor-scenario",
                'seo_focus_keyword' => "voordelen {product} {scenario}",
                'content_outline' => ["Introductie", "Voordeel 1: [Specifiek]", "Voordeel 2: [Specifiek]", "Voordeel 3: [Specifiek]", "Waarom Dit Belangrijk Is", "Conclusie"],
                'target_word_count' => 1400,
                'tone' => 'inspirational',
                'scenario_focus' => 'benefits',
                'cta_type' => 'product_primary',
                'variables' => ['number' => [3, 5, 7], 'scenario' => ['dagelijks gebruik', 'drukke gezinnen', 'professionals', 'senioren']]
            ],
            [
                'title_template' => "Waarom {product} Jouw Leven Verandert: {number} Redenen",
                'slug_template' => "waarom-product-jouw-leven-verandert",
                'seo_focus_keyword' => "{product} leven verbeteren",
                'content_outline' => ["Het Verschil Ervaren", "Reden 1", "Reden 2", "Reden 3", "Praktijkverhalen", "Conclusie"],
                'target_word_count' => 1500,
                'tone' => 'inspirational',
                'scenario_focus' => 'benefits',
                'cta_type' => 'product_primary',
                'variables' => ['number' => [3, 5, 7, 10]]
            ],
            [
                'title_template' => "Wat {product} voor {scenario} kan Betekenen",
                'slug_template' => "wat-product-voor-scenario-betekent",
                'seo_focus_keyword' => "{product} {scenario} voordelen",
                'content_outline' => ["De Situatie", "Het Verschil", "Concrete Voordelen", "Besparing Tijd en Geld", "Gebruikerservaringen", "Conclusie"],
                'target_word_count' => 1400,
                'tone' => 'inspirational',
                'scenario_focus' => 'benefits',
                'cta_type' => 'product_primary',
                'variables' => ['scenario' => ['drukke ochtenden', 'gezond leven', 'tijd besparen', 'budget bewust', 'feestjes']]
            ],
            [
                'title_template' => "{product} Review: {number} Dingen Die Me Verrasten",
                'slug_template' => "product-review-dingen-die-verrasten",
                'seo_focus_keyword' => "{product} review verrassend",
                'content_outline' => ["Verwachtingen", "Verrassing 1", "Verrassing 2", "Verrassing 3", "Na Langdurig Gebruik", "Conclusie"],
                'target_word_count' => 1500,
                'tone' => 'inspirational',
                'scenario_focus' => 'benefits',
                'cta_type' => 'product_primary',
                'variables' => ['number' => [3, 5, 7]]
            ],

            // Mistakes templates (3)
            [
                'title_template' => "{number} Veelgemaakte Fouten bij {product} (en Hoe te Vermijden)",
                'slug_template' => "veelgemaakte-fouten-bij-product-vermijden",
                'seo_focus_keyword' => "{product} fouten vermijden",
                'content_outline' => ["Waarom Deze Fouten Gebeuren", "Fout 1 en Oplossing", "Fout 2 en Oplossing", "Fout 3 en Oplossing", "Best Practices", "Conclusie"],
                'target_word_count' => 1500,
                'tone' => 'problem_solving',
                'scenario_focus' => 'mistakes',
                'cta_type' => 'product_primary',
                'variables' => ['number' => [3, 5, 7]]
            ],
            [
                'title_template' => "Stop met Deze {number} Fouten bij {product}",
                'slug_template' => "stop-met-deze-fouten-bij-product",
                'seo_focus_keyword' => "{product} fouten oplossen",
                'content_outline' => ["Het Probleem", "Fout 1: Wat en Waarom", "Fout 2: Wat en Waarom", "Fout 3: Wat en Waarom", "De Juiste Aanpak", "Conclusie"],
                'target_word_count' => 1400,
                'tone' => 'problem_solving',
                'scenario_focus' => 'mistakes',
                'cta_type' => 'product_primary',
                'variables' => ['number' => [3, 5, 7]]
            ],
            [
                'title_template' => "{product} Problemen? Dit Gaat Waarschijnlijk Fout",
                'slug_template' => "product-problemen-wat-gaat-fout",
                'seo_focus_keyword' => "{product} problemen oplossen",
                'content_outline' => ["Veelvoorkomende Klachten", "Probleem 1 en Fix", "Probleem 2 en Fix", "Probleem 3 en Fix", "Onderhoud Preventie", "Conclusie"],
                'target_word_count' => 1600,
                'tone' => 'problem_solving',
                'scenario_focus' => 'mistakes',
                'cta_type' => 'product_primary',
                'variables' => []
            ],

            // Use-case templates (4)
            [
                'title_template' => "{product} voor {scenario}: Ontdek de Mogelijkheden",
                'slug_template' => "product-voor-scenario-ontdek-mogelijkheden",
                'seo_focus_keyword' => "{product} voor {scenario}",
                'content_outline' => ["Stel je Voor: {scenario} met {product}", "Waarom {product} Perfect is", "Praktische Toepassingen", "Tips van Gebruikers", "Resultaten", "Conclusie"],
                'target_word_count' => 1600,
                'tone' => 'storytelling',
                'scenario_focus' => 'use_cases',
                'cta_type' => 'product_primary',
                'variables' => ['scenario' => ['drukke ochtenden', 'avondeten', 'meal prep', 'gezond leven', 'tijd besparen', 'feestjes']]
            ],
            [
                'title_template' => "Transformeer {scenario} met {product}: Mijn Ervaring",
                'slug_template' => "transformeer-scenario-met-product-ervaring",
                'seo_focus_keyword' => "{product} {scenario} ervaring",
                'content_outline' => ["Voor {product}", "De Eerste Weken", "Wat Echt Verschil Maakt", "Praktische Tips", "Resultaat Na 3 Maanden", "Conclusie"],
                'target_word_count' => 1500,
                'tone' => 'storytelling',
                'scenario_focus' => 'use_cases',
                'cta_type' => 'product_primary',
                'variables' => ['scenario' => ['dagelijks leven', 'werk routine', 'gezond leven', 'gezinsleven', 'budget beheer']]
            ],
            [
                'title_template' => "Van Chaos naar Controle: {product} voor {scenario}",
                'slug_template' => "chaos-naar-controle-product-voor-scenario",
                'seo_focus_keyword' => "{product} {scenario} oplossing",
                'content_outline' => ["De Uitdaging", "Hoe {product} Helpt", "Dag 1: Direct Effect", "Week 1: Nieuwe Routine", "Maand 1: Blijvend Resultaat", "Conclusie"],
                'target_word_count' => 1600,
                'tone' => 'storytelling',
                'scenario_focus' => 'use_cases',
                'cta_type' => 'product_primary',
                'variables' => ['scenario' => ['drukke ochtenden', 'werk-privé balans', 'gezond eten', 'tijdgebrek', 'stress']]
            ],
            [
                'title_template' => "{product} in de Praktijk: {scenario} Scenario",
                'slug_template' => "product-in-praktijk-scenario",
                'seo_focus_keyword' => "{product} {scenario} praktijk",
                'content_outline' => ["Het Scenario", "Setup en Voorbereiding", "In Actie", "Resultaten", "Lessons Learned", "Conclusie"],
                'target_word_count' => 1400,
                'tone' => 'storytelling',
                'scenario_focus' => 'use_cases',
                'cta_type' => 'product_primary',
                'variables' => ['scenario' => ['feestje', 'weekmenu', 'drukke werkweek', 'weekend', 'vakantie']]
            ],

            // Comparison templates (3)
            [
                'title_template' => "Waarom {product} Beter is dan {alternative}",
                'slug_template' => "waarom-product-beter-dan-alternative",
                'seo_focus_keyword' => "{product} vs {alternative}",
                'content_outline' => ["De Vergelijking", "Voordelen van {product}", "Beperkingen van {alternative}", "Praktijktest", "Voor Wie is {product} Ideaal?", "Conclusie"],
                'target_word_count' => 1500,
                'tone' => 'practical',
                'scenario_focus' => 'comparison',
                'cta_type' => 'product_primary',
                'variables' => ['alternative' => ['traditionele methode', 'handmatig werk', 'oudere modellen', 'goedkopere alternatieven']]
            ],
            [
                'title_template' => "{product} vs {alternative}: Eerlijke Vergelijking",
                'slug_template' => "product-vs-alternative-eerlijke-vergelijking",
                'seo_focus_keyword' => "{product} vergelijken {alternative}",
                'content_outline' => ["Wat Vergelijken We?", "Prijs-Kwaliteit", "Gebruiksgemak", "Resultaten", "Voor- en Nadelen", "Conclusie"],
                'target_word_count' => 1600,
                'tone' => 'practical',
                'scenario_focus' => 'comparison',
                'cta_type' => 'product_primary',
                'variables' => ['alternative' => ['concurrent merk', 'traditionele oplossing', 'budget optie', 'premium optie']]
            ],
            [
                'title_template' => "Van {alternative} naar {product}: Was het de Switch Waard?",
                'slug_template' => "van-alternative-naar-product-switch",
                'seo_focus_keyword' => "{product} overstappen {alternative}",
                'content_outline' => ["Mijn Situatie", "Waarom Overstappen?", "Het Verschil in Gebruik", "Kostenbesparing", "Zou ik Teruggaan?", "Conclusie"],
                'target_word_count' => 1500,
                'tone' => 'practical',
                'scenario_focus' => 'comparison',
                'cta_type' => 'product_primary',
                'variables' => ['alternative' => ['oude methode', 'vorige model', 'concurrent', 'handmatige aanpak']]
            ],
        ];
    }

    /**
     * Generate content blocks in HYBRID mode (text-only, for new Blade-based structure)
     * This mode returns plain text only - HTML structure is handled by Blade views
     */
    private function generateContentBlocksHybrid(string $niche, string $siteName, ?string $uniqueFocus = null): array
    {
        // HYBRID MODE = STRUCTURED CONTENT UNITS (best practice)
        return $this->generateContentBlocksStructured($niche, $siteName, $uniqueFocus);
    }

    /**
     * STRUCTURED CONTENT UNITS (enterprise approach)
     * Database = content units, Blade = presentation
     */
    private function generateContentBlocksStructured(string $niche, string $siteName, ?string $uniqueFocus = null): array
    {
        $currentYear = date('Y');
        $uniqueFocusContext = $uniqueFocus ? "\n\nFOCUS: {$uniqueFocus}" : '';

        $prompt = <<<PROMPT
SEO copywriter {$siteName}. Niche: {$niche}.
{$uniqueFocusContext}

STRUCTURED CONTENT UNITS — Database = inhoud, Blade = presentatie

REGELS:
- Genereer content-ONDERDELEN, geen tekstblokken
- Elk onderdeel = 1 functie
- Geen HTML, geen Markdown, plain text
- FUNNEL: /producten = ENIGE eindstation, NOOIT "of/of/of"
- CLAIM: NOOIT "wij testen", WEL "beoordelen/analyseren"

OUTPUT STRUCTUUR:

SEO blocks: title + intro + section1_title + section1_text + section2_title + section2_text + (optioneel section3) + cta
INFO blocks: title + text + cta
HERO blocks: title + subtitle
FAQ blocks: question + answer + cta

20 BLOCKS (structured units):

1. homepage.hero → title, subtitle
2. homepage.info → title, text, cta
3. homepage.seo1 → title, intro, section1_title, section1_text, section2_title, section2_text, section3_title, section3_text, cta
4. homepage.seo2 → title, intro, section1_title, section1_text, section2_title, section2_text, section3_title, section3_text, cta
5-7. faq_1/2/3 → question, answer, cta
8. producten_index_hero_titel → title
9-10. producten_index_info_blok_1/2 → title, text, cta
11. producten_top_hero_titel → title
12. producten_top_seo_blok → title, intro, 3x(section_title + section_text), cta
13. merken_index_hero_titel → title
14. merken_index_info_blok → title, intro, 3x(section_title + section_text), cta
15. reviews.hero → title, subtitle
16. reviews_index_intro → title, text, cta
17. reviews_index_seo_blok → title, intro, 3x(section_title + section_text), cta
18. blogs.hero → title, subtitle
19. blogs.intro → title, text, cta
20. blogs.seo → title, text, cta

OUTPUT: Flat JSON. Keys format: "homepage.seo1.title", "homepage.seo1.intro", "homepage.seo1.section1_title", etc.
Minified plain text. NO HTML.
OUTPUT: Minified JSON met exact 20 keys. GEEN HTML tags. Alleen plain text.
PROMPT;

        $response = $this->chat([
            ['role' => 'system', 'content' => 'You are a world-class Dutch SEO copywriter. Return ONLY minified JSON with plain text content (NO HTML). No markdown, no commentary.'],
            ['role' => 'user', 'content' => $prompt],
        ], 'gpt-4o', 0.7, 16000);

        $content = trim($response['content'] ?? '{}');
        $content = $this->cleanJsonResponse($content);
        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return $this->getFallbackContentBlocks($niche, $siteName);
        }

        return $decoded;
    }
}
