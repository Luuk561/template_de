<?php

namespace App\Console\Commands;

use App\Models\InformationPage;
use App\Services\OpenAIService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateAllInformationPages extends Command
{
    protected $signature = 'generate:all-information-pages {--niche=} {--test : Generate only 1 page for testing}';
    protected $description = 'Generate 5-7 complete information pages in one go (topics + content)';

    public function handle()
    {
        $niche = $this->option('niche') ?? $this->ask('Wat is de niche/product categorie? (bijv. airfryer met dubbele lade, sporthorloge)');

        if (empty($niche)) {
            $this->error('Niche is verplicht!');
            return 1;
        }

        $isTest = $this->option('test');

        $this->info("=================================================");
        $this->info("  GENEREREN VAN INFORMATIE PAGINA'S");
        $this->info("  Niche: {$niche}");
        if ($isTest) {
            $this->info("  MODE: TEST (1 pagina)");
        }
        $this->info("=================================================");
        $this->newLine();

        try {
            $openai = app(OpenAIService::class);

            // STEP 1: Generate topics
            $this->info($isTest ? 'STAP 1/2: Genereren van 1 test topic...' : 'STAP 1/2: Genereren van 5-7 topics...');
            $topicsPrompt = $this->buildTopicsPrompt($niche, $isTest);
            $topicsJson = $openai->generateFromPrompt($topicsPrompt, 'gpt-4o-mini');

            // Clean JSON
            $topicsJson = preg_replace('/^```(?:json)?\s*/m', '', $topicsJson);
            $topicsJson = preg_replace('/\s*```$/m', '', $topicsJson);
            $topics = json_decode(trim($topicsJson), true);

            if (!$topics || !is_array($topics)) {
                $this->error('Kon topics niet parsen. JSON response was ongeldig.');
                return 1;
            }

            $this->info("Gevonden: " . count($topics) . " topics");
            $this->newLine();

            // STEP 2: Generate content for each topic
            $this->info('STAP 2/2: Genereren van content voor elk topic...');
            $this->newLine();

            $bar = $this->output->createProgressBar(count($topics));
            $bar->start();

            foreach ($topics as $index => $topic) {
                $menuTitle = $topic['menu_title'] ?? $topic['title'] ?? "Topic " . ($index + 1);
                $articleTitle = $topic['article_title'] ?? $menuTitle;
                $slug = $topic['slug'] ?? Str::slug($menuTitle);
                $metaDescription = $topic['meta_description'] ?? '';

                // Check if slug exists
                if (InformationPage::where('slug', $slug)->exists()) {
                    $slug = $slug . '-' . time();
                }

                // STEP 2.1: Generate detailed outline
                $outlinePrompt = $this->buildOutlinePrompt($articleTitle, $niche);
                $outlineJson = $openai->generateFromPrompt($outlinePrompt, 'gpt-4o-mini');
                $outlineJson = preg_replace('/^```(?:json)?\s*/m', '', $outlineJson);
                $outlineJson = preg_replace('/\s*```$/m', '', $outlineJson);
                $outline = json_decode(trim($outlineJson), true);

                if (!$outline || !isset($outline['sections'])) {
                    $this->error("Kon outline niet genereren voor: {$articleTitle}");
                    continue;
                }

                // STEP 2.2: Generate intro
                $introPrompt = $this->buildIntroPrompt($articleTitle, $niche, $outline);
                $intro = $openai->generateFromPrompt($introPrompt, 'gpt-4o-mini');
                $intro = $this->cleanHtml($intro);

                // STEP 2.3: Generate each section
                $sections = [];
                foreach ($outline['sections'] as $sectionOutline) {
                    $sectionPrompt = $this->buildSectionPrompt($articleTitle, $niche, $sectionOutline, $outline);
                    $sectionContent = $openai->generateFromPrompt($sectionPrompt, 'gpt-4o-mini');
                    $sections[] = $this->cleanHtml($sectionContent);
                }

                // STEP 2.4: Generate conclusion
                $conclusionPrompt = $this->buildConclusionPrompt($articleTitle, $niche, $outline);
                $conclusion = $openai->generateFromPrompt($conclusionPrompt, 'gpt-4o-mini');
                $conclusion = $this->cleanHtml($conclusion);

                // Combine all parts
                $content = $intro . "\n\n" . implode("\n\n", $sections) . "\n\n" . $conclusion;

                // Generate excerpt
                $excerptPrompt = $this->buildExcerptPrompt($articleTitle, $content);
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

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->newLine();

            $this->info("=================================================");
            $this->info("  KLAAR!");
            $this->info("  " . count($topics) . " informatie pagina's gegenereerd");
            $this->info("=================================================");
            $this->newLine();

            $this->table(
                ['Menu Titel', 'Artikel Titel', 'Slug'],
                collect($topics)->map(fn($t) => [
                    $t['menu_title'] ?? $t['title'] ?? '-',
                    $t['article_title'] ?? '-',
                    $t['slug'] ?? '-'
                ])->toArray()
            );

            return 0;

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }

    private function cleanHtml(string $html): string
    {
        $html = preg_replace('/^```(?:html)?\s*/m', '', $html);
        $html = preg_replace('/\s*```$/m', '', $html);
        return trim($html);
    }

    private function buildOutlinePrompt(string $title, string $niche): string
    {
        return <<<PROMPT
Je bent een expert content strategist voor Nederlandse affiliate websites over {$niche}.

OPDRACHT: Maak een gedetailleerde outline voor het artikel: "{$title}"

De outline moet een coherent verhaal vormen dat logisch opbouwt van intro naar conclusie.

VEREISTEN:
- 4-6 H2 hoofdsecties (sections)
- Elke sectie moet een duidelijke rol hebben in het verhaal
- Secties moeten logisch op elkaar voortbouwen
- Elk sectie heeft 2-4 key points die behandeld worden

OUTPUT FORMAT (JSON):
{
  "intro_summary": "Wat de intro moet behandelen (50 woorden)",
  "sections": [
    {
      "h2_title": "Titel van de sectie",
      "purpose": "Waarom deze sectie belangrijk is",
      "key_points": [
        "Punt 1 dat behandeld moet worden",
        "Punt 2 met specifiek voorbeeld",
        "Punt 3 met cijfers/data"
      ],
      "suggested_elements": ["table", "list", "blockquote", "highlight"]
    }
  ],
  "conclusion_summary": "Wat de conclusie moet bevatten (50 woorden)"
}

INHOUDELIJKE FOCUS:
- Decision-stage content (help mensen kiezen)
- Concrete cijfers en ranges
- Praktische scenarios
- GEEN merknamen, WEL algemene categorieën

Genereer nu de outline voor: "{$title}"
Return ALLEEN valid JSON, geen extra tekst.
PROMPT;
    }

    private function buildIntroPrompt(string $title, string $niche, array $outline): string
    {
        $introSummary = $outline['intro_summary'] ?? '';
        $sectionsPreview = implode(', ', array_map(fn($s) => $s['h2_title'] ?? '', $outline['sections'] ?? []));

        return <<<PROMPT
Je bent een expert contentschrijver voor Nederlandse affiliate websites over {$niche}.

OPDRACHT: Schrijf een pakkende intro voor: "{$title}"

CONTEXT VAN HET ARTIKEL:
Het artikel behandelt achtereenvolgens: {$sectionsPreview}

INTRO MOET BEVATTEN:
{$introSummary}

VEREISTEN:
- 150-200 woorden
- Hook de lezer direct (begin met vraag, scenario, of verrassend feit)
- Leg relevantie uit: waarom is dit belangrijk voor de beslissing?
- Preview wat de lezer gaat leren (zonder spoilers)
- Tone: professioneel maar toegankelijk, zoals een expert vriend

SCHRIJFSTIJL:
- Begin direct, geen clichés zoals "In de wereld van..."
- Gebruik concreet voorbeeld of scenario in eerste zin
- Stel impliciete vraag die het artikel beantwoordt

Return ALLEEN de HTML voor de intro (alleen <p> tags), geen H1, geen extra markup.
PROMPT;
    }

    private function buildSectionPrompt(string $title, string $niche, array $sectionOutline, array $fullOutline): string
    {
        $h2Title = $sectionOutline['h2_title'] ?? 'Sectie';
        $purpose = $sectionOutline['purpose'] ?? '';
        $keyPoints = isset($sectionOutline['key_points']) ? implode("\n- ", $sectionOutline['key_points']) : '';
        $elements = isset($sectionOutline['suggested_elements']) ? implode(', ', $sectionOutline['suggested_elements']) : 'list';

        return <<<PROMPT
Je bent een expert contentschrijver voor Nederlandse affiliate websites over {$niche}.

OPDRACHT: Schrijf de sectie "{$h2Title}" voor het artikel "{$title}"

DOEL VAN DEZE SECTIE:
{$purpose}

KEY POINTS DIE BEHANDELD MOETEN WORDEN:
- {$keyPoints}

SUGGESTIES VOOR VISUELE ELEMENTEN:
Gebruik waar relevant: {$elements}

VEREISTEN:
- Begin met <h2>{$h2Title}</h2>
- 250-400 woorden totaal voor deze sectie
- 2-4 H3 subsecties
- Concrete cijfers, ranges, voorbeelden
- Praktische scenarios ("Als je..., dan...")
- GEEN merknamen, wel algemene categorieën

VISUELE ELEMENTEN (gebruik er MAXIMAAL 1 per sectie, en NIET in elke sectie):

Highlight box (SPAARZAAM gebruiken - alleen voor echt belangrijke waarschuwingen):
<div class="not-prose bg-gray-50 border border-gray-200 rounded-xl p-8 my-12">
  <h3 class="text-xl font-semibold text-gray-900 mb-3">Belangrijk om te weten</h3>
  <p class="text-gray-700 leading-relaxed">...</p>
</div>

Tabel voor vergelijkingen (goed voor cijfers):
<table>
  <thead><tr><th>...</th><th>...</th></tr></thead>
  <tbody><tr><td>...</td><td>...</td></tr></tbody>
</table>

Blockquote voor tips (gebruik dit vaker dan highlight boxes):
<blockquote>Expert tip of verrassend feit</blockquote>

BELANGRIJK: Gebruik highlight boxes ZEER SPAARZAAM - maximaal 1-2 in het HELE artikel!

SCHRIJFSTIJL:
- Elke H3 subsectie: 80-120 woorden
- Begin paragraaf met topic sentence
- Ondersteun met cijfers/voorbeelden
- Eindig met actionable insight

Return ALLEEN de HTML voor deze sectie (start met <h2>, inclusief alle subsecties).
PROMPT;
    }

    private function buildConclusionPrompt(string $title, string $niche, array $outline): string
    {
        $conclusionSummary = $outline['conclusion_summary'] ?? '';
        $sectionsPreview = implode(', ', array_map(fn($s) => $s['h2_title'] ?? '', $outline['sections'] ?? []));

        return <<<PROMPT
Je bent een expert contentschrijver voor Nederlandse affiliate websites over {$niche}.

OPDRACHT: Schrijf de conclusie voor: "{$title}"

HET ARTIKEL HEEFT BEHANDELD:
{$sectionsPreview}

CONCLUSIE MOET BEVATTEN:
{$conclusionSummary}

VEREISTEN:
- Begin met <h2>Conclusie</h2> of <h2>De juiste keuze maken</h2>
- 200-300 woorden
- Vat kernpunten samen (geen copy-paste, nieuwe formulering)
- Geef decision framework: "Als je [situatie], dan [advies]"
- Eindig met praktische next step voor lezer
- Tone: behulpzam, actionable, geen pushende sales taal

STRUCTUUR:
1. Korte samenvatting van belangrijkste inzichten (2-3 zinnen)
2. Decision framework met 3-4 scenario's
3. Finale advies en aanmoediging

VOORBEELD DECISION FRAMEWORK:
"Voor kleine huishoudens (1-2 personen) is [X] meestal voldoende, terwijl grotere gezinnen meer baat hebben bij [Y]. Let vooral op [criterium] als je [use case] hebt."

Return ALLEEN de HTML voor de conclusie (start met <h2>).
PROMPT;
    }

    private function buildTopicsPrompt(string $niche, bool $testMode = false): string
    {
        $count = $testMode ? '1' : '5-7';
        return <<<PROMPT
Je bent een SEO-expert en copywriter voor Nederlandse affiliate websites die zich richt op {$niche}.

DOEL: Genereer {$count} informatie pagina onderwerpen die ECHTE gebruikersvragen beantwoorden tijdens het koopproces.

KRITISCHE REGEL: Denk als een koper. Vraag jezelf af: "Zou IK dit googlen voordat ik een {$niche} koop?"
- JA: Aanschafkosten, opslag/maatvoering, vergelijken van varianten, veelgemaakte fouten, welke functies, welke maat
- NEE: Stroomkosten per jaar, technische specs, geschiedenis, ABSOLUUT GEEN RECEPTEN

VERBODEN ONDERWERPEN (genereer deze NOOIT):
❌ Recepten ("wat kan ik bereiden", "gerechten maken")
❌ Gebruikstips NADAT je gekocht hebt ("hoe gebruik je", "onderhoudstips")
❌ Stroomkosten/energie verbruik
❌ Geschiedenis of technische achtergronden
❌ Algemene kooktips

ALLEEN onderwerpen die helpen bij de KEUZE tussen verschillende producten!

BELANGRIJKE CRITERIA:
1. Beantwoord vragen die mensen ECHT hebben tijdens het oriënteren/vergelijken
2. Focus op praktische koopoverwegingen (niet theoretische info)
3. Specifiek voor deze niche (niet algemene producttips)
4. Mensen moeten dit daadwerkelijk googlen
5. Moet natuurlijk leiden naar productvergelijking

SUCCESVOLLE PATRONEN - gebruik deze structuren:

1. VERGELIJKING (met/zonder, of, vs):
   - "Enkele of dubbele lade airfryer?"
   - "Sporthorloge met of zonder GPS?"
   - "Robotstofzuiger met of zonder dweilfunctie?"
   - "Online vertalen of vertaalapparaat kopen?"

2. SPECIFICATIE KEUZE (hoeveel, welke maat):
   - "Welke maat airfryer past bij jou?"
   - "Hoeveel talen heb je echt nodig?" (vertaalapparaat)
   - "Welk schermformaat past bij jou?" (e-reader)
   - "Hoeveel opslagruimte heb je nodig?"

3. PRAKTISCHE AFWEGING:
   - "Hoeveel ruimte neemt een [product] in?"
   - "Hoeveel geluid maakt een [product]?"
   - "Hoeveel batterijduur is voldoende?"
   - "Stil of krachtig: wat is belangrijker?" (massagegun)

4. FUNCTIE/FEATURE SELECTIE:
   - "Belangrijkste functies bij een [product]"
   - "Welke sensors heb je echt nodig?" (sporthorloge)
   - "Hoeveel opzetstukken zijn essentieel?" (massagegun)

5. PRIJS/BUDGET:
   - "Wat kost een goede [product]?"
   - "Wat is een realistische prijs voor [product]?"

6. USE CASE SPECIFIEK:
   - "[Product] voor [situatie A] of [situatie B]?"
   - "Sporthorloge voor hardlopen of zwemmen?"
   - "Geschikt voor huisdieren?" (robotstofzuiger)

7. FOUTEN/VALKUILEN:
   - "Veelgemaakte fouten bij [product] kopen"
   - "Waar moet je op letten bij [product]?"

8. BETROUWBAARHEID:
   - "Welke merken zijn het meest betrouwbaar?"
   - "Welke [product] gaat het langst mee?"

CONCRETE VOORBEELDEN per niche:

Airfryer met dubbele lade:
✅ "Enkele of dubbele lade airfryer?"
✅ "Welke maat airfryer past bij jou?"
✅ "Hoeveel ruimte neemt een airfryer in?"
✅ "Belangrijkste functies bij een airfryer"
✅ "Veelgemaakte fouten bij airfryer kopen"
✅ "Wat is een realistische prijs?"
✅ "Welke merken zijn het meest betrouwbaar?"

Robotstofzuiger:
✅ "Robotstofzuiger met of zonder dweilfunctie?"
✅ "Welk oppervlak kun je automatiseren?"
✅ "Navigatie: camera of laser?"
✅ "Hoeveel geluid maakt een robotstofzuiger?"
✅ "Zelflegende basisstation: wel of niet?"
✅ "Geschikt voor huisdieren?"
✅ "Wat kost een goede robotstofzuiger?"

Massagegun:
✅ "Welke intensiteitsstanden heb je nodig?"
✅ "Massagegun voor nek of hele lichaam?"
✅ "Stil of krachtig: wat is belangrijker?"
✅ "Hoeveel opzetstukken zijn essentieel?"
✅ "Draadloos of met snoer?"
✅ "Wat is een goede prijs?"

Sporthorloge:
✅ "Sporthorloge met of zonder GPS?"
✅ "Welke sensors heb je echt nodig?"
✅ "Hoeveel batterijduur is voldoende?"
✅ "Sporthorloge voor hardlopen of zwemmen?"
✅ "Smartwatch of specifiek sporthorloge?"

E-reader:
✅ "E-reader met of zonder verlichting?"
✅ "Hoeveel opslagruimte heb je nodig?"
✅ "Welk schermformaat past bij jou?"
✅ "Waterdicht: wel of niet nodig?"
✅ "E-reader of tablet voor lezen?"

AI Vertaalapparaat:
✅ "Online vertalen of vertaalapparaat kopen?"
✅ "Hoeveel talen heb je echt nodig?"
✅ "Vertaalapparaat met of zonder internet?"
✅ "Voor welke situaties is het handig?"
✅ "Batterijduur: waar moet je op letten?"

TAALGEBRUIK - BELANGRIJKSTE REGEL:
- menu_title: 30-45 karakters, natuurlijke Nederlandse spreektaal
- Gebruik lidwoorden (de, een, het) en voorzetsels (voor, met, bij)
- Klink als een mens die een vraag stelt, niet als een robot
- GOED: "Welke maat airfryer heb je nodig?"
- FOUT: "Maat airfryer kiezen" (te robotachtig)
- GOED: "Enkele of dubbele lade?"
- FOUT: "Dubbele vs enkele lade" (te formeel)

FORMAAT per topic:
- menu_title: Natuurlijke vraag of titel, 30-45 karakters, met lidwoorden
- article_title: Uitgebreidere versie, 50-70 karakters, kan context toevoegen
- slug: URL-friendly versie
- meta_description: SEO beschrijving (150-155 karakters)

Genereer nu {$count} perfecte topic(s) voor: {$niche}

VERPLICHT:
- Gebruik minimaal 4 van de 8 patronen hierboven
- Zorg voor variatie in vraagtypen
- Elk topic moet passen bij 1 van de patronen
- Denk specifiek na over deze niche: wat maakt dit product uniek? Welke keuzes moet de koper maken?

PROCES:
1. Analyseer de niche: wat zijn de specifieke keuzemomenten voor dit product?
2. Selecteer 5-7 patronen die het best passen
3. Formuleer natuurlijke Nederlandse vragen/titels
4. Check: zou ik dit googlen voordat ik koop?

Return ALLEEN een JSON array, niets anders:
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

    private function buildExcerptPrompt(string $title, string $content): string
    {
        return <<<PROMPT
Schrijf een korte, pakkende samenvatting van maximaal 200 karakters voor het volgende artikel.

TITEL: {$title}

CONTENT:
{$content}

EISEN:
- Max 200 karakters
- Pakkend en informatief
- Geeft kernboodschap weer
- Nederlandse taal
- Geen emoji's
- Eindigt met punt

Alleen de excerpt tekst, geen extra uitleg.
PROMPT;
    }
}
