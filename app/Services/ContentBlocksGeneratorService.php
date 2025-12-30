<?php

namespace App\Services;

/**
 * ContentBlocksGeneratorService
 *
 * Generates content blocks in 2 modes:
 * - HTML mode: Full HTML blocks (backwards compatible)
 * - Structured mode: Content units (title, intro, sections, cta)
 */
class ContentBlocksGeneratorService
{
    private OpenAIService $openAI;

    public function __construct(OpenAIService $openAI)
    {
        $this->openAI = $openAI;
    }

    /**
     * Generate content blocks
     *
     * @param string $niche
     * @param string $siteName
     * @param string|null $uniqueFocus
     * @param string $format 'html' or 'hybrid' (structured)
     * @return array
     */
    public function generate(string $niche, string $siteName, ?string $uniqueFocus = null, string $format = 'html'): array
    {
        if ($format === 'hybrid') {
            return $this->generateStructured($niche, $siteName, $uniqueFocus);
        }

        return $this->generateHTML($niche, $siteName, $uniqueFocus);
    }

    // ============================================================================
    // HTML MODE (BACKWARDS COMPATIBLE)
    // ============================================================================

/**
     * Generate HTML mode (backwards compatible)
     * Full HTML blocks with <h2>, <h3>, <p> tags
     */
    private function generateHTML(string $niche, string $siteName, ?string $uniqueFocus = null): array
    {
        $currentYear = date('Y');
        $currentMonth = \Carbon\Carbon::now('Europe/Berlin')->locale('de')->translatedFormat('F');

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
Sie sind ein WORLD-CLASS Content-Stratege für deutsche Affiliate-Websites.
Je taak: genereer 20 unieke content blocks voor {$siteName} (niche: {$niche}).
{$uniqueFocusInstruction}

═══════════════════════════════════════════════════════════════════════
GLOBAL CONTEXT
═══════════════════════════════════════════════════════════════════════

Site: {$siteName}
Niche: {$niche}
Jaar: {$currentYear}
Zielgruppe: deutschsprachige Verbraucher die {$niche} willen kopen
Tone: Informatief, behulpzaam, vertrouwenswaardig (geen hype)

═══════════════════════════════════════════════════════════════════════
FUNNEL-POLITIK — NICHT IGNORIEREN
═══════════════════════════════════════════════════════════════════════

HIERARCHIE (Entscheidungsfluss):
1. /produkte = EINZIGER ENTSCHEIDUNGS-HUB (Endziel für Kaufentscheidung)
2. Homepage (/) = Orientierung → führen zu /produkte
3. /blogs = informieren (NIEMALS Endziel) → führen zu /produkte
4. /reviews = vertiefen (NIEMALS Endziel) → führen zu /produkte
5. /top-5 = Shortcut (untergeordnet aan /produkte) → führen zu /produkte
6. /beste-marken = Markenfilter (unterstützend) → führen zu /produkte

VERBODEN GEDRAG (dit mag NOOIT):
✗ Geen "of/of/of" menukaart-zinnen zoals: "Bekijk producten, Top 5, reviews of blogs"
✗ Geen promotie van /blogs, /reviews, /top-5 als navigatie-opties in afsluitingen
✗ Geen inline <a>-links naar /top-5, /reviews, /blogs in lopende tekst
✗ Geen claims als "wij testen" (gebruik: "beoordelen", "analyseren", "vergelijken")
✗ Geen herhaling van dezelfde angles (motorvermogen/loopvlak) in meerdere blocks

CTA-REGEL (keihard):
✓ Elk content block eindigt met EXACT 1 CTA-zin
✓ CTA verwijst ALTIJD naar /produkte (of /produkte met filter voor merken-pagina)
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
Eindigt: ALTIJD naar /produkte (nooit naar Top 5/reviews/blogs)
Mag NIET: Opties aanbieden, menukaart maken

PRODUCTEN (/produkte)
Role: DECISION HUB — hier wordt gekozen
Eindigt: Blijft op /produkte (filters, vergelijken)
Mag NIET: Verwijzen naar Top 5/reviews/blogs als alternatieven

TOP 5 (/top-5)
Role: Shortcut (untergeordnet), snelle keuze
Eindigt: ALTIJD terug naar /produkte voor volledige vergelijking
Mag NIET: Concurreren met /produkte als "beter" eindstation

BESTE MERKEN (/beste-marken)
Role: Merkfilter ingang
Eindigt: Naar /produkte met Markenfilter
Mag NIET: Eindstation worden

REVIEWS (/reviews)
Role: Verdieping, expertise tonen (GEEN eindstation)
Eindigt: ALTIJD naar /produkte voor vergelijking
Mag NIET: "Lees meer reviews" als afsluiting

BLOGS (/blogs)
Role: Informeren, SEO, educatie (GEEN eindstation)
Eindigt: ALTIJD naar /produkte
Mag NIET: Blijven hangen in blog-content


═══════════════════════════════════════════════════════════════════════
BLOCK BRIEFS (20 blocks — elk met unieke focus)
═══════════════════════════════════════════════════════════════════════

─────────────────────────────────────────────────────────────────────
BLOCK 1: homepage.hero
─────────────────────────────────────────────────────────────────────
PAGE: Homepage (/)
PAGE ROLE: Oriëntatie → führen zu /produkte
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
PAGE ROLE: Oriëntatie → führen zu /produkte
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
CTA (laatste zin): "Begin met vergelijken op <a href=\"/produkte\" class=\"text-purple-700 underline\">onze Produktseite</a>."

─────────────────────────────────────────────────────────────────────
BLOCK 3: homepage.seo1
─────────────────────────────────────────────────────────────────────
PAGE: Homepage (/)
PAGE ROLE: Oriëntatie → führen zu /produkte
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
"Klaar om te vergelijken? Starten Sie auf unserer Produktseite."

─────────────────────────────────────────────────────────────────────
BLOCK 4: homepage.seo2
─────────────────────────────────────────────────────────────────────
PAGE: Homepage (/)
PAGE ROLE: Oriëntatie → führen zu /produkte
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
"Start je vergelijking op onze Produktseite."

─────────────────────────────────────────────────────────────────────
BLOCK 5: homepage.faq_1
─────────────────────────────────────────────────────────────────────
PAGE: Homepage (/)
PAGE ROLE: Oriëntatie → führen zu /produkte
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
"Vergleichen Sie alle Optionen op onze Produktseite."

─────────────────────────────────────────────────────────────────────
BLOCK 6: homepage.faq_2
─────────────────────────────────────────────────────────────────────
PAGE: Homepage (/)
PAGE ROLE: Oriëntatie → führen zu /produkte
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
"Ontdek alle modellen op onze Produktseite."

─────────────────────────────────────────────────────────────────────
BLOCK 7: homepage.faq_3
───────────────────────��─────────────────────────────────────────────
PAGE: Homepage (/)
PAGE ROLE: Oriëntatie → führen zu /produkte
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
"Sehen Sie alle opties op onze Produktseite."

─────────────────────────────────────────────────────────────────────
BLOCK 8: producten_index_hero_titel
─────────────────────────────────────────────────────────────────────
PAGE: Producten (/produkte)
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
PAGE: Producten (/produkte)
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
PAGE: Producten (/produkte)
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
PAGE ROLE: Shortcut (untergeordnet aan /produkte)
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
PAGE ROLE: Shortcut (untergeordnet aan /produkte)
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
- Geen promotie van Top 5 als "beter" dan /produkte
- Geen "of/of/of"
CTA (laatste zin): "Wil je meer opties? Bekijk <a href=\"/produkte\" class=\"text-purple-700 underline\">alle {$niche}</a> voor volledige vergelijking."

─────────────────────────────────────────────────────────────────────
BLOCK 13: merken_index_hero_titel
─────────────────────────────────────────────────────────────────────
PAGE: Beste merken (/beste-marken)
PAGE ROLE: Merkfilter ingang → /produkte
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
PAGE: Beste merken (/beste-marken)
PAGE ROLE: Merkfilter ingang → /produkte
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
CTA (laatste zin): "Filter op jouw favoriete merk op <a href=\"/produkte\" class=\"text-purple-700 underline\">onze Produktseite</a>."

─────────────────────────────────────────────────────────────────────
BLOCK 15: reviews.hero
─────────────────────────────────────────────────────���───────────────
PAGE: Reviews (/reviews)
PAGE ROLE: Verdieping (GEEN eindstation) → /produkte
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
PAGE ROLE: Verdieping (GEEN eindstation) → /produkte
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
"Klaar om te kiezen? Vergelijk alle modellen op onze Produktseite."

─────────────────────────────────────────────────────────────────────
BLOCK 17: reviews_index_seo_blok
─────────────────────────────────────────────────────────────────────
PAGE: Reviews (/reviews)
PAGE ROLE: Verdieping (GEEN eindstation) → /produkte
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
"Klaar om te vergelijken? Ga naar onze Produktseite."

─────────────────────────────────────────────────────────────────────
BLOCK 18: blogs.hero
─────────────────────────────────────────────────────────────────────
PAGE: Blogs (/blogs)
PAGE ROLE: Informeren (GEEN eindstation) → /produkte
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
PAGE ROLE: Informeren (GEEN eindstation) → /produkte
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
"Klaar om te kiezen? Vergelijk alle modellen op onze Produktseite."

─────────────────────────────────────────────────────────────────────
BLOCK 20: blogs.seo
─────────────────────────────────────────────────────────────────────
PAGE: Blogs (/blogs)
PAGE ROLE: Informeren (GEEN eindstation) → /produkte
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
- Geen interne links naar andere hubs (GEEN <a href="/beste-marken"> of /reviews of /top-5)
- Geen "lees ook reviews/top5" opties
- Geen inline links

CTA TEMPLATE (kopieer exact):
"Begin met vergelijken op onze Produktseite."

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

        $response = $this->openAI->chat([
            ['role' => 'system', 'content' => 'You are a world-class Dutch SEO copywriter. Return ONLY minified JSON with complete HTML content. No markdown, no commentary, no placeholders.'],
            ['role' => 'user', 'content' => $prompt],
        ], 'gpt-4o', 0.7, 16000);

        $content = trim($response['content'] ?? '{}');
        $content = $this->cleanJsonResponse($content);
        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return $this->getFallback($niche, $siteName);
        }

        return $decoded;
    }

    // ============================================================================
    // STRUCTURED MODE (CONTENT UNITS)
    // ============================================================================

    /**
     * Generate STRUCTURED mode (content units)
     * NEW APPROACH: 20 individual, focused API calls (one per block)
     *
     * Database = content, Blade = presentation
     */
    private function generateStructured(string $niche, string $siteName, ?string $uniqueFocus = null): array
    {
        // Define all 20 blocks to generate
        $blockKeys = [
            // Homepage (6 blocks)
            'homepage.hero',
            'homepage.info',
            'homepage.seo1',
            'homepage.seo2',
            'homepage.faq_1',
            'homepage.faq_2',
            'homepage.faq_3',

            // Producten (4 blocks)
            'producten_index_hero_titel',
            'producten_index_info_blok_1',
            'producten_index_info_blok_2',
            'producten_top_hero_titel',
            'producten_top_seo_blok',

            // Merken (2 blocks)
            'merken_index_hero_titel',
            'merken_index_info_blok',

            // Reviews (3 blocks)
            'reviews.hero',
            'reviews_index_intro',
            'reviews_index_seo_blok',

            // Blogs (3 blocks)
            'blogs.hero',
            'blogs.intro',
            'blogs.seo',
        ];

        $allContent = [];
        $previousContext = []; // Track previously generated content to avoid repetition

        echo "\n🚀 Generating 20 content blocks for {$siteName} ({$niche})...\n\n";

        foreach ($blockKeys as $index => $blockKey) {
            $num = $index + 1;
            echo "  [{$num}/20] Generating {$blockKey}... ";

            try {
                $blockContent = $this->generateSingleBlock($blockKey, $niche, $siteName, $uniqueFocus, $previousContext);

                if (!empty($blockContent)) {
                    // Flatten block content into main array with proper keys
                    foreach ($blockContent as $unit => $content) {
                        $allContent["{$blockKey}.{$unit}"] = $content;
                    }

                    // Add this block to context for next blocks (only for same-page blocks)
                    if (str_starts_with($blockKey, 'homepage.')) {
                        $previousContext['homepage'][] = [
                            'block' => $blockKey,
                            'content' => implode(' ', array_values($blockContent))
                        ];
                    }

                    echo "✓\n";
                } else {
                    echo "⚠ Empty response\n";
                }
            } catch (\Exception $e) {
                echo "✗ Error: " . $e->getMessage() . "\n";
            }

            // Small delay to avoid rate limits
            usleep(200000); // 200ms
        }

        echo "\n✅ Generated " . count($allContent) . " content units across 20 blocks\n\n";

        return $allContent;
    }

    /**
     * OLD STRUCTURED MODE (DEPRECATED - kept for reference)
     * This was the old monolithic prompt approach
     */
    private function generateStructuredOLD(string $niche, string $siteName, ?string $uniqueFocus = null): array
    {
        $currentYear = date('Y');
        $currentMonth = \Carbon\Carbon::now('Europe/Berlin')->locale('de')->translatedFormat('F');

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
Sie sind ein WORLD-CLASS Content-Stratege für deutsche Affiliate-Websites.
Je taak: genereer structured content units voor {$siteName} (niche: {$niche}).
{$uniqueFocusInstruction}

═══════════════════════════════════════════════════════════════════════
GLOBAL CONTEXT
═══════════════════════════════════════════════════════════════════════

Site: {$siteName}
Niche: {$niche}
Jaar: {$currentYear}
Zielgruppe: deutschsprachige Verbraucher die {$niche} willen kopen
Tone: Informatief, behulpzaam, vertrouwenswaardig (geen hype)

═══════════════════════════════════════════════════════════════════════
STRUCTURED MODE — DATABASE = CONTENT, BLADE = PRESENTATION
═══════════════════════════════════════════════════════════════════════

OUTPUT FORMAT:
- GEEN HTML tags (<h2>, <p>, etc)
- GEEN Markdown (**, ##, etc)
- Alleen PLAIN TEXT per unit
- Blade views controleren presentatie

UNIT TYPES:
- HERO: title + subtitle
- INFO: title + text + cta
- SEO: title + intro + section1_title + section1_text + section2_title + section2_text + section3_title + section3_text + cta
- FAQ: question + answer + cta

═══════════════════════════════════════════════════════════════════════
FUNNEL-POLITIK — NICHT IGNORIEREN
═══════════════════════════════════════════════════════════════════════

HIERARCHIE (Entscheidungsfluss):
1. /produkte = EINZIGER ENTSCHEIDUNGS-HUB (Endziel für Kaufentscheidung)
2. Homepage (/) = Orientierung → führen zu /produkte
3. /blogs = informieren (NIEMALS Endziel) → führen zu /produkte
4. /reviews = vertiefen (NIEMALS Endziel) → führen zu /produkte
5. /top-5 = Shortcut (untergeordnet aan /produkte) → führen zu /produkte
6. /beste-marken = Markenfilter (unterstützend) → führen zu /produkte

VERBODEN GEDRAG (dit mag NOOIT):
✗ Geen "of/of/of" menukaart-zinnen zoals: "Bekijk producten, Top 5, reviews of blogs"
✗ Geen promotie van /blogs, /reviews, /top-5 als navigatie-opties in afsluitingen
✗ Geen inline links naar /top-5, /reviews, /blogs in lopende tekst (CTA's wel)
✗ Geen claims als "wij testen" (gebruik: "beoordelen", "analyseren", "vergelijken")
✗ Geen herhaling van dezelfde angles (motorvermogen/loopvlak) in meerdere blocks

CTA-REGEL (keihard):
→ ALLEEN: "Sehen Sie alle {$niche}" of "Start met vergelijken" → /produkte
→ NOOIT: "Lees ook onze reviews" of "Bekijk de Top 5"

═══════════════════════════════════════════════════════════════════════
CLAIM POLICY (CRITICAL — wettelijk)
═══════════════════════════════════════════════════════════════════════

✗ NOOIT (juridisch risico):
  - "wij testen"
  - "onze tests"
  - "uitgebreide tests"
  - "in ons testlab"

✓ WEL TOEGESTAAN (affiliate review):
  - "wij beoordelen"
  - "wij analyseren"
  - "wij vergelijken"
  - "experts beoordelen"
  - "gebruikerservaringen analyseren"

═══════════════════════════════════════════════════════════════════════
STIJLGIDS — CONCREET, ENGAGING, SEO-STERK
═══════════════════════════════════════════════════════════════════════

RULE #1: CONCRETE CIJFERS & DATA
→ Gebruik meetbare voordelen waar mogelijk:
  ✓ "15 minuten kooktijd" (niet: "snel klaarmaken")
  ✓ "80% minder vet" (niet: "gezonder")
  ✓ "€0,20 per gebruik" (niet: "zuinig")
  ✓ "3 op de 5 huishoudens" (niet: "populair")

RULE #2: PROBLEEM → OPLOSSING STRUCTUUR
→ Begin met herkenbaar probleem, dan de oplossing:
  ✓ "Stop met voorverwarmen. Een {$niche} is direct klaar."
  ✓ "Geen ruimte voor een oven? Een compacte {$niche} past overal."
  ✗ "Deze producten zijn geweldig en hebben veel functies."

RULE #3: VERMIJD CLICHÉS & HOLLE TAAL
→ Schrap vage marketing-taal:
  ✗ "ideaal voor", "perfect voor", "populaire keuze"
  ✗ "vele voordelen", "uitstekende kwaliteit"
  ✓ Vervang door specifieke feiten of voorbeelden

RULE #4: ACTIEVE TAAL & KORTE ZINNEN
→ Gebruik actieve zinnen, vermijd passief:
  ✓ "Je bereidt friet in 15 minuten" (actief)
  ✗ "Friet kan bereid worden in 15 minuten" (passief)
→ Max 20-25 woorden per zin voor leesbaarheid

RULE #5: SEO-KEYWORDS NATUURLIJK VERWEVEN
→ Gebruik niche-keywords + long-tail variaties:
  - Primary: "{$niche}"
  - Variaties: "{$niche} kopen", "beste {$niche}", "{$niche} vergelijken"
  - LSI: product-specifieke termen (bijv. "hetelucht frituren", "loopband training")
→ Keyword density: 1-2% (natuurlijk, niet geforceerd)

RULE #6: BLOCK-SPECIFIEKE TOON
Per block type verschillende aanpak:

SEO BLOCKS (homepage.seo1, seo2, reviews_index_seo_blok, etc.):
  → TOON: Educatief, concreet, data-gedreven
  → STRUCTUUR: Intro met cijfer/feit → 3 secties met specifieke voordelen
  → VOORBEELD: "Airfryers gebruiken hete lucht in plaats van olie - tot 80% minder vet..."

INFO BLOCKS (homepage.info, merken_index_info_blok):
  → TOON: Behulpzaam, transparant, USP-gedreven
  → STRUCTUUR: 3 USP's met concrete uitleg hoe het werkt
  → VOORBEELD: "We vergelijken alle modellen op prijs, functies én gebruikerservaringen..."

HERO BLOCKS (homepage.hero, reviews.hero, blogs.hero):
  → TOON: Krachtig, urgent, waarde-gedreven
  → STRUCTUUR: Belofte in title + concreet bewijs in subtitle
  → VOORBEELD: "De beste airfryers van {$currentMonth} {$currentYear} – eerlijk vergeleken"

FAQ BLOCKS (homepage.faq_1/2/3):
  → TOON: Informatief, vraag-gericht, direct antwoord
  → STRUCTUUR: Specifieke vraag → concreet antwoord met cijfers
  → VOORBEELD: "Hoeveel kost een airfryer per maand? Gemiddeld €5-8 aan stroom..."

═══════════════════════════════════════════════════════════════════════
CONTENT BLOCK SPECIFICATIES (20 BLOCKS)
═══════════════════════════════════════════════════════════════════════

─────────────────────────────────────────────────────────────────────
BLOCK 1: homepage.hero
─────────────────────────────────────────────────────────────────────
TYPE: HERO
UNITS: title, subtitle

PAGE: Homepage (/)
PAGE ROLE: Oriëntatiepunt → /produkte
BLOCK ROLE: Eerste indruk + immediate waarde communiceren
FOCUS TAG: "actueel-vergelijk-bespaar"

FORBIDDEN TOPICS:
✗ Geen "welkom bij {$siteName}" → redundant
✗ Geen "grootste collectie" → onverifieerbaar
✗ Geen promotie van reviews/blogs/Top 5

MUST TREAT:
1. TITLE: Actuele maand/jaar + waarde (vergelijken OF besparen, niet beide)
2. SUBTITLE: Waarom deze site? (unieke waarde in 1 zin, 100-150 tekens)

LENGTES:
- title: 60-90 tekens (inclusief {$currentMonth} {$currentYear})
- subtitle: 100-150 tekens

VOORBEELDEN:
title: "De beste {$niche} van {$currentMonth} {$currentYear} – Eerlijk vergeleken"
subtitle: "Vind in 2 minuten welk model bij jou past. Geen verkooppraatjes, alleen feiten."

OUTPUT KEYS:
- homepage.hero.title
- homepage.hero.subtitle

─────────────────────────────────────────────────────────────────────
BLOCK 2: homepage.info
─────────────────────────────────────────────────────────────────────
TYPE: INFO
UNITS: title, text, cta

PAGE: Homepage (/)
PAGE ROLE: Oriëntatiepunt → /produkte
BLOCK ROLE: Site-waarde uitleggen (waarom deze site gebruiken?)
FOCUS TAG: "transparantie-snelheid-volledigheid"

FORBIDDEN TOPICS:
✗ Geen herhaling van hero-content
✗ Geen producteigenschappen (motorvermogen, loopvlak) → dat is SEO block
✗ Geen "wij zijn de beste" claims

MUST TREAT:
1. TITLE: Waarom deze site? (H2-niveau, 40-60 tekens)
2. TEXT: 3 USP's in doorlopende tekst (200-300 woorden)
   - USP 1: Transparante vergelijking (hoe?)
   - USP 2: Tijdsbesparing (hoe?)
   - USP 3: Volledigheid (wat vergelijken we?)

LENGTES:
- title: 40-60 tekens
- text: 200-300 woorden

VOORBEELD TEXT structuur:
"[USP 1 in 2-3 zinnen]. [USP 2 in 2-3 zinnen]. [USP 3 in 2-3 zinnen]."

NOTE: Geen CTA genereren - button wordt hardcoded in Blade view

OUTPUT KEYS:
- homepage.info.title
- homepage.info.text

─────────────────────────────────────────────────────────────────────
BLOCK 3: homepage.seo1
─────────────────────────────────────────────────────────────────────
TYPE: SEO
UNITS: title, intro, section1_title, section1_text, section2_title, section2_text, section3_title, section3_text, cta

PAGE: Homepage (/)
PAGE ROLE: Oriëntatiepunt → /produkte
BLOCK ROLE: Waarom {$niche}? (categorie-waarde aantonen)
FOCUS TAG: "waarom-voordelen-toepassingen"

FORBIDDEN TOPICS:
✗ Geen "hoe kies je?" → dat is homepage.seo2
✗ Geen productadvies/kooptips → dat is /produkte
✗ Geen merkenvergelijking → dat is /beste-marken

MUST TREAT:
1. TITLE: Waarom {$niche}? (H2, 40-60 tekens)
2. INTRO: Opener over categorie-waarde (150-200 woorden)
3. SECTION 1: Praktische voordelen (titel + 200-250w)
4. SECTION 2: Toepassingen/situaties (titel + 200-250w)
5. SECTION 3: Innovaties/trends (titel + 200-250w)

STIJL (CRITICAL - volg STIJLGIDS):
→ INTRO moet starten met concreet cijfer/feit over de niche
  Voorbeeld: "Airfryers gebruiken hete lucht in plaats van olie - tot 80% minder vet..."
→ Elk SECTION moet probleem→oplossing structuur hebben
→ Gebruik concrete cijfers: tijd, percentages, kosten, gebruiksstatistieken
→ Vermijd clichés zoals "ideaal voor", "populaire keuze"
→ Focus op meetbare voordelen, niet vage claims

LENGTES:
- title: 40-60 tekens
- intro: 150-200 woorden
- section1_title: 40-60 tekens (H3)
- section1_text: 200-250 woorden
- section2_title: 40-60 tekens (H3)
- section2_text: 200-250 woorden
- section3_title: 40-60 tekens (H3)
- section3_text: 200-250 woorden

TOTAAL: 800-1000 woorden

NOTE: Geen CTA genereren - button wordt hardcoded in Blade view

OUTPUT KEYS:
- homepage.seo1.title
- homepage.seo1.intro
- homepage.seo1.section1_title
- homepage.seo1.section1_text
- homepage.seo1.section2_title
- homepage.seo1.section2_text
- homepage.seo1.section3_title
- homepage.seo1.section3_text

─────────────────────────────────────────────────────────────────────
BLOCK 4: homepage.seo2
─────────────────────────────────────────────────────────────────────
TYPE: SEO
UNITS: title, intro, section1_title, section1_text, section2_title, section2_text, section3_title, section3_text, cta

PAGE: Homepage (/)
PAGE ROLE: Oriëntatiepunt → /produkte
BLOCK ROLE: Hoe kies je? (koopproces voorbereiden)
FOCUS TAG: "keuzeproces-specificaties-budget"

FORBIDDEN TOPICS:
✗ Geen herhaling "waarom {$niche}" → dat was homepage.seo1
✗ Geen concrete productadvies → dat is /produkte
✗ Geen merkenvergelijking → dat is /beste-marken

MUST TREAT:
1. TITLE: Hoe kies je de juiste {$niche}? (H2, 40-60 tekens)
2. INTRO: Keuzeproces framing (150-200w)
3. SECTION 1: Belangrijkste specs/features (titel + 200-250w)
4. SECTION 2: Budget/prijsklassen (titel + 200-250w)
5. SECTION 3: Situatie/gebruik (titel + 200-250w)

LENGTES:
- title: 40-60 tekens
- intro: 150-200 woorden
- section1_title: 40-60 tekens
- section1_text: 200-250 woorden
- section2_title: 40-60 tekens
- section2_text: 200-250 woorden
- section3_title: 40-60 tekens
- section3_text: 200-250 woorden

TOTAAL: 800-1000 woorden

NOTE: Geen CTA genereren - button wordt hardcoded in Blade view

OUTPUT KEYS:
- homepage.seo2.title
- homepage.seo2.intro
- homepage.seo2.section1_title
- homepage.seo2.section1_text
- homepage.seo2.section2_title
- homepage.seo2.section2_text
- homepage.seo2.section3_title
- homepage.seo2.section3_text

─────────────────────────────────────────────────────────────────────
BLOCK 5-7: homepage.faq_1, homepage.faq_2, homepage.faq_3
─────────────────────────────────────────────────────────────────────
TYPE: FAQ
UNITS: question, answer, cta

PAGE: Homepage (/)
BLOCK ROLE: Veelgestelde vragen beantwoorden
FOCUS TAG: "faq-quick-answers"

FORBIDDEN TOPICS:
✗ Geen productspecifieke vragen → dat is product detail pages
✗ Geen "welk merk is beste?" → dat is /beste-marken
✗ Geen "wat zijn beste modellen?" → dat is /top-5

MUST TREAT (3 FAQ's):
FAQ 1: Algemene categorie vraag (bijv. "Wat is verschil tussen type X en Y?")
FAQ 2: Koopproces vraag (bijv. "Waar moet ik op letten bij aankoop?")
FAQ 3: Praktische vraag (bijv. "Hoeveel kost een goede {$niche}?")

LENGTES (per FAQ):
- question: 60-100 tekens
- answer: 150-250 woorden

NOTE: Geen CTA genereren - FAQ's staan op homepage, algemene CTA volgt na FAQ sectie

OUTPUT KEYS:
- homepage.faq_1.question
- homepage.faq_1.answer
- homepage.faq_2.question
- homepage.faq_2.answer
- homepage.faq_3.question
- homepage.faq_3.answer

─────────────────────────────────────────────────────────────────────
BLOCK 8: producten_index_hero_titel
─────────────────────────────────────────────────────────────────────
TYPE: HERO (title only)
UNITS: title

PAGE: Productoverzicht (/produkte)
PAGE ROLE: Beslissingscentrum (ENIGE EINDSTATION)
BLOCK ROLE: Pagina framing
FOCUS TAG: "volledig-overzicht"

LENGTES:
- title: 40-60 tekens

MUST INCLUDE:
- "Alle {$niche}"
- GEEN extra woorden

VOORBEELD:
"Alle {$niche} vergeleken"

OUTPUT KEYS:
- producten_index_hero_titel.title

─────────────────────────────────────────────────────────────────────
BLOCK 9: producten_index_info_blok_1
─────────────────────────────────────────────────────────────────────
TYPE: INFO
UNITS: title, text, cta

PAGE: Productoverzicht (/produkte)
PAGE ROLE: Beslissingscentrum (ENIGE EINDSTATION)
BLOCK ROLE: Filterwaarde uitleggen
FOCUS TAG: "filter-specificaties-vergelijk"

FORBIDDEN TOPICS:
✗ Geen algemene "waarom {$niche}" → dat was homepage.seo1
✗ Geen Top 5/reviews/blogs promotie
✗ Geen "koop hier" messaging → dit is vergelijkingsplatform

MUST TREAT:
1. TITLE: Hoe te filteren/vergelijken? (40-60 tekens)
2. TEXT: Uitleg filter-functionaliteit + specs uitleg (300-400 woorden)
   - Welke filters beschikbaar
   - Hoe specificaties interpreteren
   - Vergelijkingstabel gebruiken

LENGTES:
- title: 40-60 tekens
- text: 300-400 woorden

NOTE: Geen CTA nodig - /produkte is eindstation, producten staan erboven

OUTPUT KEYS:
- producten_index_info_blok_1.title
- producten_index_info_blok_1.text

─────────────────────────────────────────────────────────────────────
BLOCK 10: producten_index_info_blok_2
─────────────────────────────────────────────────────────────────────
TYPE: INFO
UNITS: title, text, cta

PAGE: Productoverzicht (/produkte)
PAGE ROLE: Beslissingscentrum (ENIGE EINDSTATION)
BLOCK ROLE: Prijs/waarde positionering
FOCUS TAG: "prijsklassen-waarde-besparen"

FORBIDDEN TOPICS:
✗ Geen herhaling van filter-uitleg → dat was info_blok_1
✗ Geen merkenvergelijking → dat is /beste-marken
✗ Geen "deze is beste" → laat gebruiker kiezen

MUST TREAT:
1. TITLE: Prijs vs waarde (40-60 tekens)
2. TEXT: Prijsklassen + waar te besparen (300-400 woorden)
   - Budget vs premium positioning
   - Waar betaal je voor?
   - Bespaartips (zonder productadvies)

LENGTES:
- title: 40-60 tekens
- text: 300-400 woorden

NOTE: Geen CTA nodig - /produkte is eindstation, producten staan erboven

OUTPUT KEYS:
- producten_index_info_blok_2.title
- producten_index_info_blok_2.text

─────────────────────────────────────────────────────────────────────
BLOCK 11: producten_top_hero_titel
─────────────────────────────────────────────────────────────────────
TYPE: HERO (title only)
UNITS: title

PAGE: Top 5 (/top-5)
PAGE ROLE: Shortcut (untergeordnet aan /produkte)
BLOCK ROLE: Pagina framing
FOCUS TAG: "top5-actueel"

LENGTES:
- title: 60-80 tekens

MUST INCLUDE:
- "Top 5 beste {$niche} van {$currentMonth} {$currentYear}"
- Gebruik exact {$currentMonth} en {$currentYear}

VOORBEELD:
"Top 5 beste {$niche} van {$currentMonth} {$currentYear}"

OUTPUT KEYS:
- producten_top_hero_titel.title

─────────────────────────────────────────────────────────────────────
BLOCK 12: producten_top_seo_blok
─────────────────────────────────────────────────────────────────────
TYPE: SEO
UNITS: title, intro, section1_title, section1_text, section2_title, section2_text, section3_title, section3_text, cta

PAGE: Top 5 (/top-5)
PAGE ROLE: Shortcut (untergeordnet aan /produkte)
BLOCK ROLE: Selectieproces uitleggen
FOCUS TAG: "selectiecriteria-methodiek"

FORBIDDEN TOPICS:
✗ Geen promotie van Top 5 als "beter" dan /produkte
✗ Geen "of/of/of" navigatie
✗ Geen concrete productnamen in tekst

MUST TREAT:
1. TITLE: Hoe selecteren we de Top 5? (40-60 tekens)
2. INTRO: Transparantie over selectie (150-200w)
3. SECTION 1: Onze selectiecriteria (titel + 200-250w)
   - 4 criteria uitleggen
4. SECTION 2: Voor wie is welk model? (titel + 200-250w)
   - Algemeen per positie (1-2, 3, 4-5)
   - GEEN productnamen
5. SECTION 3: Vergelijkingstabel gebruiken (titel + 200-250w)
   - Praktische tips

LENGTES:
- title: 40-60 tekens
- intro: 150-200 woorden
- section1_title: 40-60 tekens
- section1_text: 200-250 woorden
- section2_title: 40-60 tekens
- section2_text: 200-250 woorden
- section3_title: 40-60 tekens
- section3_text: 200-250 woorden

TOTAAL: 800-1000 woorden

NOTE: Geen CTA genereren - button wordt hardcoded in Blade view

OUTPUT KEYS:
- producten_top_seo_blok.title
- producten_top_seo_blok.intro
- producten_top_seo_blok.section1_title
- producten_top_seo_blok.section1_text
- producten_top_seo_blok.section2_title
- producten_top_seo_blok.section2_text
- producten_top_seo_blok.section3_title
- producten_top_seo_blok.section3_text

─────────────────────────────────────────────────────────────────────
BLOCK 13: merken_index_hero_titel
─────────────────────────────────────────────────────────────────────
TYPE: HERO (title + subtitle)
UNITS: title, subtitle

PAGE: Beste merken (/beste-marken)
PAGE ROLE: Merkfilter ingang → /produkte
BLOCK ROLE: Pagina framing
FOCUS TAG: "merken-vergelijk"

LENGTES:
- title: 50-70 tekens
- subtitle: 80-120 tekens

MUST INCLUDE:
- title: "De beste merken {$niche} vergeleken"
- subtitle: Waarom merk belangrijk is (1 zin)

OUTPUT KEYS:
- merken_index_hero_titel.title
- merken_index_hero_titel.subtitle

─────────────────────────────────────────────────────────────────────
BLOCK 14: merken_index_info_blok
─────────────────────────────────────────────────────────────────────
TYPE: SEO
UNITS: title, intro, section1_title, section1_text, section2_title, section2_text, section3_title, section3_text, cta

PAGE: Beste merken (/beste-marken)
PAGE ROLE: Merkfilter ingang → /produkte
BLOCK ROLE: Merkpositionering uitleggen
FOCUS TAG: "merkwaarde-garantie-service"

FORBIDDEN TOPICS:
✗ Geen productspecifieke tips → dat is /produkte
✗ Geen Top 5/reviews/blogs promotie

MUST TREAT:
1. TITLE: Waarom merk belangrijk is (40-60 tekens)
2. INTRO: Verschil tussen merken (150-200w)
3. SECTION 1: De belangrijkste merken (titel + 200-250w)
   - 5-7 ECHTE merknamen (Philips, Tefal, etc)
   - GEEN "[Merk 1]" placeholders
4. SECTION 2: A-merk vs budget (titel + 200-250w)
   - Wanneer is welke waard?
5. SECTION 3: Garantie en service (titel + 200-250w)
   - Lange termijn waarde

LENGTES:
- title: 40-60 tekens
- intro: 150-200 woorden
- section1_title: 40-60 tekens
- section1_text: 200-250 woorden (met ECHTE merknamen)
- section2_title: 40-60 tekens
- section2_text: 200-250 woorden
- section3_title: 40-60 tekens
- section3_text: 200-250 woorden

TOTAAL: 800-1000 woorden

NOTE: Geen CTA genereren - button wordt hardcoded in Blade view

OUTPUT KEYS:
- merken_index_info_blok.title
- merken_index_info_blok.intro
- merken_index_info_blok.section1_title
- merken_index_info_blok.section1_text
- merken_index_info_blok.section2_title
- merken_index_info_blok.section2_text
- merken_index_info_blok.section3_title
- merken_index_info_blok.section3_text

─────────────────────────────────────────────────────────────────────
BLOCK 15: reviews.hero
─────────────────────────────────────────────────────────────────────
TYPE: HERO
UNITS: title, subtitle

PAGE: Reviews (/reviews)
PAGE ROLE: Verdieping (GEEN eindstation) → /produkte
BLOCK ROLE: Pagina framing + waarde uitleggen
FOCUS TAG: "expertbeoordelingen"

FORBIDDEN TOPICS:
✗ Geen uitleg reviewproces → dat is reviews_index_seo_blok
✗ Geen "wij testen" claims

MUST TREAT:
1. TITLE: Reviews over {$niche} (50-70 tekens)
2. SUBTITLE: Waarom betrouwbaar? (80-120 tekens)

CLAIM POLICY:
✗ NOOIT: "wij testen", "onze tests"
✓ WEL: "wij beoordelen", "experts analyseren"

LENGTES:
- title: 50-70 tekens
- subtitle: 80-120 tekens

OUTPUT KEYS:
- reviews.hero.title
- reviews.hero.subtitle

─────────────────────────────────────────────────────────────────────
BLOCK 16: reviews_index_intro
─────────────────────────────────────────────────────────────────────
TYPE: INFO
UNITS: title, text, cta

PAGE: Reviews (/reviews)
PAGE ROLE: Verdieping (GEEN eindstation) → /produkte
BLOCK ROLE: Waarom reviews waardevol?
FOCUS TAG: "review-waarde-betrouwbaarheid"

FORBIDDEN TOPICS:
✗ Geen reviewproces uitleg → dat is reviews_index_seo_blok
✗ Geen productadvies → dat is /produkte

MUST TREAT:
1. TITLE: Waarom reviews lezen? (40-60 tekens)
2. TEXT: Waarde van reviews (200-300 woorden)
   - Wat bieden reviews?
   - Hoe gebruiken?
   - Transitie naar /produkte

LENGTES:
- title: 40-60 tekens
- text: 200-300 woorden

NOTE: Geen CTA genereren - button wordt hardcoded in Blade view

OUTPUT KEYS:
- reviews_index_intro.title
- reviews_index_intro.text

─────────────────────────────────────────────────────────────────────
BLOCK 17: reviews_index_seo_blok
─────────────────────────────────────────────────────────────────────
TYPE: SEO
UNITS: title, intro, section1_title, section1_text, section2_title, section2_text, section3_title, section3_text, cta

PAGE: Reviews (/reviews)
PAGE ROLE: Verdieping (GEEN eindstation) → /produkte
BLOCK ROLE: Reviewproces + methodiek uitleggen
FOCUS TAG: "review-methodiek-criteria"

FORBIDDEN TOPICS:
✗ Geen "waarom reviews waardevol" → dat was reviews_index_intro
✗ Geen productadvies → dat is /produkte

MUST TREAT:
1. TITLE: Hoe beoordelen wij {$niche}? (40-60 tekens)
2. INTRO: Transparantie over proces (150-200w)
3. SECTION 1: Onze beoordelingscriteria (titel + 200-250w)
4. SECTION 2: Hoe scoren we? (titel + 200-250w)
5. SECTION 3: Van review naar keuze (titel + 200-250w)

CLAIM POLICY:
✗ NOOIT: "wij testen", "in ons testlab"
✓ WEL: "wij beoordelen", "wij analyseren"

LENGTES:
- title: 40-60 tekens
- intro: 150-200 woorden
- section1_title: 40-60 tekens
- section1_text: 200-250 woorden
- section2_title: 40-60 tekens
- section2_text: 200-250 woorden
- section3_title: 40-60 tekens
- section3_text: 200-250 woorden

TOTAAL: 800-1000 woorden

NOTE: Geen CTA genereren - button wordt hardcoded in Blade view

OUTPUT KEYS:
- reviews_index_seo_blok.title
- reviews_index_seo_blok.intro
- reviews_index_seo_blok.section1_title
- reviews_index_seo_blok.section1_text
- reviews_index_seo_blok.section2_title
- reviews_index_seo_blok.section2_text
- reviews_index_seo_blok.section3_title
- reviews_index_seo_blok.section3_text

─────────────────────────────────────────────────────────────────────
BLOCK 18: blogs.hero
─────────────────────────────────────────────────────────────────────
TYPE: HERO
UNITS: title, subtitle

PAGE: Blogs (/blogs)
PAGE ROLE: Informeren (GEEN eindstation) → /produkte
BLOCK ROLE: Pagina framing
FOCUS TAG: "tips-trends-achtergrond"

LENGTES:
- title: 50-70 tekens
- subtitle: 80-120 tekens

MUST INCLUDE:
- title: Blogs over {$niche}
- subtitle: Wat te verwachten (tips, trends, etc)

OUTPUT KEYS:
- blogs.hero.title
- blogs.hero.subtitle

─────────────────────────────────────────────────────────────────────
BLOCK 19: blogs.intro
─────────────────────────────────────────────────────────────────────
TYPE: INFO
UNITS: title, text, cta

PAGE: Blogs (/blogs)
PAGE ROLE: Informeren (GEEN eindstation) → /produkte
BLOCK ROLE: Waarom blogs waardevol?
FOCUS TAG: "inspiratie-kennis-achtergrond"

FORBIDDEN TOPICS:
✗ Geen SEO-achtige content → dat is blogs.seo
✗ Geen productadvies → dat is /produkte

MUST TREAT:
1. TITLE: Waarom onze blogs? (40-60 tekens)
2. TEXT: Waarde van blogs (200-300 woorden)
   - Inspiratie + kennis
   - Transitie naar /produkte

LENGTES:
- title: 40-60 tekens
- text: 200-300 woorden

NOTE: Geen CTA genereren - button wordt hardcoded in Blade view

OUTPUT KEYS:
- blogs.intro.title
- blogs.intro.text

─────────────────────────────────────────────────────────────────────
BLOCK 20: blogs.seo
─────────────────────────────────────────────────────────────────────
TYPE: SEO
UNITS: title, intro, section1_title, section1_text, section2_title, section2_text, section3_title, section3_text, cta

PAGE: Blogs (/blogs)
PAGE ROLE: Informeren (GEEN eindstation) → /produkte
BLOCK ROLE: Diepere context over {$niche} categorie
FOCUS TAG: "categorie-ontwikkeling-trends"

FORBIDDEN TOPICS:
✗ Geen "waarom blogs waardevol" → dat was blogs.intro
✗ Geen productadvies → dat is /produkte
✗ Geen kooptips → dat is homepage.seo2

MUST TREAT:
1. TITLE: {$niche} trends & ontwikkeling (40-60 tekens)
2. INTRO: Categorie-context (150-200w)
3. SECTION 1: Recente ontwikkelingen (titel + 200-250w)
4. SECTION 2: Toekomstige trends (titel + 200-250w)
5. SECTION 3: Wat betekent dit voor jou? (titel + 200-250w)

LENGTES:
- title: 40-60 tekens
- intro: 150-200 woorden
- section1_title: 40-60 tekens
- section1_text: 200-250 woorden
- section2_title: 40-60 tekens
- section2_text: 200-250 woorden
- section3_title: 40-60 tekens
- section3_text: 200-250 woorden

TOTAAL: 800-1000 woorden

NOTE: Geen CTA genereren - button wordt hardcoded in Blade view

OUTPUT KEYS:
- blogs.seo.title
- blogs.seo.intro
- blogs.seo.section1_title
- blogs.seo.section1_text
- blogs.seo.section2_title
- blogs.seo.section2_text
- blogs.seo.section3_title
- blogs.seo.section3_text

═══════════════════════════════════════════════════════════════════════
JSON OUTPUT FORMAT
═══════════════════════════════════════════════════════════════════════

Return FLAT JSON met alle keys:

{
  "homepage.hero.title": "...",
  "homepage.hero.subtitle": "...",
  "homepage.info.title": "...",
  "homepage.info.text": "...",
  "homepage.info.cta": "...",
  "homepage.seo1.title": "...",
  "homepage.seo1.intro": "...",
  "homepage.seo1.section1_title": "...",
  "homepage.seo1.section1_text": "...",
  "homepage.seo1.section2_title": "...",
  "homepage.seo1.section2_text": "...",
  "homepage.seo1.section3_title": "...",
  "homepage.seo1.section3_text": "...",
  "homepage.seo1.cta": "...",
  (... etc voor alle 20 blocks)
}

CRITICAL:
- Alleen PLAIN TEXT (geen HTML, geen Markdown)
- Minified JSON
- Alle lengtes respecteren
- Alle FORBIDDEN TOPICS vermijden
- Funnel policy volgen
- Claim policy volgen

Return ALLEEN minified JSON. Geen markdown, geen commentary.
PROMPT;

        $response = $this->openAI->chat([
            ['role' => 'system', 'content' => 'You are a world-class Dutch SEO copywriter. Return ONLY minified JSON with plain text content (NO HTML, NO Markdown). No markdown blocks, no commentary, no placeholders.'],
            ['role' => 'user', 'content' => $prompt],
        ], 'gpt-4o', 0.7, 16000);

        $content = trim($response['content'] ?? '{}');
        $content = $this->cleanJsonResponse($content);
        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return $this->getFallback($niche, $siteName);
        }

        return $decoded;
    }

    /**
     * TEMPORARY: Compact structured prompt (will be replaced with detailed version)
     */
    private function generateStructuredCompact(string $niche, string $siteName, ?string $uniqueFocus = null): array
    {
        $currentYear = date('Y');
        $uniqueFocusContext = $uniqueFocus ? "\n\nFOCUS: {$uniqueFocus}" : '';

        $prompt = <<<PROMPT
SEO copywriter {$siteName}. Niche: {$niche}.
{$uniqueFocusContext}

SCHRIJFSTIJL (VERPLICHT):
✓ Schrijf zoals je SPREEKT, niet zoals een brochure
✓ "als je snel wilt" NIET "wanneer je snel een X op tafel wilt"
✓ "voor wie vaak X" NIET "voor wie veelvuldig X onderneemt"
✓ Concreet en direct, geen corporate jargon
✓ SPECIFIEKE situaties, GEEN vage frasen

VERBODEN MARKETING/AI TAAL (STRENG):
❌ "na een lange werkdag" - VERBODEN
❌ "voor veel mensen" / "voor velen" - VERBODEN
❌ "ideaal" - VERBODEN
❌ "handig" - VERBODEN
❌ "perfect" - VERBODEN
❌ "uitstekend" - VERBODEN
❌ "geweldig" - VERBODEN
❌ "essentieel" - VERBODEN
❌ "cruciaal" - VERBODEN
❌ "optimaal" - VERBODEN

SCHRIJF CONCREET IN PLAATS VAN VAAG:
✓ "in een appartement" i.p.v. "ideaal voor"
✓ "bij beperkte ruimte" i.p.v. "handig voor"
✓ "zonder buren te storen" i.p.v. "perfect voor"
✓ "opklapbaar voor 10m² ruimtes" i.p.v. "ideaal op te bergen"
✓ "geschikt voor hardlopen tot 15km/u" i.p.v. "perfect voor joggen"

HOMEPAGE BLOCKS - unieke rollen:
- homepage.info: WAT we vergelijken (site-functie). GEEN product-eigenschappen.
- homepage.seo1: WAAROM dit product (voordelen).
- homepage.seo2: HOE kies je (keuzehulp: gebruik, budget, capaciteit).
- FAQs: Praktische vragen over {niche}.

STRUCTURED CONTENT UNITS — Database = inhoud, Blade = presentatie

REGELS:
- Genereer content-ONDERDELEN, geen tekstblokken
- Elk onderdeel = 1 functie
- Geen HTML, geen Markdown, plain text
- FUNNEL: /produkte = ENIGE eindstation, NOOIT "of/of/of"
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
PROMPT;

        $response = $this->openAI->chat([
            ['role' => 'system', 'content' => 'You are a world-class Dutch SEO copywriter. Return ONLY minified JSON with plain text content (NO HTML). No markdown, no commentary.'],
            ['role' => 'user', 'content' => $prompt],
        ], 'gpt-4o', 0.7, 16000);

        $content = trim($response['content'] ?? '{}');
        // cleanJsonResponse() removed - not needed with structured outputs
        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return $this->getFallback($niche, $siteName);
        }

        return $decoded;
    }

    // ============================================================================
    // HELPER METHODS
    // ============================================================================

    /**
     * Clean JSON response from AI
     */
    /**
     * DEPRECATED: cleanJsonResponse() is no longer needed
     * With OpenAI Structured Outputs (strict JSON Schema), responses are ALWAYS valid JSON
     * No markdown wrappers, no parsing errors, no cleanup required
     */

    /**
     * Fallback content blocks (HTML mode)
     */
    private function getFallback(string $niche, string $siteName): array
    {
        $currentYear = date('Y');
        $currentMonth = date('F');

        return [
            'homepage.hero' => "De beste {$niche} van {$currentMonth} {$currentYear} – Vergelijk en bespaar",
            'homepage.info' => "<h2>Welkom bij {$siteName}</h2><p>Vind de perfecte {$niche} voor jouw situatie. Wij vergelijken specificaties, prijzen en reviews zodat jij de beste keuze kunt maken.</p><p>Start met vergelijken op onze Produktseite.</p>",
            'homepage.seo1' => "<h2>Waarom kiezen voor {$niche}?</h2><p>Ontdek de voordelen en mogelijkheden.</p>",
            'homepage.seo2' => "<h2>Hoe kies je de juiste {$niche}?</h2><p>Let op deze belangrijke factoren bij je aankoop.</p>",
            // ... etc
        ];
    }

    // ============================================================================
    // PER-BLOCK GENERATION (NEW - PARALLEL APPROACH)
    // ============================================================================

    /**
     * Generate a single content block with focused prompt
     *
     * @param string $blockKey Block identifier (e.g., 'homepage.hero')
     * @param string $niche Product niche
     * @param string $siteName Site name
     * @param string|null $uniqueFocus Optional unique focus/USP
     * @return array Block content units
     */
    private function generateSingleBlock(string $blockKey, string $niche, string $siteName, ?string $uniqueFocus = null, array $previousContext = []): array
    {
        $currentYear = date('Y');
        $currentMonth = \Carbon\Carbon::now('Europe/Berlin')->locale('de')->translatedFormat('F');

        // Get block definition
        $blockDef = $this->getBlockDefinition($blockKey, $niche, $siteName, $currentMonth, $currentYear, $uniqueFocus);

        if (!$blockDef) {
            return []; // Unknown block
        }

        // Get information pages for SEO blocks (they can reference these in text)
        $informationPages = $this->getInformationPagesForBlock($blockDef['type']);

        // Get previous content from same page to avoid repetition
        $pageContext = $this->getPageContext($blockKey, $previousContext);

        // Regenerate loop with validation
        $maxAttempts = 2;
        $lastErrors = [];

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            // Generate content
            $blockContent = $this->callOpenAI($blockKey, $blockDef, $niche, $siteName, $currentMonth, $currentYear, $uniqueFocus, $informationPages, $pageContext, $lastErrors);

            if (empty($blockContent)) {
                \Log::warning("Empty response for {$blockKey} (attempt {$attempt})");
                continue;
            }

            // Validate content
            $validator = new ContentBlockValidator();
            $result = $validator->validate($blockContent, $blockDef);

            // Log warnings (but accept content)
            if (!empty($result->warnings)) {
                \Log::warning("Content warnings for {$blockKey}", [
                    'warnings' => $result->warnings,
                    'attempt' => $attempt
                ]);
            }

            // If valid, return content
            if ($result->isValid) {
                if ($attempt > 1) {
                    \Log::info("Content validated successfully on attempt {$attempt} for {$blockKey}");
                }
                return $blockContent;
            }

            // Invalid - log errors
            \Log::warning("Validation failed for {$blockKey} (attempt {$attempt}/{$maxAttempts})", [
                'errors' => $result->errors
            ]);

            $lastErrors = $result->errors;

            // If not last attempt, wait before retry
            if ($attempt < $maxAttempts) {
                usleep(500000); // 500ms delay
            }
        }

        // All attempts failed - use fallback
        \Log::error("All {$maxAttempts} attempts failed for {$blockKey}, using fallback", [
            'last_errors' => $lastErrors
        ]);

        return $this->getFallbackContent($blockKey, $blockDef);
    }

    /**
     * Call OpenAI API with optional error feedback for regeneration
     */
    private function callOpenAI(string $blockKey, array $blockDef, string $niche, string $siteName, string $currentMonth, string $currentYear, ?string $uniqueFocus, array $informationPages, array $pageContext, array $previousErrors = []): array
    {
        // Build JSON Schema for this block (guaranteed structure)
        $jsonSchema = $this->buildJsonSchema($blockDef);

        // Build focused prompt for this specific block
        $prompt = $this->buildBlockPrompt($blockDef, $niche, $siteName, $currentMonth, $currentYear, $uniqueFocus, $informationPages, $pageContext);

        // Add error feedback if this is a regeneration
        if (!empty($previousErrors)) {
            $prompt .= $this->buildFixPrompt($previousErrors);
        }

        // Call OpenAI with Structured Outputs
        $messages = [
            ['role' => 'system', 'content' => 'Je bent een expert content strategist voor Nederlandse affiliate websites.'],
            ['role' => 'user', 'content' => $prompt],
        ];

        $responseFormat = [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'content_block_' . str_replace('.', '_', $blockKey),
                'schema' => $jsonSchema,
                'strict' => true  // ENFORCE exact structure
            ]
        ];

        try {
            $response = $this->openAI->chat($messages, 'gpt-4o', 0.8, 2000, $responseFormat);

            // Response is GUARANTEED valid JSON - no cleaning needed!
            $content = trim($response['content'] ?? '{}');
            $decoded = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                \Log::error('JSON decode failed despite strict schema', [
                    'block' => $blockKey,
                    'content' => $content,
                    'error' => json_last_error_msg()
                ]);
                return [];
            }

            return $decoded;
        } catch (\Exception $e) {
            \Log::error("OpenAI API error for {$blockKey}", [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Build prompt addition with error feedback
     */
    private function buildFixPrompt(array $errors): string
    {
        $errorList = implode("\n", array_map(fn($e) => "  ✗ {$e}", $errors));

        return "\n\n" . <<<FIX
🚨 VORIGE POGING AFGEKEURD - FIX DEZE PROBLEMEN:
{$errorList}

Genereer opnieuw met deze correcties. Let extra goed op de gemarkeerde problemen.
FIX;
    }

    /**
     * Get fallback content when all generation attempts fail
     */
    private function getFallbackContent(string $blockKey, array $blockDef): array
    {
        // Simple fallback templates per block type
        $fallback = [];

        foreach ($blockDef['units'] as $unit) {
            // Generate basic fallback based on unit type
            if (str_contains($unit, 'title')) {
                $fallback[$unit] = "Meer informatie binnenkort beschikbaar";
            } elseif (str_contains($unit, 'question')) {
                $fallback[$unit] = "Vraag wordt binnenkort beantwoord";
            } elseif (str_contains($unit, 'answer') || str_contains($unit, 'text') || str_contains($unit, 'intro')) {
                $fallback[$unit] = "Deze informatie is momenteel in ontwikkeling. Kom binnenkort terug voor meer details.";
            } else {
                $fallback[$unit] = "Content binnenkort beschikbaar";
            }
        }

        return $fallback;
    }

    /**
     * Build JSON Schema for a content block
     * This ENFORCES exact structure - no markdown, no extra fields, 100% predictable
     */
    private function buildJsonSchema(array $blockDef): array
    {
        $properties = [];
        $required = [];

        foreach ($blockDef['units'] as $unit) {
            $properties[$unit] = [
                'type' => 'string',
                'description' => "Content for {$unit} unit"
            ];
            $required[] = $unit;
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
            'additionalProperties' => false  // NO extra fields allowed
        ];
    }

    /**
     * Get information pages that can be referenced in content blocks
     */
    private function getInformationPagesForBlock(string $blockType): array
    {
        // Only fetch for SEO blocks (where we want to reference info pages)
        if ($blockType !== 'SEO') {
            return [];
        }

        // Get active information pages with menu_title and slug
        $pages = \App\Models\InformationPage::where('is_active', true)
            ->orderBy('order')
            ->select('menu_title', 'slug')
            ->get()
            ->map(function($page) {
                return [
                    'title' => $page->menu_title,
                    'url' => '/informatie/' . $page->slug
                ];
            })
            ->toArray();

        return $pages;
    }

    /**
     * Get previous content from same page to avoid repetition
     */
    private function getPageContext(string $blockKey, array $previousContext): array
    {
        // Extract page name (e.g., 'homepage' from 'homepage.seo1')
        $page = explode('.', $blockKey)[0];

        // Return previous blocks from same page
        return $previousContext[$page] ?? [];
    }

    /**
     * Get block definition (specifications for a specific block)
     */
    private function getBlockDefinition(string $blockKey, string $niche, string $siteName, string $currentMonth, string $currentYear, ?string $uniqueFocus): ?array
    {
        // Define all 20 blocks with their specs
        $definitions = [
            'homepage.hero' => [
                'type' => 'HERO',
                'units' => ['title', 'subtitle'],
                'role' => 'Eerste indruk + immediate waarde communiceren',
                'page_role' => 'awareness',
                'cta_rule' => 'forbidden',
                'cta_target' => 'none',
                'max_cijfers' => 0,
                'focus_tag' => 'actueel-vergelijk-bespaar',
                'forbidden' => [
                    'Geen "welkom bij {siteName}" → redundant',
                    'Geen "grootste collectie" → onverifieerbaar',
                    'Geen promotie van reviews/blogs/Top 5',
                ],
                'must_treat' => [
                    'TITLE: De beste {niche} van [HUIDIGE MAAND] [HUIDIG JAAR] + belofte (vergelijken/besparen)',
                    'SUBTITLE: Waarom deze site? (unieke waarde in 1 zin, 100-150 tekens)',
                ],
                'lengths' => [
                    'title' => '60-90 tekens (inclusief maand/jaar)',
                    'subtitle' => '100-150 tekens',
                ],
                'style' => 'Krachtig, actueel, waarde-gedreven. GEBRUIK ACTUELE MAAND/JAAR (december 2025). Belofte in title.',
            ],

            'homepage.seo1' => [
                'type' => 'SEO',
                'units' => ['title', 'intro', 'section1_title', 'section1_text', 'section2_title', 'section2_text'],
                'role' => 'Waarom {niche}? (voordelen, waarom mensen kopen)',
                'page_role' => 'awareness',
                'cta_rule' => 'optional',
                'cta_target' => '/produkte',
                'max_cijfers' => 0,
                'focus_tag' => 'voordelen-waarom',
                'forbidden' => [
                    'GEEN keuzehulp ("hoe kies je", budget, capaciteit) - dat is SEO2',
                    'GEEN cijfers',
                ],
                'must_treat' => [
                    'TITLE: Waarom {niche}? (30-50 tekens)',
                    'INTRO: Intro (30-40w)',
                    'SECTION 1: Voordeel 1 (H3 titel + 30-40w tekst)',
                    'SECTION 2: Voordeel 2 (H3 titel + 30-40w tekst)',
                ],
                'lengths' => [
                    'title' => '30-50 tekens',
                    'intro' => '30-40 woorden',
                    'section1_title' => '20-40 tekens',
                    'section1_text' => '30-40 woorden',
                    'section2_title' => '20-40 tekens',
                    'section2_text' => '30-40 woorden',
                ],
                'style' => 'Enthousiast maar eerlijk. 2 voordelen met H3 koppen. Positief maar ook beperkingen noemen. Conversie-gericht.',
            ],

            'homepage.info' => [
                'type' => 'INFO',
                'units' => ['title', 'text'],
                'role' => 'Wat doet deze site? (vergelijken specs/ervaringen)',
                'page_role' => 'awareness',
                'cta_rule' => 'optional',
                'cta_target' => '/produkte',
                'max_cijfers' => 0,
                'focus_tag' => 'site-waarde-vergelijken',
                'forbidden' => [
                    'GEEN producteigenschappen/voordelen - dat is SEO1',
                    'GEEN test-claims: "we testen", "grondig getest"',
                    'GEEN cijfers',
                    'GEEN keuzehulp - dat is SEO2',
                ],
                'must_treat' => [
                    'TITLE: Over deze site (20-40 tekens)',
                    'TEXT: We vergelijken specs en gebruikerservaringen. Zo vind je makkelijker wat past. Focus op SITE-FUNCTIE (vergelijken/filteren), NIET op productvoordelen of gebruik-scenario\'s. (80-100w)',
                ],
                'lengths' => [
                    'title' => '20-40 tekens',
                    'text' => '80-100 woorden',
                ],
                'style' => 'Transparant. Focus op WAT we vergelijken, niet WAT het product doet.',
            ],

            'homepage.seo2' => [
                'type' => 'SEO',
                'units' => ['title', 'intro', 'section1_title', 'section1_text', 'section2_title', 'section2_text', 'section3_title', 'section3_text'],
                'role' => 'Hoe kies je? (keuzehulp: gebruik, budget, capaciteit)',
                'page_role' => 'keuze',
                'cta_rule' => 'optional',
                'cta_target' => '/produkte',
                'max_cijfers' => 0,
                'focus_tag' => 'keuzehulp-beslissen',
                'forbidden' => [
                    'GEEN voordelen (dat was SEO1)',
                    'GEEN cijfers',
                    'GEEN merkadvies',
                ],
                'must_treat' => [
                    'TITLE: Welke past bij jou? (30-50 tekens)',
                    'INTRO: Intro (20-30w)',
                    'SECTION 1: Gebruik/situatie (H3 titel + 25-35w tekst)',
                    'SECTION 2: Budget/prijs (H3 titel + 25-35w tekst)',
                    'SECTION 3: Capaciteit/grootte (H3 titel + 25-35w tekst)',
                ],
                'lengths' => [
                    'title' => '30-50 tekens',
                    'intro' => '20-30 woorden',
                    'section1_title' => '20-40 tekens',
                    'section1_text' => '25-35 woorden',
                    'section2_title' => '20-40 tekens',
                    'section2_text' => '25-35 woorden',
                    'section3_title' => '20-40 tekens',
                    'section3_text' => '25-35 woorden',
                ],
                'style' => 'Praktisch. 3 subsecties met H3 koppen. Focus op KIEZEN. "Als je X, dan Y".',
            ],

            'homepage.faq_1' => [
                'type' => 'FAQ',
                'units' => ['question', 'answer'],
                'role' => 'FAQ 1: Beginnersvraag over {niche}',
                'page_role' => 'awareness',
                'cta_rule' => 'forbidden',
                'cta_target' => 'none',
                'max_cijfers' => 0,
                'focus_tag' => 'geschiktheid-beginners',
                'forbidden' => [
                    'GEEN onderhoud/techniek (dat is FAQ2)',
                    'GEEN gebruik/toepassingen (dat is FAQ3)',
                    'GEEN cijfers',
                    '❌ VERBODEN: "ideaal", "handig", "perfect", "na een lange werkdag", "voor veel mensen"',
                    '✓ GEBRUIK: concrete situaties (appartement, beperkte ruimte, gezin met kinderen)',
                ],
                'must_treat' => [
                    'QUESTION: Voor wie/wanneer geschikt? Beginnersvraag. (60-100 tekens)',
                    'ANSWER: Concreet antwoord over geschiktheid/niveau. ZONDER vage marketing taal. (50-70w)',
                ],
                'lengths' => ['question' => '60-100 tekens', 'answer' => '50-70 woorden'],
                'style' => 'Toegankelijk, beginnervriendelijk. CONCREET en SPECIFIEK, geen vage marketing woorden.',
            ],

            'homepage.faq_2' => [
                'type' => 'FAQ',
                'units' => ['question', 'answer'],
                'role' => 'FAQ 2: Onderhoud/technische vraag over {niche}',
                'page_role' => 'awareness',
                'cta_rule' => 'forbidden',
                'cta_target' => 'none',
                'max_cijfers' => 0,
                'focus_tag' => 'onderhoud-techniek',
                'forbidden' => [
                    'GEEN geschiktheid (dat is FAQ1)',
                    'GEEN gebruik/toepassingen (dat is FAQ3)',
                    'GEEN cijfers',
                    '❌ VERBODEN: "ideaal", "handig", "perfect", "na een lange werkdag", "voor veel mensen"',
                    '✓ GEBRUIK: concrete acties en frequenties (wekelijks, per maand, na 100 gebruiksbeurten)',
                ],
                'must_treat' => [
                    'QUESTION: Onderhoud of technische vraag. (60-100 tekens)',
                    'ANSWER: Praktische tips voor onderhoud/techniek. ZONDER vage marketing taal. (50-70w)',
                ],
                'lengths' => ['question' => '60-100 tekens', 'answer' => '50-70 woorden'],
                'style' => 'Praktisch, technisch. Concrete onderhoudstips. GEEN vage marketing woorden.',
            ],

            'homepage.faq_3' => [
                'type' => 'FAQ',
                'units' => ['question', 'answer'],
                'role' => 'FAQ 3: Gebruik/toepassing van {niche}',
                'page_role' => 'awareness',
                'cta_rule' => 'forbidden',
                'cta_target' => 'none',
                'max_cijfers' => 0,
                'focus_tag' => 'gebruik-toepassingen',
                'forbidden' => [
                    'GEEN geschiktheid (dat is FAQ1)',
                    'GEEN onderhoud/techniek (dat is FAQ2)',
                    'GEEN cijfers',
                    '❌ VERBODEN: "ideaal", "handig", "perfect", "na een lange werkdag", "voor veel mensen"',
                    '✓ GEBRUIK: concrete gerechten/situaties (friet + kip, groenten + vis, ontbijt + lunch)',
                ],
                'must_treat' => [
                    'QUESTION: Waarvoor gebruik je het? Toepassingen. (60-100 tekens)',
                    'ANSWER: Concrete voorbeelden gebruik/toepassingen. ZONDER vage marketing taal. (50-70w)',
                ],
                'lengths' => ['question' => '60-100 tekens', 'answer' => '50-70 woorden'],
                'style' => 'Inspirerend, praktisch. Concrete gebruiksvoorbeelden. GEEN vage marketing woorden.',
            ],

            // PRODUCTEN BLOCKS
            'producten_index_hero_titel' => [
                'type' => 'HERO',
                'units' => ['title'],
                'role' => 'Productoverzicht titel (decision hub)',
                'page_role' => 'keuze',
                'cta_rule' => 'forbidden',
                'cta_target' => 'none',
                'max_cijfers' => 0,
                'focus_tag' => 'vergelijken-kiezen',
                'forbidden' => [
                    'Geen "welkom"',
                    'Geen "grootste assortiment"',
                    'GEEN absolute claims: "alle modellen" → "ruim assortiment modellen"',
                ],
                'must_treat' => ['TITLE: Alle {niche} vergelijken (50-80 tekens)'],
                'lengths' => ['title' => '50-80 tekens'],
                'style' => 'Actiegericht, beslissingsfocus. "Vergelijk en kies" energie.',
            ],

            'producten_index_info_blok_1' => [
                'type' => 'INFO',
                'units' => ['title', 'text'],
                'role' => 'Waarom vergelijken op deze pagina?',
                'page_role' => 'keuze',
                'cta_rule' => 'forbidden',
                'cta_target' => 'none',
                'max_cijfers' => 0,
                'focus_tag' => 'filteropties-specificaties',
                'forbidden' => [
                    'Geen CTA naar andere pagina\'s',
                    'TRANSPARANTIE: "we tonen specs uit productdatabases" niet "we testen alle modellen"',
                ],
                'must_treat' => ['TITLE: Waarom hier vergelijken? (40-60 tekens)', 'TEXT: Filterfuncties uitleggen (70-90 woorden)'],
                'lengths' => ['title' => '40-60 tekens', 'text' => '70-90 woorden (MAX 90!)'],
                'style' => 'Praktisch, uitleg-gedreven. Leg uit HOE de filters werken.',
            ],

            'producten_index_info_blok_2' => [
                'type' => 'INFO',
                'units' => ['title', 'text'],
                'role' => 'Waarop letten bij vergelijken?',
                'page_role' => 'keuze',
                'cta_rule' => 'forbidden',
                'cta_target' => 'none',
                'max_cijfers' => 1,
                'focus_tag' => 'specificaties-keuzetips',
                'forbidden' => [
                    'Geen herhaling van info blok 1',
                    'NUANCE bij keuzeadvies: "vaak belangrijk" niet "altijd belangrijk"',
                    'CONTEXT bij specs: "afhankelijk van gebruik" bij variabele criteria',
                ],
                'must_treat' => ['TITLE: Waarop letten? (40-60 tekens)', 'TEXT: Belangrijkste vergelijkingscriteria (70-90 woorden)'],
                'lengths' => ['title' => '40-60 tekens', 'text' => '70-90 woorden (MAX 90!)'],
                'style' => 'Educatief, beslissingsgericht. Concrete keuze-criteria.',
            ],

            'producten_top_hero_titel' => [
                'type' => 'HERO',
                'units' => ['title'],
                'role' => 'Top 5 pagina titel',
                'page_role' => 'keuze',
                'cta_rule' => 'forbidden',
                'cta_target' => 'none',
                'max_cijfers' => 0,
                'focus_tag' => 'beste-keuzes',
                'forbidden' => ['Geen "onze favorieten"'],
                'must_treat' => ['TITLE: Top 5 beste {niche} van {currentMonth} (60-90 tekens)'],
                'lengths' => ['title' => '60-90 tekens'],
                'style' => 'Krachtig, selectie-gedreven. "Beste van..." energie.',
            ],

            'producten_top_seo_blok' => [
                'type' => 'SEO',
                'units' => ['title', 'intro', 'section1_title', 'section1_text', 'section2_title', 'section2_text', 'section3_title', 'section3_text'],
                'role' => 'Waarom deze Top 5?',
                'page_role' => 'keuze',
                'cta_rule' => 'forbidden',
                'cta_target' => 'none',
                'max_cijfers' => 1,
                'focus_tag' => 'selectiecriteria-waarde',
                'forbidden' => [
                    'Geen productadvies',
                    'Geen merkpromotie',
                    'GEEN test-claims: "we hebben getest" → "we hebben vergeleken op basis van specs en reviews"',
                    'TRANSPARANTIE over selectie: "geselecteerd op basis van ratings, specs en prijzen"',
                ],
                'must_treat' => [
                    'TITLE: Waarom deze selectie? (40-60 tekens)',
                    'INTRO: Selectiecriteria uitleg (40-60 woorden)',
                    'SECTION 1: Criteria 1 (titel + 50-70w)',
                    'SECTION 2: Criteria 2 (titel + 50-70w)',
                    'SECTION 3: Criteria 3 (titel + 50-70w)',
                ],
                'lengths' => [
                    'title' => '40-60 tekens',
                    'intro' => '40-60 woorden (MAX 60!)',
                    'section1_title' => '40-60 tekens',
                    'section1_text' => '50-70 woorden (MAX 70!)',
                    'section2_title' => '40-60 tekens',
                    'section2_text' => '50-70 woorden (MAX 70!)',
                    'section3_title' => '40-60 tekens',
                    'section3_text' => '50-70 woorden (MAX 70!)',
                ],
                'style' => 'Transparant, criteria-gedreven. Leg uit HOE de selectie tot stand komt.',
            ],

            // MERKEN BLOCKS
            'merken_index_hero_titel' => [
                'type' => 'HERO',
                'units' => ['title', 'subtitle'],
                'role' => 'Merken overzicht hero',
                'page_role' => 'verdieping',
                'cta_rule' => 'optional',
                'cta_target' => '/produkte',
                'max_cijfers' => 0,
                'focus_tag' => 'merkenvergelijking',
                'forbidden' => [
                    'Geen merkpromotie',
                    'OBJECTIVITEIT: geen "beste merk" - elk merk heeft voor/nadelen',
                ],
                'must_treat' => ['TITLE: Alle {niche} merken vergelijken (50-80 tekens)', 'SUBTITLE: Waarom merken vergelijken? (100-150 tekens)'],
                'lengths' => ['title' => '50-80 tekens', 'subtitle' => '100-150 tekens'],
                'style' => 'Objectief, vergelijkend. Geen voorkeur voor specifieke merken.',
            ],

            'merken_index_info_blok' => [
                'type' => 'SEO',
                'units' => ['title', 'intro', 'section1_title', 'section1_text', 'section2_title', 'section2_text', 'section3_title', 'section3_text'],
                'role' => 'Waarom merk belangrijk?',
                'page_role' => 'verdieping',
                'cta_rule' => 'required',
                'cta_target' => '/produkte',
                'max_cijfers' => 1,
                'focus_tag' => 'merkverschillen-kwaliteit',
                'forbidden' => [
                    'Geen merkpromotie',
                    'Geen "beste merk"',
                    'NUANCE bij merkenvergelijking: "vaak betere garantie" niet "beste garantie"',
                    'EERLIJKHEID: elk merk heeft sterke en zwakke punten noemen',
                ],
                'must_treat' => [
                    'TITLE: Waarom merk ertoe doet (40-60 tekens)',
                    'INTRO: Merkwaarde uitleg (40-60 woorden)',
                    'SECTION 1: Kwaliteitsverschillen (titel + 50-70w)',
                    'SECTION 2: Prijs vs kwaliteit (titel + 50-70w)',
                    'SECTION 3: Service/garantie (titel + 50-70w)',
                ],
                'lengths' => [
                    'title' => '40-60 tekens',
                    'intro' => '40-60 woorden (MAX 60!)',
                    'section1_title' => '40-60 tekens',
                    'section1_text' => '50-70 woorden (MAX 70!)',
                    'section2_title' => '40-60 tekens',
                    'section2_text' => '50-70 woorden (MAX 70!)',
                    'section3_title' => '40-60 tekens',
                    'section3_text' => '50-70 woorden (MAX 70!)',
                ],
                'style' => 'Objectief, vergelijkend. Focus op wat merken verschillend maakt.',
            ],

            // REVIEWS BLOCKS
            'reviews.hero' => [
                'type' => 'HERO',
                'units' => ['title', 'subtitle'],
                'role' => 'Reviews overzicht hero',
                'page_role' => 'verdieping',
                'cta_rule' => 'forbidden',
                'cta_target' => 'none',
                'max_cijfers' => 0,
                'focus_tag' => 'diepgaand-eerlijk',
                'forbidden' => [
                    'Geen "wij testen"',
                    'TRANSPARANTIE: "we analyseren specs en gebruikerservaringen" niet "we testen grondig"',
                ],
                'must_treat' => ['TITLE: Reviews van {niche} (50-80 tekens)', 'SUBTITLE: Wat te verwachten (100-150 tekens)'],
                'lengths' => ['title' => '50-80 tekens', 'subtitle' => '100-150 tekens'],
                'style' => 'Betrouwbaar, analyse-gedreven. Focus op eerlijkheid.',
            ],

            'reviews_index_intro' => [
                'type' => 'INFO',
                'units' => ['title', 'text'],
                'role' => 'Waarom reviews lezen?',
                'page_role' => 'verdieping',
                'cta_rule' => 'optional',
                'cta_target' => '/produkte',
                'max_cijfers' => 0,
                'focus_tag' => 'waarde-reviews',
                'forbidden' => [
                    'Geen "wij testen"',
                    'EERLIJKHEID: "we analyseren op basis van specs, reviews en ervaringen" niet "eigen testlab"',
                ],
                'must_treat' => ['TITLE: Waarom reviews? (40-60 tekens)', 'TEXT: Waarde van reviews uitleggen (70-90 woorden)'],
                'lengths' => ['title' => '40-60 tekens', 'text' => '70-90 woorden (MAX 90!)'],
                'style' => 'Informatief, waarde-gedreven. Leg uit WAT je aan reviews hebt.',
            ],

            'reviews_index_seo_blok' => [
                'type' => 'SEO',
                'units' => ['title', 'intro', 'section1_title', 'section1_text', 'section2_title', 'section2_text', 'section3_title', 'section3_text'],
                'role' => 'Wat kijken we naar in reviews?',
                'page_role' => 'verdieping',
                'cta_rule' => 'optional',
                'cta_target' => '/produkte',
                'max_cijfers' => 1,
                'focus_tag' => 'reviewcriteria-objectiviteit',
                'forbidden' => [
                    'Geen "wij testen"',
                    'Geen productpromotie',
                    'TRANSPARANTIE: "we beoordelen op basis van specs, user reviews en fabrikantinfo"',
                    'EERLIJKHEID: geen claims over fysieke tests die we niet hebben gedaan',
                ],
                'must_treat' => [
                    'TITLE: Wat beoordelen we? (40-60 tekens)',
                    'INTRO: Review-aanpak uitleg (40-60 woorden)',
                    'SECTION 1: Criterium 1 (titel + 50-70w)',
                    'SECTION 2: Criterium 2 (titel + 50-70w)',
                    'SECTION 3: Criterium 3 (titel + 50-70w)',
                ],
                'lengths' => [
                    'title' => '40-60 tekens',
                    'intro' => '40-60 woorden (MAX 60!)',
                    'section1_title' => '40-60 tekens',
                    'section1_text' => '50-70 woorden (MAX 70!)',
                    'section2_title' => '40-60 tekens',
                    'section2_text' => '50-70 woorden (MAX 70!)',
                    'section3_title' => '40-60 tekens',
                    'section3_text' => '50-70 woorden (MAX 70!)',
                ],
                'style' => 'Transparant, criteria-gedreven. Leg uit HOE we beoordelen.',
            ],

            // BLOGS BLOCKS
            'blogs.hero' => [
                'type' => 'HERO',
                'units' => ['title', 'subtitle'],
                'role' => 'Blogs overzicht hero',
                'page_role' => 'verdieping',
                'cta_rule' => 'forbidden',
                'cta_target' => 'none',
                'max_cijfers' => 0,
                'focus_tag' => 'informatief-educatief',
                'forbidden' => ['Geen "lees onze blogs"'],
                'must_treat' => ['TITLE: Alles over {niche} (50-80 tekens)', 'SUBTITLE: Wat te leren (100-150 tekens)'],
                'lengths' => ['title' => '50-80 tekens', 'subtitle' => '100-150 tekens'],
                'style' => 'Informatief, kennisgedreven. Focus op leerwaarde.',
            ],

            'blogs.intro' => [
                'type' => 'INFO',
                'units' => ['title', 'text'],
                'role' => 'Waarom blogs lezen?',
                'page_role' => 'verdieping',
                'cta_rule' => 'forbidden',
                'cta_target' => 'none',
                'max_cijfers' => 0,
                'focus_tag' => 'kennis-tips',
                'forbidden' => [
                    'Geen CTA naar producten in tekst',
                    'NUANCE bij educatieve claims: "deze tips kunnen helpen" niet "dit werkt altijd"',
                ],
                'must_treat' => ['TITLE: Waarom deze blogs? (40-60 tekens)', 'TEXT: Waarde van artikelen (70-90 woorden)'],
                'lengths' => ['title' => '40-60 tekens', 'text' => '70-90 woorden (MAX 90!)'],
                'style' => 'Educatief, waarde-gedreven. Leg uit WAT je leert.',
            ],

            'blogs.seo' => [
                'type' => 'SEO',
                'units' => ['title', 'intro', 'section1_title', 'section1_text', 'section2_title', 'section2_text', 'section3_title', 'section3_text'],
                'role' => 'Wat behandelen onze blogs?',
                'page_role' => 'verdieping',
                'cta_rule' => 'forbidden',
                'cta_target' => 'none',
                'max_cijfers' => 1,
                'focus_tag' => 'onderwerpen-diepgang',
                'forbidden' => [
                    'Geen productpromotie',
                    'EERLIJKHEID: geen absolute claims over resultaten - "kan je helpen" niet "zorgt ervoor dat"',
                    'BRON bij feiten: "volgens onderzoek", "experts adviseren" bij specifieke claims',
                ],
                'must_treat' => [
                    'TITLE: Wat leer je hier? (40-60 tekens)',
                    'INTRO: Blog-onderwerpen overzicht (40-60 woorden)',
                    'SECTION 1: Thema 1 (titel + 50-70w)',
                    'SECTION 2: Thema 2 (titel + 50-70w)',
                    'SECTION 3: Thema 3 (titel + 50-70w)',
                ],
                'lengths' => [
                    'title' => '40-60 tekens',
                    'intro' => '40-60 woorden (MAX 60!)',
                    'section1_title' => '40-60 tekens',
                    'section1_text' => '50-70 woorden (MAX 70!)',
                    'section2_title' => '40-60 tekens',
                    'section2_text' => '50-70 woorden (MAX 70!)',
                    'section3_title' => '40-60 tekens',
                    'section3_text' => '50-70 woorden (MAX 70!)',
                ],
                'style' => 'Educatief, thema-gedreven. Focus op leerwaarde en praktische tips.',
            ],
        ];

        $def = $definitions[$blockKey] ?? null;

        if (!$def) {
            return null;
        }

        // Replace placeholders in role, must_treat, etc
        array_walk_recursive($def, function(&$value) use ($niche, $siteName, $currentMonth, $currentYear) {
            if (is_string($value)) {
                $value = str_replace(
                    ['{niche}', '{siteName}', '{currentMonth}', '{currentYear}'],
                    [$niche, $siteName, $currentMonth, $currentYear],
                    $value
                );
            }
        });

        return $def;
    }

    /**
     * Build focused prompt for a single block
     */
    private function buildBlockPrompt(array $blockDef, string $niche, string $siteName, string $currentMonth, string $currentYear, ?string $uniqueFocus, array $informationPages = [], array $pageContext = []): string
    {
        $uniqueFocusSection = '';
        if ($uniqueFocus) {
            $uniqueFocusSection = "\nUNIEKE FOCUS: \"{$uniqueFocus}\" (gebruik SPAARZAAM, alleen waar het waarde toevoegt)\n";
        }

        $forbiddenList = implode("\n", array_map(fn($f) => "✗ " . $f, $blockDef['forbidden']));
        $mustTreatList = implode("\n", array_map(fn($m) => (is_numeric(array_search($m, $blockDef['must_treat'])) ? (array_search($m, $blockDef['must_treat']) + 1) . ". " : "") . $m, $blockDef['must_treat']));

        $lengthsList = '';
        foreach ($blockDef['lengths'] as $key => $value) {
            $lengthsList .= "- {$key}: {$value}\n";
        }

        $exampleSection = '';
        if (isset($blockDef['example'])) {
            $exampleSection = "\n\nVOORBEELDEN:\n";
            foreach ($blockDef['example'] as $key => $value) {
                $exampleSection .= "{$key}: \"{$value}\"\n";
            }
        }

        // Page context section (previously generated blocks on same page)
        $contextSection = '';
        if (!empty($pageContext)) {
            // Extract used links and key phrases
            $usedLinks = [];
            $keyPhrases = [];

            foreach ($pageContext as $prev) {
                // Extract links
                preg_match_all('/<a href="([^"]+)">([^<]+)<\/a>/', $prev['content'], $matches);
                foreach ($matches[1] as $i => $url) {
                    $usedLinks[] = $url . ' (' . $matches[2][$i] . ')';
                }

                // Extract percentages
                preg_match_all('/\d+%[^,\.]*/', $prev['content'], $percentMatches);
                foreach ($percentMatches[0] as $pct) {
                    $keyPhrases[] = $pct;
                }
            }

            $contextSection = "\n\n🚫 VERMIJD HERHALING - AL GEBRUIKT OP DEZE PAGINA:\n";

            if (!empty($usedLinks)) {
                $contextSection .= "\nGEBRUIKTE LINKS (kies ANDERE informatiepagina's):\n";
                foreach (array_unique($usedLinks) as $link) {
                    $contextSection .= "  ✗ {$link}\n";
                }
            }

            if (!empty($keyPhrases)) {
                $contextSection .= "\nGEBRUIKTE CIJFERS/PERCENTAGES (gebruik ANDERE cijfers):\n";
                foreach (array_unique($keyPhrases) as $phrase) {
                    $contextSection .= "  ✗ {$phrase}\n";
                }
            }

            $contextSection .= "\n⚠️ CRITICAL: Als een link/cijfer hierboven staat, gebruik het NIET opnieuw!\n";
            $contextSection .= "✓ Kies NIEUWE informatiepagina links uit de lijst hieronder\n";
            $contextSection .= "✓ Gebruik ANDERE percentages/cijfers voor variatie\n";
        }

        // Information pages section (for SEO blocks)
        $infoPagesSection = '';
        if (!empty($informationPages)) {
            $infoPagesSection = "\n\nINFORMATIE PAGINA'S (gebruik strategisch in lopende tekst):\n";
            foreach ($informationPages as $page) {
                $infoPagesSection .= "- \"{$page['title']}\" → {$page['url']}\n";
            }
            $infoPagesSection .= "\nLINK STRATEGIEËN:\n";
            $infoPagesSection .= "1. MAX 1-2 links per block (voorkom link overload)\n";
            $infoPagesSection .= "2. Link ALLEEN als het echt waarde toevoegt voor de lezer\n";
            $infoPagesSection .= "3. Integreer natuurlijk in lopende tekst (niet geforceerd aan het einde)\n";
            $infoPagesSection .= "4. Gebruik ALTIJD volledige titel uit de lijst hierboven\n";
            $infoPagesSection .= "5. Check of link AL gebruikt is in vorige blocks (zie VERMIJD HERHALING)\n";
            $infoPagesSection .= "6. Formaat: \"Lees meer over <a href=\"URL\">EXACTE TITEL</a>\" OF \"Bekijk <a href=\"URL\">EXACTE TITEL</a>\"\n";
            $infoPagesSection .= "\nVOORBEELD GOED: \"Voor een gezin van 4 personen is 5 liter vaak ideaal. Lees meer over <a href=\"/informatie/juiste-maat-airfryer\">welke maat airfryer je nodig hebt</a>.\"\n";
            $infoPagesSection .= "VOORBEELD FOUT: Te veel links, geforceerde links, verkeerde titels, HERHAALDE LINKS\n";
        }

        $unitsList = implode('", "', $blockDef['units']);

        // Build CTA instruction based on rule
        $ctaInstruction = '';
        if ($blockDef['cta_rule'] === 'required') {
            $ctaInstruction = "CTA VERPLICHT: Eindig met exact 1 zin die richting geeft naar {$blockDef['cta_target']}
   → Voorbeeld: \"Klaar om te vergelijken? <a href=\\\"{$blockDef['cta_target']}\\\">Sehen Sie alle modellen</a>.\"
   → GEBRUIK ALTIJD een <a href> link, NOOIT platte tekst zoals 'op /produkte'
   → GEEN dubbele CTA's, geen \"of bekijk blogs/reviews\"";
        } elseif ($blockDef['cta_rule'] === 'optional') {
            $ctaInstruction = "CTA OPTIONEEL: Mag eindigen met subtiele verwijzing naar {$blockDef['cta_target']} (1 zin max)
   → Gebruik <a href=\"{$blockDef['cta_target']}\">link</a> formaat, geen platte tekst
   → Alleen als het natuurlijk past in de flow
   → GEEN geforceerde \"bekijk ook\" links";
        } else {
            $ctaInstruction = "CTA VERBODEN: Geen call-to-actions, geen \"bekijk ook\", geen verwijzingen naar andere pagina's";
        }

        return <<<PROMPT
Je schrijft zoals een vriend die iets uitlegt, NIET zoals een brochure.

SCHRIJFSTIJL (VERPLICHT):
1. Leg mechanisme uit: "gebruiken hete lucht om eten knapperig te maken"
   NIET voordelen verkopen: "zijn handig voor drukke avonden"

2. "Voor veel mensen is dat handig na een lange werkdag"
   NIET "wanneer je snel een maaltijd op tafel wilt"

3. Concrete voorbeelden: "Patat wordt knapperig, maar minder goudgeel"
   NIET vage claims: "perfect voor je favoriete gerechten"

4. MAX {$blockDef['max_cijfers']} cijfers in dit block. Liever mechanisme uitleggen dan cijfers.

═══════════════════════════════════════════════════════════════════════
📋 BLOCK INFO:
Page role: {$blockDef['page_role']} (awareness/keuze/verdieping)
Block functie: {$blockDef['role']}
Max cijfers: {$blockDef['max_cijfers']} numerieke claims in dit HELE block
{$ctaInstruction}
E-E-A-T: "We vergelijken specs en ervaringen" NOOIT "We testen grondig"
Als je cijfers gebruikt: ALTIJD met bron ("Volgens fabrikant...", "Gebruikers melden...")

═══════════════════════════════════════════════════════════════════════

VERBODEN:
{$forbiddenList}

MOET BEHANDELEN:
{$mustTreatList}

LENGTES:
{$lengthsList}

GOUDEN VOORBEELD - SCHRIJF EXACT ZO (dit is perfect):
"Airfryers zijn populair omdat ze het makkelijker maken om snel te koken. In plaats van een pan olie gebruiken deze apparaten hete lucht om eten knapperig te maken. Voor veel mensen is dat handig na een lange werkdag. Het resultaat is wel anders dan frituren. Patat wordt bijvoorbeeld knapperig, maar minder goudgeel."

WAAROM DIT PERFECT IS:
- Geen cijfers (geen %, geen "X keer sneller")
- Legt mechanisme uit ("hete lucht om eten knapperig te maken")
- "Voor veel mensen" (niet "wanneer je")
- Concrete voorbeeld ("Patat wordt knapperig, maar minder goudgeel")

🚫 VERBODEN IN JOUW OUTPUT:
- GEEN percentages of cijfers ("70%", "30% sneller" = VERBODEN)
- GEEN "drukke dag/avond" (gebruik "lange werkdag")
- GEEN "bijdraagt aan", "biedt", "voordelen" (leg mechanisme uit)

{$exampleSection}{$contextSection}{$infoPagesSection}
OUTPUT FORMAT: Plain text JSON met keys: ["{$unitsList}"]
Geen HTML, geen Markdown. Alleen plain text per unit.

Variabelen: niche="{$niche}", maand="{$currentMonth}", jaar="{$currentYear}"

Genereer NU de content in JSON format:
PROMPT;
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
}
