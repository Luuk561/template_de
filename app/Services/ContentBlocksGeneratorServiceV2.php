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
            ['role' => 'system', 'content' => 'Sie sind ein deutscher SEO-Texter. Geben Sie NUR gültiges JSON zurück.'],
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
        $currentMonth = now()->locale('de')->translatedFormat('F');
        $currentYear = now()->year;

        $uniqueFocusContext = $uniqueFocus
            ? "EINZIGARTIGER FOKUS: {$uniqueFocus} (subtil in den Inhalt einarbeiten)"
            : '';

        // Fetch active informatie artikelen for internal linking
        $infoPages = InformationPage::active()->ordered()->get(['title', 'slug']);
        $infoLinksContext = '';
        if ($infoPages->isNotEmpty()) {
            $linksList = $infoPages->map(fn($page) => "- <a href=\"/informationen/{$page->slug}\">{$page->title}</a>")->join("\n");
            $infoLinksContext = "\n\nINTERNE LINKS (verwenden Sie maximal 1-2 pro SEO-Block als HTML <a> Tags):\n{$linksList}\nBeispiel: <a href=\"/informationen/beispiel\">Ankertext</a>";
        }

        return <<<PROMPT
SEO-TEXTER FÜR: {$siteName}
NICHE: {$niche}
{$uniqueFocusContext}
DATUM: {$currentMonth} {$currentYear}{$infoLinksContext}

SCHREIBSTIL (immer)
- Schreiben Sie, als würden Sie es einer Person erklären: "Sie/du"
- Kurze Sätze. Keine Broschürensprache. Kein Marketingjargon
- Jeder Absatz enthält mindestens 1 konkretes Detail (Situation, Einschränkung, Auswahlkriterium oder Beispiel)

VERMEIDEN (nicht verwenden)
- Wörter: ideal, praktisch, perfekt, ausgezeichnet, großartig, optimal, essenziell, entscheidend
- Sätze: "nach einem langen Arbeitstag", "für viele Menschen", "für viele"
- Testbehauptungen: "wir haben getestet", "unsere Testergebnisse"

MACHEN SIE
- Konkret benennen, was jemand bemerkt/erfährt oder worauf jemand achtet
- Normale Produktspezifikationen/Beispiele sind in Ordnung (z.B. "12-16 km/h", "1,5m Lauffläche", "für Familie mit 4 Personen")
- Keine Prozentsätze oder 'Forschung sagt'-Behauptungen

FUNNEL (nur wenn der Block einen CTA erfordert)
- Enden Sie mit Verweis auf /produkte
- Kein Verweis auf Blogs/Reviews/Top5 als Endziel

OUTPUT
- Geben Sie NUR gültiges JSON zurück
- Genau die angeforderten Keys, kein zusätzlicher Text, kein Markdown, kein HTML

PROMPT;
    }

    /**
     * Block-specific prompt
     */
    private function getBlockPrompt(array $config, string $niche): string
    {
        $units = implode(', ', $config['units']);
        $role = $config['role'];
        $forbidden = isset($config['forbidden']) ? '- ' . implode("\n- ", $config['forbidden']) : 'Keine zusätzlichen Einschränkungen';
        $mustTreat = isset($config['must_treat']) ? '- ' . implode("\n- ", $config['must_treat']) : 'Keine verpflichtenden Elemente';

        $lengthInstructions = '';
        if (isset($config['lengths'])) {
            foreach ($config['lengths'] as $unit => $length) {
                $lengthInstructions .= "- {$unit}: {$length}\n";
            }
        }

        return <<<PROMPT
═══════════════════════════════════════════════════════════════════
BLOCK-AUFTRAG
═══════════════════════════════════════════════════════════════════

ROLLE: {$role}
NICHE: {$niche}

VERPFLICHTENDE BEHANDLUNG:
{$mustTreat}

VERBOTEN IN DIESEM BLOCK:
{$forbidden}

LÄNGEN:
{$lengthInstructions}

STIL: {$config['style']}

═══════════════════════════════════════════════════════════════════
OUTPUT
═══════════════════════════════════════════════════════════════════

Geben Sie JSON mit Keys zurück: {$units}
Jeder Key = plain text (kein HTML/Markdown)

Beispiel:
{
  "title": "Ihr Titel hier",
  "text": "Ihr Text hier"
}

Generieren Sie JETZT den Inhalt:
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
            $fallback[$unit] = "[Inhaltsgenerierung fehlgeschlagen - bitte manuell ausfüllen]";
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
                'role' => 'Homepage Hero: Positionierung + Versprechen + Erwartung',
                'forbidden' => [
                    'KEINE "Die besten X von [Monat] [Jahr]"',
                    'KEINE absoluten Behauptungen ("beste", "Nummer 1")',
                    'KEINE vagen Marketingwörter ohne Kontext',
                ],
                'must_treat' => [
                    'TITLE: Aktion + {niche}. Fokus auf Vergleichen/Finden, nicht auf Behauptungen.',
                    'SUBTITLE: Erklären Sie konkret, WAS Sie hier vergleichen und WORAUF (Specs, Erfahrungen, Unterschiede).',
                    'Subtitle fügt neue Informationen zum Titel hinzu.',
                ],
                'lengths' => [
                    'title' => '60-80 Zeichen',
                    'subtitle' => '80-120 Zeichen',
                ],
                'style' => 'Klar, inhaltlich, kein Hype. Fokus auf Entscheidung treffen.',
            ],


            'homepage.info' => [
                'units' => ['title', 'text'],
                'role' => 'Was macht diese Seite und wie hilft das beim Auswählen?',
                'forbidden' => [
                    'KEINE Produktvorteile oder Behauptungen',
                    'KEINE Kaufempfehlungen oder "beste Wahl"-Aussagen',
                    'KEINE Testbehauptungen',
                ],
                'must_treat' => [
                    'TITLE: Benennen Sie, dass die Seite beim Vergleichen und Auswählen in {niche} hilft (20–40 Zeichen).',
                    'TEXT: Erklären Sie, dass die Seite Einblick und Richtung gibt, indem Spezifikationen, Unterschiede und Erfahrungen gebündelt werden.',
                    'TEXT: Betonen Sie, dass der Benutzer selbst wählt, mit Hilfe von Übersicht und Kontext.',
                ],
                'lengths' => [
                    'title' => '20-40 Zeichen',
                    'text' => '80-100 Wörter',
                ],
                'style' => 'Hilfreich und richtungsweisend. Informativ ohne zu verkaufen.',
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
                'role' => 'Warum Menschen sich für {niche} entscheiden (Kontext + Situationen)',
                'forbidden' => [
                    'KEINE Auswahlberatung oder Kaufberatung',
                    'KEINE Vergleiche zwischen Modellen',
                    'KEINE Behauptungen oder Testaussagen',
                    'KEINE Superlative oder Marketingsprache',
                ],
                'must_treat' => [
                    'TITLE: Benennen Sie die Rolle oder den Wert von {niche} in einem konkreten Kontext (zu Hause/Alltag/Situation). Vermeiden Sie "warum Menschen X verwenden" für offensichtliche Produkte. (30–50 Zeichen)',
                    'INTRO: Beschreiben Sie das Problem oder die Situation, die zur Verwendung von {niche} führt, ohne Vorteile aufzuzählen.',
                    'SECTION 1: Situationsgetriebener Vorteil. Beschreiben Sie eine konkrete Nutzungssituation und welches Problem dieses Produkt darin löst. Fügen Sie 1-2 relevante interne Links aus der INTERNE LINKS Liste hinzu, wenn es zum Kontext passt.',
                    'SECTION 2: Praktischer Mechanismus. Erklären Sie, WAS das Produkt ermöglicht, was ohne dieses Produkt schwierig ist. Fügen Sie 1-2 relevante interne Links aus der INTERNE LINKS Liste hinzu, wenn es zum Kontext passt.',
                    'SECTION 3: Einschränkungen beseitigen. Beschreiben Sie, welcher häufige Einwand oder welches Problem durch diesen Produkttyp verringert wird. Fügen Sie 1-2 relevante interne Links aus der INTERNE LINKS Liste hinzu, wenn es zum Kontext passt.',
                ],
                'lengths' => [
                    'title' => '30-50 Zeichen',
                    'intro' => '60-80 Wörter',
                    'section1_title' => '20-40 Zeichen',
                    'section1_text' => '180-200 Wörter',
                    'section2_title' => '20-40 Zeichen',
                    'section2_text' => '180-200 Wörter',
                    'section3_title' => '20-40 Zeichen',
                    'section3_text' => '180-200 Wörter',
                ],
                'style' => 'Kontextuell und konkret. Keine Vorteilsliste, sondern Situationen, Ursachen und Effekte.',
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
                'role' => 'Auswahlhilfe: Wie bestimmen Sie, welcher Typ {niche} zu Ihnen passt?',
                'forbidden' => [
                    'KEINE Produktvorteile oder Pluspunkte',
                    'KEINE Marken- oder Modellnamen',
                    'KEINE Empfehlungen oder Rankings',
                    'KEINE Test- oder Bewertungsbehauptungen',
                ],
                'must_treat' => [
                    'TITLE: Wie wählen Sie ein {niche}? (30–50 Zeichen)',
                    'INTRO: Erklären Sie, dass verschiedene Situationen unterschiedliche Anforderungen stellen (30–40 Wörter).',
                    'SECTION 1: Nutzungssituation. Beschreiben Sie, wie Nutzungsfrequenz und Art der Nutzung die Wahl beeinflussen. Fügen Sie 1-2 relevante interne Links aus der INTERNE LINKS Liste hinzu, wenn es zum Kontext passt.',
                    'SECTION 2: Raum und Umgebung. Erklären Sie, wie verfügbarer Raum, Platzierung und Umgebung eine Rolle spielen. Fügen Sie 1-2 relevante interne Links aus der INTERNE LINKS Liste hinzu, wenn es zum Kontext passt.',
                    'SECTION 3: Belastbarkeit oder Kapazität. Beschreiben Sie, welcher Benutzertyp oder welches Nutzungsniveau entscheidend ist. Fügen Sie 1-2 relevante interne Links aus der INTERNE LINKS Liste hinzu, wenn es zum Kontext passt.',
                    'SECTION 4: Budget und Erwartungen. Erklären Sie, wie Preis mit Nutzung zusammenhängt, ohne Beträge zu nennen. Fügen Sie 1-2 relevante interne Links aus der INTERNE LINKS Liste hinzu, wenn es zum Kontext passt.',
                ],
                'lengths' => [
                    'title' => '30-50 Zeichen',
                    'intro' => '50-70 Wörter',
                    'section1_title' => '20-40 Zeichen',
                    'section1_text' => '180-200 Wörter',
                    'section2_title' => '20-40 Zeichen',
                    'section2_text' => '180-200 Wörter',
                    'section3_title' => '20-40 Zeichen',
                    'section3_text' => '180-200 Wörter',
                    'section4_title' => '20-40 Zeichen',
                    'section4_text' => '180-200 Wörter',
                ],
                'style' => 'Entscheidungsorientiert und neutral. Schreiben Sie in "Wenn-Dann"-Logik ohne Urteil.',
            ],


            'homepage.faq_1' => [
                'units' => ['question', 'answer'],
                'role' => 'FAQ: Für wen ist {niche} geeignet?',
                'forbidden' => [
                    'KEINE Wartung, Installation oder Technik',
                    'KEINE Nutzungsanwendungen',
                    'KEINE Kaufberatung oder Empfehlungen',
                ],
                'must_treat' => [
                    'QUESTION: Für welche Situationen oder Benutzer ist {niche} gedacht?',
                    'ANSWER: Beschreiben Sie konkrete Situationen, in denen dieser Produkttyp logisch in Betracht zu ziehen ist.',
                    'ANSWER: Verwenden Sie erkennbaren Kontext (Raum, Haushalt, Erfahrung, Häufigkeit), kein Urteil.',
                ],
                'lengths' => [
                    'question' => '60-100 Zeichen',
                    'answer' => '60-80 Wörter',
                ],
                'style' => 'Aufklärend und sachlich. Beschreibend, nicht überzeugend.',
            ],


            'homepage.faq_2' => [
                'units' => ['question', 'answer'],
                'role' => 'FAQ: Wartung und praktische Pflege',
                'forbidden' => [
                    'KEINE Eignung oder Zielgruppe',
                    'KEINE Nutzungssituationen',
                    'KEINE Marketingsprache',
                ],
                'must_treat' => [
                    'QUESTION: Wie warten Sie {niche} in der Praxis?',
                    'ANSWER: Beschreiben Sie wiederkehrende Wartungsarbeiten und Aufmerksamkeitspunkte.',
                    'ANSWER: Benennen Sie Aktionen mit Zeitangabe (täglich, wöchentlich, periodisch) ohne Zahlen oder Schemata.',
                ],
                'lengths' => [
                    'question' => '60-100 Zeichen',
                    'answer' => '60-80 Wörter',
                ],
                'style' => 'Praktisch und technisch, aber zugänglich.',
            ],


            'homepage.faq_3' => [
                'units' => ['question', 'answer'],
                'role' => 'FAQ: Anwendungen und Verwendung',
                'forbidden' => [
                    'KEINE Eignung oder Zielgruppe',
                    'KEINE Wartung oder Technik',
                    'KEINE Vorteile oder Pluspunkte',
                ],
                'must_treat' => [
                    'QUESTION: Wofür wird {niche} normalerweise verwendet?',
                    'ANSWER: Geben Sie mehrere konkrete Nutzungsszenarien oder Anwendungen.',
                    'ANSWER: Fokus auf das, was Menschen damit tun, nicht warum es besser ist.',
                ],
                'lengths' => [
                    'question' => '60-100 Zeichen',
                    'answer' => '60-80 Wörter',
                ],
                'style' => 'Beschreibend und konkret. Beispiele ohne Urteil.',
            ],

            // PRODUKTE BLOCKS
            'producten_index_hero_titel' => [
                'units' => ['title'],
                'role' => 'Produktübersicht: Positionierung + Aktion',
                'forbidden' => [
                    'KEINE Superlative oder Rankings',
                    'KEINE zeitgebundenen Behauptungen',
                    'KEINE Kaufberatung oder Empfehlungen',
                ],
                'must_treat' => [
                    'TITLE: Aktionsorientierte Übersicht von {niche}, mit Schwerpunkt auf Vergleichen und Überblick.',
                    'TITLE: Verwenden Sie ein Verb (Vergleichen / Ansehen / Entdecken) und benennen Sie explizit {niche}.',
                ],
                'lengths' => [
                    'title' => '40-60 Zeichen',
                ],
                'style' => 'Funktional und klar. Keine Marketingsprache, reine Übersicht.',
            ],

            'producten_index_info_blok_1' => [
                'units' => ['title', 'text'],
                'role' => 'Warum diese Seite zum Vergleichen verwenden?',
                'forbidden' => [
                    'KEINE Produktvorteile oder Eigenschaften',
                    'KEINE Kaufberatung oder Empfehlungen',
                    'KEINE Test- oder Bewertungsbehauptungen',
                ],
                'must_treat' => [
                    'TITLE: Benennen Sie, warum diese Seite geeignet ist, um {niche} zu vergleichen (30–50 Zeichen).',
                    'TEXT: Erklären Sie, WIE die Seite funktioniert: Filter, Sortierung, Spezifikationen nebeneinander.',
                    'TEXT: Beschreiben Sie den Prozess (ansehen, filtern, vergleichen), nicht das Ergebnis.',
                ],
                'lengths' => [
                    'title' => '30-50 Zeichen',
                    'text' => '70-90 Wörter',
                ],
                'style' => 'Erklärend und neutral. Fokus auf Funktionalität, nicht auf Überzeugen.',
            ],

            'producten_index_info_blok_2' => [
                'units' => ['title', 'text'],
                'role' => 'Vergleichskriterien: Worauf achten Sie bei {niche}?',
                'forbidden' => [
                    'KEINE Produktvorteile oder Empfehlungen',
                    'KEINE spezifischen Marken oder Modelle',
                    'KEINE Kaufberatung',
                ],
                'must_treat' => [
                    'TITLE: Benennen Sie, dass es um Aufmerksamkeitspunkte beim Vergleichen geht (30–50 Zeichen).',
                    'TEXT: Beschreiben Sie objektive Kriterien, in denen sich {niche} voneinander unterscheiden.',
                    'TEXT: Fokus auf messbare oder beobachtbare Aspekte (Nutzung, Raum, Kapazität, Ausführung), ohne Urteil.',
                ],
                'lengths' => [
                    'title' => '30-50 Zeichen',
                    'text' => '70-90 Wörter',
                ],
                'style' => 'Praktisch und informativ. Hilft strukturieren, nicht entscheiden.',
            ],

            'producten_top_hero_titel' => [
                'units' => ['title'],
                'role' => 'Top-Auswahl Übersicht: aktueller Kontext + Abgrenzung',
                'forbidden' => [
                    'KEINE Superlative außerhalb der Rangfolge (keine "besten überhaupt", "Nummer 1 Wahl")',
                    'KEINE Kaufversprechen oder Empfehlungssprache',
                ],
                'must_treat' => [
                    'TITLE: Benennen Sie, dass es um eine Top 5 Auswahl von {niche} geht.',
                    'TITLE: Fügen Sie Monat und Jahr für Aktualität und Kontext hinzu.',
                    'TITLE: Halten Sie es beschreibend und sachlich, keine behauptende Formulierung.',
                ],
                'lengths' => [
                    'title' => '50-70 Zeichen',
                ],
                'style' => 'Geschäftlich und aktuell. Gibt Kontext, kein Urteil.',
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
                'role' => 'Auswahl-Erklärung: Auf welcher Basis diese Top 5 zusammengestellt wurde',
                'forbidden' => [
                    'KEINE Test-, Mess- oder Bewertungsbehauptungen',
                    'KEINE Empfehlungen oder Kaufberatung',
                    'KEINE Superlative oder Bewertungen',
                    'KEINE Marken- oder Modellpräferenz aussprechen',
                ],
                'must_treat' => [
                    'TITLE: Erklären Sie, dass dieser Block die Auswahlkriterien der Top 5 beschreibt (30–50 Zeichen).',
                    'INTRO: Beschreiben Sie allgemein, wie eine Auswahl zustande kommt (Quellen, Vergleich, Abgrenzung), ohne Behauptungen.',
                    'SECTION 1: Objektives Kriterium 1, das relevant für den Vergleich von {niche} ist. Fügen Sie 1-2 relevante interne Links aus der INTERNE LINKS Liste hinzu, wenn es zum Kontext passt.',
                    'SECTION 2: Objektives Kriterium 2, das Einfluss auf die Zusammensetzung der Top 5 hat. Fügen Sie 1-2 relevante interne Links aus der INTERNE LINKS Liste hinzu, wenn es zum Kontext passt.',
                    'SECTION 3: Objektives Kriterium 3, das für Balance innerhalb der Auswahl sorgt. Fügen Sie 1-2 relevante interne Links aus der INTERNE LINKS Liste hinzu, wenn es zum Kontext passt.',
                ],
                'lengths' => [
                    'title' => '30-50 Zeichen',
                    'intro' => '60-80 Wörter',
                    'section1_title' => '30-50 Zeichen',
                    'section1_text' => '180-200 Wörter',
                    'section2_title' => '30-50 Zeichen',
                    'section2_text' => '180-200 Wörter',
                    'section3_title' => '30-50 Zeichen',
                    'section3_text' => '180-200 Wörter',
                ],
                'style' => 'Transparent und beschreibend. Erklären Sie den Auswahlprozess ohne Urteil oder Empfehlung.',
            ],


            // MARKEN BLOCKS
            'merken_index_hero_titel' => [
                'units' => ['title', 'subtitle'],
                'role' => 'Markenübersicht: Positionierung + Erklärung',
                'forbidden' => [
                    'KEINE Superlative oder Empfehlungen',
                    'KEINE Kaufberatung oder Präferenzen',
                    'KEINE Marketingsprache',
                ],
                'must_treat' => [
                    'TITLE: Benennen Sie, dass diese Seite Marken innerhalb {niche} vergleicht.',
                    'TITLE: Verwenden Sie ein aktionsorientiertes Verb (Vergleichen / Ansehen) und explizit {niche}.',
                    'SUBTITLE: Erklären Sie, warum Marken vergleichen sinnvoll ist innerhalb dieser Produktkategorie.',
                    'SUBTITLE: Fokus auf Unterschiede zwischen Marken, nicht auf Qualitätsurteile.',
                ],
                'lengths' => [
                    'title' => '40-60 Zeichen',
                    'subtitle' => '100-150 Zeichen',
                ],
                'style' => 'Funktional und neutral. Fokus auf Übersicht und Einblick.',
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
                'role' => 'Erklärung: Welche Rolle spielt die Marke bei {niche}?',
                'forbidden' => [
                    'KEINE Empfehlungen oder Präferenzen',
                    'KEINE Qualitätsbehauptungen oder Bewertungen',
                    'KEINE Kaufberatung',
                ],
                'must_treat' => [
                    'TITLE: Führen Sie das Thema Markenunterschiede innerhalb {niche} ein (30–50 Zeichen).',
                    'INTRO: Erklären Sie, dass Marken innerhalb dieser Kategorie sich in Fokus und Ansatz unterscheiden.',
                    'SECTION 1: Aspekt 1, in dem sich Marken unterscheiden (z.B. Positionierung oder Zielgruppe). Fügen Sie 1-2 relevante interne Links aus der INTERNE LINKS Liste hinzu, wenn es zum Kontext passt.',
                    'SECTION 2: Aspekt 2, in dem Marken strukturell unterscheiden (z.B. Angebot oder Linienbreite). Fügen Sie 1-2 relevante interne Links aus der INTERNE LINKS Liste hinzu, wenn es zum Kontext passt.',
                    'SECTION 3: Aspekt 3, der Einfluss auf die Erfahrung oder Erwartung bei der Wahl einer Marke hat. Fügen Sie 1-2 relevante interne Links aus der INTERNE LINKS Liste hinzu, wenn es zum Kontext passt.',
                ],
                'lengths' => [
                    'title' => '30-50 Zeichen',
                    'intro' => '60-80 Wörter',
                    'section1_title' => '30-50 Zeichen',
                    'section1_text' => '180-200 Wörter',
                    'section2_title' => '30-50 Zeichen',
                    'section2_text' => '180-200 Wörter',
                    'section3_title' => '30-50 Zeichen',
                    'section3_text' => '180-200 Wörter',
                ],
                'style' => 'Objektiv und beschreibend. Vergleichend ohne Urteil.',
            ],

            // BEWERTUNGEN BLOCKS
            'reviews.hero' => [
                'units' => ['title', 'subtitle'],
                'role' => 'Bewertungsübersicht: Kontext + Erwartung',
                'forbidden' => [
                    'KEINE Empfehlungen oder Rankings',
                    'KEINE Superlative',
                    'KEINE Kaufberatung',
                ],
                'must_treat' => [
                    'TITLE: Benennen Sie, dass dies eine Übersicht von Bewertungen innerhalb {niche} ist.',
                    'SUBTITLE: Erklären Sie, was jemand aus Bewertungen herausholt (Erfahrungen, Aufmerksamkeitspunkte, Unterschiede), ohne Urteil.',
                ],
                'lengths' => [
                    'title' => '40-60 Zeichen',
                    'subtitle' => '100-150 Zeichen',
                ],
                'style' => 'Zugänglich und informativ. Erwartungsmanagement, kein Verkauf.',
            ],


            'reviews_index_intro' => [
                'units' => ['title', 'text'],
                'role' => 'Warum Bewertungen lesen?',
                'forbidden' => [
                    'KEINE Testbehauptungen',
                    'KEINE Kaufberatung',
                    'KEINE Produktvorteile',
                ],
                'must_treat' => [
                    'TITLE: Führen Sie den Nutzen von Bewertungen beim Orientieren ein (30–50 Zeichen).',
                    'TEXT: Erklären Sie, welche Einblicke Bewertungen geben (Erfahrung, Verwendung, Einschränkungen), ohne Empfehlung.',
                ],
                'lengths' => [
                    'title' => '30-50 Zeichen',
                    'text' => '80-100 Wörter',
                ],
                'style' => 'Nützlich und neutral. Hilft verstehen, nicht wählen.',
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
                'role' => 'Erklärung Bewertungsrahmen für Reviews',
                'forbidden' => [
                    'KEINE Test- oder Messbehauptungen',
                    'KEINE Empfehlungen oder Präferenzen',
                    'KEINE Marken- oder Modellvergleiche',
                ],
                'must_treat' => [
                    'TITLE: Erklären Sie, dass dieser Block beschreibt, worauf Bewertungen basieren (30–50 Zeichen).',
                    'INTRO: Beschreiben Sie allgemein, welche Aspekte in Bewertungen zurückkommen, ohne Behauptungen.',
                    'SECTION 1: Aspekt 1, der häufig in Benutzererfahrungen zurückkommt. Fügen Sie 1-2 relevante interne Links aus der INTERNE LINKS Liste hinzu, wenn es zum Kontext passt.',
                    'SECTION 2: Aspekt 2, der Einfluss auf die tägliche Verwendung hat. Fügen Sie 1-2 relevante interne Links aus der INTERNE LINKS Liste hinzu, wenn es zum Kontext passt.',
                    'SECTION 3: Aspekt 3, der Erwartungen und Praxis miteinander verbindet. Fügen Sie 1-2 relevante interne Links aus der INTERNE LINKS Liste hinzu, wenn es zum Kontext passt.',
                ],
                'lengths' => [
                    'title' => '30-50 Zeichen',
                    'intro' => '60-80 Wörter',
                    'section1_title' => '30-50 Zeichen',
                    'section1_text' => '180-200 Wörter',
                    'section2_title' => '30-50 Zeichen',
                    'section2_text' => '180-200 Wörter',
                    'section3_title' => '30-50 Zeichen',
                    'section3_text' => '180-200 Wörter',
                ],
                'style' => 'Transparent und beschreibend. Objektiver Rahmen, kein Urteil.',
            ],

            // BLOGS BLOCKS
            'blogs.hero' => [
                'units' => ['title', 'subtitle'],
                'role' => 'Blog-Übersicht: Bildung + Kontext',
                'forbidden' => [
                    'KEINE Kaufberatung oder Empfehlungen',
                    'KEINE Superlative oder Behauptungen',
                    'KEINE Marketingsprache',
                ],
                'must_treat' => [
                    'TITLE: Benennen Sie, dass dies eine Übersicht von Blogs über {niche} ist.',
                    'SUBTITLE: Erklären Sie, welche Art von Wissen und Einblicken Blogs bieten (Erklärung, Vertiefung, Kontext).',
                ],
                'lengths' => [
                    'title' => '40-60 Zeichen',
                    'subtitle' => '100-180 Zeichen',
                ],
                'style' => 'Informativ und einladend. Fokus auf Lernen, nicht Verkaufen.',
            ],


            'blogs.intro' => [
                'units' => ['title', 'text'],
                'role' => 'Warum Blogs lesen innerhalb dieser Nische?',
                'forbidden' => [
                    'KEINE Kaufberatung',
                    'KEINE Produktvorteile',
                    'KEINE Testbehauptungen',
                ],
                'must_treat' => [
                    'TITLE: Führen Sie das Ziel von Blogs in einem kurzen Satz ein (20–40 Zeichen).',
                    'TEXT: Erklären Sie, welche Rolle Blogs beim Orientieren und Verstehen spielen, ohne Richtung zu geben.',
                ],
                'lengths' => [
                    'title' => '20-40 Zeichen',
                    'text' => '70-90 Wörter',
                ],
                'style' => 'Zugänglich und aufklärend. Neutraler Ton.',
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
                'role' => 'Übersicht der Blog-Themen innerhalb {niche}',
                'forbidden' => [
                    'KEINE Kaufberatung oder Empfehlungen',
                    'KEINE Marken- oder Modellpräferenzen',
                    'KEINE Test- oder Bewertungsbehauptungen',
                ],
                'must_treat' => [
                    'TITLE: Erklären Sie, dass dieser Block beschreibt, welche Themen behandelt werden (30-50 Zeichen).',
                    'INTRO: Beschreiben Sie allgemein, welche Wissensgebiete oder Themen in Blogs zurückkommen.',
                    'SECTION 1: Thema 1, das Vertiefung in Verwendung oder Hintergrund gibt. Fügen Sie 1-2 relevante interne Links aus der INTERNE LINKS Liste hinzu, wenn es zum Kontext passt.',
                    'SECTION 2: Thema 2, das Einblick in Unterschiede oder Aufmerksamkeitspunkte gibt. Fügen Sie 1-2 relevante interne Links aus der INTERNE LINKS Liste hinzu, wenn es zum Kontext passt.',
                    'SECTION 3: Thema 3, das beim Verstehen oder Orientieren hilft. Fügen Sie 1-2 relevante interne Links aus der INTERNE LINKS Liste hinzu, wenn es zum Kontext passt.',
                ],
                'lengths' => [
                    'title' => '30-50 Zeichen',
                    'intro' => '60-80 Wörter',
                    'section1_title' => '30-50 Zeichen',
                    'section1_text' => '180-200 Wörter',
                    'section2_title' => '30-50 Zeichen',
                    'section2_text' => '180-200 Wörter',
                    'section3_title' => '30-50 Zeichen',
                    'section3_text' => '180-200 Wörter',
                ],
                'style' => 'Informativ und strukturiert. Übersicht ohne Urteil.',
            ],

        ];
    }
}
