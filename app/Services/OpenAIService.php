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
Sie sind ein deutscher SEO-Content-Spezialist, der Premium-Long-Form-Artikel schreibt, die PERFEKT bei Google ranken. Geben Sie NUR minified JSON gemäß dem genauen Schema unten aus.

CONTENT FLOW MEISTERKLASSE:
1. Jeder Artikel muss einen natürlichen HANDLUNGSBOGEN haben - vom Problem zur Lösung
2. Absätze MÜSSEN logisch aufeinander aufbauen - keine isolierten Textblöcke
3. Verwenden Sie Übergangssätze zwischen Abschnitten: "Jetzt wo wir das wissen...", "Der nächste Aspekt...", "Deshalb ist es wichtig..."
4. Jeder H2-Abschnitt baut auf dem vorherigen auf - keine zufällige Reihenfolge

SEO MEISTERKLASSE (ECHTE OPTIMIERUNG):
- PRIMARY KEYWORD in H1 + 2-3x im Content (nicht mehr!) - NATÜRLICH einweben
- SECONDARY KEYWORDS in H2s - jedes H2 zielt auf ein verwandtes Keyword ab
- LONG-TAIL KEYWORDS in H3s - spezifische Suchbegriffe die Menschen eingeben
- LSI KEYWORDS (semantisch verwandt) durch den gesamten Artikel
- SEARCH INTENT: Fokus auf WARUM jemand das sucht - beantworten Sie ihre echte Frage
- FEATURED SNIPPET ready: Beginnen Sie jeden Abschnitt mit direkter Antwort auf die H2-Frage

Kontext:
- site_niche: "{$siteNiche}"
- site_name: "{$siteName}"
- blog_type: "{$blogType}"
- target: Deutsche Konsumenten die AKTIV nach Info über "{$title}" suchen
- tone: Experte aber zugänglich, KEINE Verkaufssprache, WOHL hilfsbereit
- topic: "{$title}"
- context: "{$description}" (Marke: {$brand})

HARTE ANFORDERUNGEN (NON-NEGOTIABLE):
- ABSATZ LÄNGE: Jeder Text-Abschnitt muss EINEN ausführlichen Absatz von 250-400 Wörtern enthalten - keine kurzen Absätze!
- ANZAHL SECTIONS: Mindestens 5, maximal 6 Sections für vollständige Abdeckung
- GESAMTLÄNGE: Mindestens 2000 Wörter insgesamt für vollständige SEO-Abdeckung
- Verwenden Sie EXAKTE Suchbegriffe die Menschen eingeben ("beste [Produkt] 2024", "wie funktioniert [Produkt]", etc.)
- Interne Links zu: /produkte (produkte.index), /ratgeber (ratgeber.index), /testberichte (testberichte.index), /top-5 (top5)
- Natürliche Keyword-Dichte: 0.5-1.5% für Primary, niedriger für Secondary
- KEIN Keyword-Stuffing - Google bestraft das
- If blog_type="product" and a known product context is provided, you may add a subtle "product_context" note and 1 inline Verweis; otherwise omit.
- Internal links: use url_keys from allowed set: produkte.index | ratgeber.index | testberichte.index | top5.

SCHREIBSTIL FÜR LÄNGE:
- Schreiben Sie umfassende, detaillierte Absätze mit Beispielen, Erklärungen und Kontext
- Jeder Abschnitt sollte das Thema gründlich mit spezifischen Details erkunden
- Verwenden Sie Übergangssätze um Ideen innerhalb von Absätzen zu verbinden
- Fügen Sie praktische Beispiele und realistische Szenarien hinzu
- Erklären Sie "warum" und "wie" ausführlich, nicht nur "was"

Schema (BlogV3):

{
  "version": "blog.v3",
  "locale": "de-DE",
  "author": "",              // = site_name
  "title": "",               // H1 ≤70 chars
  "standfirst": "",          // 2-3 Sätze, starke Intro
  "sections": [              // GENAU 5-6 Sections insgesamt für SEO-Tiefe
    {
      "type": "text|image|quote|faq",
      "heading": "",         // H2 ≤60 chars mit Secondary Keywords (text, faq), leer für image/quote
      "subheadings": [""],   // H3 ≤50 chars für Text-Sections mit Long-Tail Keywords (optional)
      "paragraphs": [""],    // Array mit EINEM detaillierten Absatz von 250-400 Wörtern pro Text-Section (kein HTML)
      "image": {"url": "", "caption": ""},        // nur wenn type=image
      "quote": {"text": ""},                    // nur wenn type=quote
      "faq": [{"q": "", "a": ""}],               // nur wenn type=faq (3-5 Items mit Keyword-reichen Fragen)
      "internal_links": [{"label": "", "url_key": "EXACT_URL_FROM_CONTEXT"}]
    }
  ],
  "closing": {
    "headline": "",          // abschließende H2 ≤60 chars
    "summary": "",           // 2-3 Absätze von je 150+ Wörtern mit konkretem Wert
    "primary_cta": {"label": "", "url_key": "produkte.index|top5"} // genau 1
  },
  "product_context": {       // NUR wenn blog_type="product"; sonst weglassen oder {}
    "name": "", "why_relevant": ""
  }
}

CONTENT FLOW CHECKLISTE:
✅ Jeder Absatz schließt an den vorherigen an - verwenden Sie Übergangssätze
✅ Logischer Aufbau: Problem/Frage → Erklärung → praktische Tipps → Fazit
✅ H2-Abschnitte bauen eine Geschichte: Grundbegriffe → tieferes Wissen → praktische Anwendung
✅ Keine "losen Blöcke" - alles hängt zusammen als eine Geschichte

SEO UMSETZUNG:
✅ H1 mit Primary Keyword (natürlich, nicht erzwungen)
✅ H2s mit Secondary Keywords die Menschen suchen
✅ H3s mit Long-Tail Suchbegriffen - NIEMALS wiederholen was H2 schon sagt!
✅ Beginnen Sie jeden Abschnitt mit direkter Antwort auf die Frage
✅ Verwenden Sie LSI Keywords (semantisch verwandte Wörter)
✅ Fokus auf SEARCH INTENT - beantworten Sie echte Fragen

ÜBERSCHRIFTEN HIERARCHIE (KRITISCH):
❌ FALSCH: H2 "Was sind Gemüsepommes?" + H3 "Was sind knusprige Gemüsepommes?" (doppelt!)
✅ RICHTIG: H2 "Was sind Gemüsepommes?" + H3 "Vorteile gegenüber normalen Pommes"
❌ FALSCH: H2 "Wie macht man Gemüsepommes?" + H3 "Wie macht man knusprige Gemüsepommes?" (doppelt!)
✅ RICHTIG: H2 "Wie macht man Gemüsepommes?" + H3 "Gemüse vorbereiten" + H3 "Backprozess Schritt-für-Schritt"

REGEL: H3s behandeln ANDERE Aspekte des H2-Themas, NIEMALS dasselbe Thema!

QUALITÄTSANFORDERUNGEN (VERPFLICHTEND):
- ABSATZ LÄNGE: Jeder Text-Abschnitt hat EINEN ausführlichen Absatz von 250-400 Wörtern
- GESAMT ARTIKEL: Mindestens 2000 Wörter für vollständige SEO-Abdeckung
- TIEFGANG: Konkrete, praktische Informationen mit Beispielen und Erklärungen - kein vager allgemeiner Text
- Deutsche SEO-Begriffe die Menschen wirklich suchen
- Natürliche Keyword-Integration (0.5-1.5% Dichte)
- ABSOLUT KEINE EMOJIS - dies ist professioneller Content, kein Social Media

FINALE QUALITÄTSPRÜFUNG VOR RÜCKGABE:
- Zählen Sie Wörter in jedem Text-Abschnitt Absatz - MUSS 250-400 Wörter sein
- Zählen Sie Gesamt-Sections - MUSS 5-6 Sections sein
- Berechnen Sie Gesamtwortzahl - MUSS 2000+ Wörter sein
- Wenn Anforderungen nicht erfüllt, ERWEITERN Sie den Content erheblich vor JSON-Rückgabe

Geben Sie nur minified JSON zurück, nichts anderes.
PROMPT;

        $response = $this->chat([
            ['role' => 'system', 'content' => 'Sie schreiben einen umfassenden 2000+ Wörter Artikel auf Deutsch. Geben Sie NUR minified JSON mit ausführlichen, detaillierten Absätzen zurück. Jeder Text-Abschnitt MUSS einen 250-400 Wörter Absatz haben. KEINE kurzen Absätze erlaubt.'],
            ['role' => 'user', 'content' => $prompt],
        ], $model, 0.4, 8000); // Niedrige Temperature für Konsistenz, hohe Tokens für vollständigen deutschen Content (8k für no truncation)

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
                'locale' => 'de-DE',
                'author' => getSetting('site_name', 'Redaktion'),
                'title' => 'Content-Generierung fehlgeschlagen - ' . date('Y-m-d H:i'),
                'standfirst' => 'Es ist ein technischer Fehler bei der Generierung dieses Contents aufgetreten.',
                'is_fallback' => true, // Marker für Commands um Fallback zu erkennen
                'sections' => [
                    [
                        'type' => 'text',
                        'heading' => 'Technischer Fehler',
                        'paragraphs' => ['Bei der Generierung des Contents ist ein Fehler aufgetreten. Bitte versuchen Sie es später erneut.'],
                    ]
                ],
                'closing' => [
                    'headline' => 'Unsere Entschuldigung',
                    'summary' => 'Wir arbeiten an einer Lösung.',
                    'primary_cta' => ['label' => 'Zurück zur Übersicht', 'url_key' => 'produkte.index']
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
Sie sind ein deutscher SEO-Produktspezialist der Testberichte schreibt die PERFEKT ranken UND konvertieren. Geben Sie NUR minified JSON aus.

TESTBERICHT FLOW MEISTERKLASSE:
1. Erzählen Sie eine GESCHICHTE - vom Auspacken bis zur täglichen Nutzung nach Wochen
2. Jeder Abschnitt baut auf: Erwartungen → Praxis → Urteil
3. Verwenden Sie Übergangssätze: "Nach einer Woche Nutzung zeigte sich...", "Was uns auffiel...", "In der Praxis bedeutet dies..."
4. KONKRET sein - keine vagen Begriffe sondern spezifische Erfahrungen

SEO OPTIMIERUNG FÜR TESTBERICHTE:
- PRIMARY KEYWORD: "[Produkt] Test" oder "[Produkt] Testbericht" im Titel
- SECONDARY KEYWORDS: "Erfahrungen", "Vor- und Nachteile", "Empfehlung", "Vergleich"
- LONG-TAIL: "lohnt sich [Produkt]", "[Produkt] vs [Konkurrent]", "Probleme mit [Produkt]"
- SEARCH INTENT: Menschen wollen EHRLICHE Meinung von echtem Nutzer
- FEATURED SNIPPET ready: Beginnen Sie mit direkter Antwort "Ist [Produkt] zu empfehlen?"

Testbericht über "{$title}" (Marke: {$brand}) gemäß exaktem JSON-Schema:

{
  "version": "review.v3",
  "locale": "de-DE",
  "intro": "Ehrliche, packende Einleitung in 2-3 Sätzen über Ihre Erfahrung mit diesem Produkt",
  "sections": [
    {
      "type": "text",
      "heading": "Erster Eindruck und Erwartungen",
      "paragraphs": ["Auspackeerlebnis und erste Eindrücke", "Erwartungen basierend auf Spezifikationen", "Kontext in dem Sie das Produkt testen werden"]
    },
    {
      "type": "pros-cons",
      "heading": "Vor- und Nachteile aus der Praxis",
      "pros": ["Konkreter Vorteil aus eigener Erfahrung", "Praktischer Pluspunkt", "Einzigartige Eigenschaft", "Positive Überraschung"],
      "cons": ["Ehrlicher Minuspunkt", "Praktische Einschränkung", "Verbesserungspunkt"]
    },
    {
      "type": "quote",
      "quote": "Eine auffallende Erkenntnis oder Kernwert der das Produkt definiert"
    },
    {
      "type": "text",
      "heading": "Leistung in der Praxis",
      "paragraphs": ["Konkrete Testergebnisse", "Vergleich mit Erwartungen", "Wie es im täglichen Gebrauch funktioniert"]
    },
    {
      "type": "text",
      "heading": "Für wen ist dies geeignet?",
      "paragraphs": ["Ideale Zielgruppe und Anwendungssituationen", "Wann würden Sie dies empfehlen", "Alternativen für andere Bedürfnisse"]
    },
    {
      "type": "steps",
      "heading": "Kaufentscheidung Schritt für Schritt",
      "items": [
        {"title": "Definieren Sie Ihre Bedürfnisse", "detail": "Welche Funktionen sind wirklich wichtig für Ihre Situation?"},
        {"title": "Vergleichen Sie Spezifikationen", "detail": "Worauf müssen Sie beim Vergleich von Modellen achten?"},
        {"title": "Erwägen Sie Alternativen", "detail": "Welche anderen Optionen passen zu Ihrem Budget und Anforderungen?"},
        {"title": "Treffen Sie die endgültige Wahl", "detail": "Finale Abwägung und wo Sie am besten kaufen können"}
      ]
    },
    {
      "type": "faq",
      "items": [
        {"q": "Wie lange hält dieses Produkt?", "a": "Erwartete Lebensdauer basierend auf Verarbeitungsqualität"},
        {"q": "Ist es für Anfänger geeignet?", "a": "Bedienungsfreundlichkeit und Lernkurve"},
        {"q": "Was unterscheidet es von Konkurrenten?", "a": "Einzigartige Vorteile gegenüber Alternativen"},
        {"q": "Worauf sollte man beim Kauf achten?", "a": "Praktische Kaufberatung"}
      ]
    },
    {
      "type": "conclusion",
      "heading": "Endurteil",
      "paragraphs": ["Zusammenfassung der Stärken und Schwächen", "Endempfehlung und praktische Beratung"]
    }
  ],
  "verdict": {
    "headline": "Unser Fazit",
    "buy_if": ["Kaufen wenn Sie Situation 1 haben", "Perfekt bei Bedarf 2"],
    "skip_if": ["Überspringen bei Situation 1", "Nicht geeignet wenn Sie Bedarf 2 haben"],
    "bottom_line": "Ein Satz der den Kern Ihrer Empfehlung zusammenfasst"
  }
}

Produktinformationen (Kontext, nicht wörtlich übernehmen):
- Titel: {$title}
- Beschreibung: {$description}
- Marke: {$brand}

TESTBERICHT FLOW CHECKLISTE:
✅ Erzählen Sie eine chronologische Geschichte: erster Eindruck → täglicher Gebrauch → Endurteil
✅ Jeder Abschnitt bezieht sich auf vorherige: "Wie bereits erwähnt...", "In der Praxis zeigte sich..."
✅ Konkret sein: "Nach 3 Wochen Nutzung", "Bei täglichen Aufgaben von 2 Stunden"
✅ Persönliche Erfahrung: "Was uns auffiel", "Unsere Erfahrung war"

SEO OPTIMIERUNG TESTBERICHTE:
✅ Titel mit Primary Keyword: "[Produkt] Test" oder "[Produkt] Testbericht"
✅ H2s beantworten direkte Fragen: "Ist [Produkt] sein Geld wert?"
✅ Featured Snippet ready: Beginnen Sie mit direkter JA/NEIN Antwort
✅ Long-Tail Keywords: "Probleme mit [Produkt]", "[Produkt] vs [Konkurrent]"
✅ LSI Keywords: "Erfahrung", "Empfehlung", "Beratung", "Vergleich"

🚫 LINKING REGELN (VERPFLICHTEND):
- Platzieren Sie NIEMALS Links in laufendem Text (paragraphs)
- Erwähnen Sie Produkte/Marken, aber NICHT verlinken
- Keine internen Links - CTA-Buttons im Template erledigen diese Arbeit
- Content ist reine Information - Navigation ist separat

QUALITÄTSANFORDERUNGEN:
- Jeder Absatz 120+ Wörter für SEO-Autorität
- Spezifische Details, keine vagen allgemeinen Bemerkungen
- Ehrliche Vor-/Nachteile basierend auf echten Nutzungssituationen
- Klare Zielgruppenempfehlungen
- Deutscher Ton: professionell aber persönlich
- ABSOLUT KEINE EMOJIS - dies ist seriöser Produkttest-Content

Geben Sie nur minified JSON zurück, nichts anderes.
PROMPT;

        $response = $this->chat([
            ['role' => 'system', 'content' => 'Geben Sie NUR minified JSON zurück. Kein Markdown, kein Kommentar.'],
            ['role' => 'user', 'content' => $prompt],
        ], $model, 0.4, 8000); // Niedrige Temperature für Konsistenz, hohe Tokens für vollständige deutsche Testberichte (8k für no truncation)

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
                'locale' => 'de-DE',
                'intro' => 'Bei der Generierung des Contents ist ein Fehler aufgetreten.',
                'sections' => [],
                'verdict' => ['headline' => 'Fazit', 'bottom_line' => 'Testbericht nicht verfügbar']
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
Sie sind ein deutscher Produktspezialist der objektive Produktanalysen für {$niche} Websites schreibt.
Geben Sie NUR minified JSON gemäß dem exakten Schema unten aus.

SCHREIBSTIL - WICHTIG:
- NIEMALS schreiben als ob Sie das Produkt physisch getestet haben
- Verwenden Sie NICHT: "ich habe getestet", "nach dem Auspacken", "in meiner Erfahrung", "ich bemerkte", "wir testeten"
- Verwenden Sie WOHL: "dieses Produkt bietet", "basierend auf den Spezifikationen", "Nutzer berichten", "die Features zeigen"
- Fokus auf objektive Analyse von Specs, Features und logischen Erwartungen
- Professionell und informativ, nicht persönlich

CONTENT FOKUS:
- Analysieren Sie Spezifikationen und was sie für den Nutzer bedeuten
- Besprechen Sie Features und ihre praktischen Anwendungen
- Vergleichen Sie mit Alternativen basierend auf Specs
- Geben Sie ehrliche Vor- und Nachteile basierend auf Produktinformationen
- Helfen Sie Konsumenten eine fundierte Entscheidung zu treffen

Produkt: "{$title}"
Marke: {$brand}
Kategorie: {$niche}
Produktinformationen: {$description}

Generieren Sie einen Testbericht gemäß diesem exakten JSON-Schema:

{
  "version": "review.v3",
  "locale": "de-DE",
  "intro": "Objektive Einleitung über was dieses Produkt bietet und warum es relevant ist (2-3 Sätze, KEINE persönliche Erfahrung)",
  "sections": [
    {
      "type": "text",
      "heading": "Was dieses Produkt bietet",
      "paragraphs": ["Was Sie laut Hersteller bekommen, wichtigste Features", "Mehrwert und einzigartige Eigenschaften"]
    },
    {
      "type": "text",
      "heading": "Spezifikationen und Features im Detail",
      "paragraphs": ["Technische Specs erklärt und was sie bedeuten", "Wie die Features funktionieren und was Sie damit machen können", "Vergleich mit ähnlichen Produkten"]
    },
    {
      "type": "pros-cons",
      "heading": "Stärken und Beachtungspunkte",
      "pros": ["Konkreter Vorteil basierend auf Specs", "Praktischer Pluspunkt", "Einzigartige Feature oder Eigenschaft", "Gutes Preis-Leistungs-Verhältnis"],
      "cons": ["Ehrlicher Beachtungspunkt oder Einschränkung", "Möglicher Nachteil für spezifische Nutzer", "Aspekt wo Konkurrenz besser abschneidet"]
    },
    {
      "type": "quote",
      "quote": "Kernpunkt der Analyse - was macht dieses Produkt besonders oder wichtig zu wissen"
    },
    {
      "type": "text",
      "heading": "Eignung und Anwendungen",
      "paragraphs": ["Für welche Nutzer und Situationen dieses Produkt am besten geeignet ist", "Wann dies die richtige Wahl ist und wann nicht"]
    },
    {
      "type": "text",
      "heading": "Erwartungen für die Nutzung",
      "paragraphs": ["Was Sie basierend auf den Specs und Features erwarten können", "Wie sich dies im täglichen Gebrauch zeigt", "Potentielle Vorteile und Einschränkungen in der Praxis"]
    },
    {
      "type": "steps",
      "heading": "Kaufüberlegung Schritt für Schritt",
      "items": [
        {"title": "Bestimmen Sie Ihre Bedürfnisse", "detail": "Welche Funktionen sind wichtig für Ihre Situation?"},
        {"title": "Vergleichen Sie Alternativen", "detail": "Wie verhält sich dies zu anderen Optionen in dieser Preisklasse?"},
        {"title": "Prüfen Sie die Spezifikationen", "detail": "Erfüllen die Specs Ihre Anforderungen?"},
        {"title": "Treffen Sie Ihre Wahl", "detail": "Ist dies die beste Option für Ihr Budget und Bedürfnisse?"}
      ]
    },
    {
      "type": "faq",
      "items": [
        {"q": "Für wen ist dieses Produkt geeignet?", "a": "Zielgruppe und ideale Nutzungssituationen"},
        {"q": "Was sind die wichtigsten Vorteile?", "a": "Kernvorteile basierend auf Features"},
        {"q": "Worauf sollte man achten?", "a": "Wichtige Beachtungspunkte beim Kauf"},
        {"q": "Wie verhält es sich zu Alternativen?", "a": "Positionierung gegenüber Konkurrenz"}
      ]
    },
    {
      "type": "conclusion",
      "heading": "Fazit",
      "paragraphs": ["Zusammenfassung der Analyse: Stärken und Beachtungspunkte", "Empfehlung: für wen ist dies eine gute Wahl?"]
    }
  ],
  "verdict": {
    "headline": "Unsere Bewertung",
    "buy_if": [
      "Kaufen wenn Sie [spezifisches Bedürfnis/Situation]",
      "Perfekt bei [spezifischer Anwendungsfall]"
    ],
    "skip_if": [
      "Überspringen wenn Sie [spezifische Situation wo dies nicht passt]",
      "Nicht geeignet wenn [spezifische Einschränkung]"
    ],
    "bottom_line": "Kernbotschaft: Wertversprechen in einem Satz"
  }
}

🚫 LINKING REGELN (VERPFLICHTEND):
- Platzieren Sie NIEMALS Links in laufendem Text (paragraphs)
- Erwähnen Sie Produkte/Marken, aber NICHT verlinken
- Keine internen Links - CTA-Buttons im Template erledigen diese Arbeit
- Content ist reine Information - Navigation ist separat

KRITISCHE REGELN:
1. Verwenden Sie NIEMALS persönliche Erfahrungen oder Test-Sprache
2. Basieren Sie alles auf Specs, Features und logischer Analyse
3. Bleiben Sie objektiv und informativ
4. Vor-/Nachteile müssen konkret und spezifisch sein
5. Sections müssen natürlich lesen, nicht roboterhaft
6. Gesamt 1200-1600 Wörter
7. Geben Sie NUR minified JSON zurück, kein Markdown

Generieren Sie jetzt das JSON:
PROMPT;

        $response = $this->chat([
            ['role' => 'system', 'content' => 'Sie sind ein professioneller Produktanalyst der objektive Produktanalysen schreibt.'],
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
        $prompt = "Sie sind ein SEO-Experte der Meta-Tags schreibt die Google AI Overview freundlich sind.

Schreiben Sie packende Meta-Tags die Konversion stimulieren:

META TITLE (exakt 60 Zeichen):
- Integrieren Sie Kern-Keyword + Marke + USP
- Verwenden Sie Separatoren (| oder •) für Struktur
- Enden Sie mit Aktions-CTA (\"Vergleichen\", \"Entdecken\", \"Ansehen\")
- Beispiele: \"Beste Philips Airfryer {aktuelles Jahr} | Modelle Vergleichen\" oder \"Samsung TV Angebot • Rabatte Entdecken\"

META DESCRIPTION (exakt 160 Zeichen):
- Beantworten Sie die Kernfrage des Suchers
- Fügen Sie USP + Vorteil + CTA hinzu
- Verwenden Sie Aktionswörter (\"entdecken\", \"vergleichen\", \"sparen\")
- Integrieren Sie Social Proof falls möglich
- Schließen Sie mit klarem Call-to-Action ab

Optimieren für Google AI Overview:
- Geben Sie direkte Antworten auf Suchfragen
- Verwenden Sie kontextuell reiche Informationen
- Integrieren Sie relevante Synonyme natürlich
- Fokus auf Nutzerintention und Vorteile

Produktdaten:
Titel: {$title}
Beschreibung: {$description}
Marke: {$brand}

Output exakt als JSON (ohne extra Text):
{
  \"meta_title\": \"...\",
  \"meta_description\": \"...\"
}";

        $response = $this->chat([
            ['role' => 'system', 'content' => 'Sie sind ein hilfreicher Assistent.'],
            ['role' => 'user', 'content' => $prompt],
        ], $model, 0.3, 200); // Sehr niedrige Temperature für konsistente Meta-Tags

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
Sie sind ein Produkt-Copywriter der Texte für Google AI Overview Optimierung schreibt.
Schreiben Sie einzigartige, faktisch korrekte, konversionsorientierte Produkttexte auf Deutsch.

Regeln:
- Übernehmen Sie keine Sätze wörtlich aus der gelieferten Quelle.
- Verwenden Sie semantisches HTML (kein inline CSS), geeignet für direkte Veröffentlichung.
- Keine Verweise auf bol.com oder externe Quellen.
- Keine irreführenden Behauptungen oder unbegründete Superlative.
- Ton: professionell, klar, hilfsbereit, konversionsorientiert.
- Schreiben Sie scannbar: kurze Absätze (max 3-4 Sätze), Bullet Points, klare Überschriften.
- SEO: verarbeiten Sie Fokus-Keywords aus Titel/Marke/Nische natürlich; kein Keyword-Stuffing.
- Länge: 600–900 Wörter für tiefgehenden, wertvollen Content.

Google AI Overview Optimierung:
- Beantworten Sie implizite Fragen der Sucher direkt und konkret.
- Verwenden Sie kontextuell reiche, faktisch korrekte Informationen.
- Integrieren Sie Frage-Antwort-Stil für bessere Auffindbarkeit.
- Fokus auf praktische Vorteile und konkrete Nutzungssituationen.
- Geben Sie direkte Antworten auf "warum", "wie" und "was" Fragen.
SYS;

        $user = <<<USR
Kontext:
- Produkttitel: {$title}
- Marke: {$brand}
- Nische: {$niche}
- Site/Absender: {$site}

Wichtigste Spezifikationen:
{$specLines}

Zusammenfassung der gelieferten Quelle (nur als Kontext, NICHT kopieren):
{$sourceSummary}

Aufgaben:
1) Schreiben Sie eine vollständige Produktbeschreibung von 600-900 Wörtern in sauberem semantischem HTML:

<section>
  <h2>Einleitung</h2>
  <p>Beantworten Sie direkt die Kernfrage: was macht dieses Produkt besonders? (2-3 Sätze)</p>

  <h2>Wichtigste Vorteile</h2>
  <p>Konkrete Vorteile mit praktischen Beispielen...</p>
  <ul>
    <li>Vorteil 1 mit messbarem Mehrwert</li>
    <li>Vorteil 2 mit Nutzungssituation</li>
    <li>Vorteil 3 mit konkretem Ergebnis</li>
  </ul>

  <h3>Für welche Zielgruppe?</h3>
  <p>Spezifische Nutzungssituationen und Zielgruppen...</p>

  <h2>Praktische Vorteile gegenüber Alternativen</h2>
  <p>Was unterscheidet dieses Produkt von Konkurrenten?</p>
  <ul>
    <li>Einzigartige Eigenschaft 1 vs. Alternative</li>
    <li>Praktischer Vorteil 2 im täglichen Gebrauch</li>
    <li>Mehrwert 3 auf lange Sicht</li>
  </ul>

  <h2>Nutzung & praktische Tipps</h2>
  <p>Konkrete Ratschläge für optimales Ergebnis...</p>
  <h3>Installation & Setup</h3>
  <p>Praktische Schritte für die Nutzung...</p>

  <h2>Spezifikationen in einfacher Sprache</h2>
  <ul>
    <li>Spezifikation 1: was bedeutet dies praktisch?</li>
    <li>Spezifikation 2: warum ist dies wichtig?</li>
  </ul>

  <h2>Häufig gestellte Fragen</h2>
  <h3>Wie lange hält dieses Produkt?</h3>
  <p>Konkrete Antwort mit erwarteter Lebensdauer.</p>
  <h3>Ist dies für [spezifische Anwendung] geeignet?</h3>
  <p>Direkte Antwort mit praktischer Erläuterung.</p>
  <h3>Was sind die wichtigsten Unterschiede zu [Alternative]?</h3>
  <p>Objektiver Vergleich mit Kernpunkten.</p>

  <h2>Fazit</h2>
  <p>Zusammenfassung Kernpunkte und Empfehlung...</p>
  <p><strong>Entdecken Sie alle Spezifikationen und vergleichen Sie Preise auf {$site}. Wählen Sie bewusst was perfekt zu Ihren Bedürfnissen passt!</strong></p>
</section>

2) Machen Sie es vollständig einzigartig. Umformulieren, interpretieren, erklären Sie in eigenen Worten.
3) Schreiben Sie Google AI Overview freundlich: beantworten Sie "warum", "wie" und "für wen" Fragen.
4) Integrieren Sie natürliche Synonyme von Kern-Keywords ohne Keyword-Stuffing.
5) Liefern Sie eine ultra-kurze Zusammenfassung (max. 35 Wörter) für Snippets.

Antworten Sie in JSON mit exakt diesen Keys:
{
  "html": "<section>...</section>",
  "summary": "kurze Zusammenfassung"
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
                .'<h2>Einleitung</h2>'
                .'<p>'.e(Str::limit(strip_tags($sourceSummary), 450)).'</p>'
                .'<h2>Fazit</h2>'
                ."<p>Sehen Sie alle Details und vergleichen Sie Modelle auf {$site}.</p>"
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
            ['role' => 'system', 'content' => 'Sie sind ein professioneller deutscher Textschreiber.'],
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
You are a senior product expert and content strategist writing IN GERMAN for {$siteName}, a trusted {$niche} specialist website. Output ONLY minified JSON per the schema. No markdown, no commentary.

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
- Target audience: Deutsche Konsumenten die nach {$primaryKeyword} suchen
- LANGUAGE: ALL CONTENT MUST BE IN GERMAN (Deutsch)

E-E-A-T REQUIREMENTS (hard) - USE GERMAN PHRASES:
- Experience: Include phrases like "Unsere Tests zeigen", "Nach Monaten der Nutzung", "Unserer Erfahrung nach"
- Expertise: Show deep product knowledge, mention specifications, explain technical aspects IN GERMAN
- Authoritativeness: Reference industry trends, compare multiple brands, cite common user issues IN GERMAN
- Trustworthiness: Be honest about limitations, mention both pros and cons, avoid overselling

CONTENT REQUIREMENTS (CRITICAL):
- Wordcount: MINIMUM 1200 words, target 1500-1800 words for comprehensive coverage
- Structure: Clear H1 (≤70 chars), exactly 5-6 detailed H2 sections (≤60 chars), H3 subsections where needed
- Each paragraph MUST be substantial (150+ words) with detailed expertise
- Write in-depth sections, not brief summaries - this is comprehensive expert content
- Tone: Professional but approachable, confident expertise without sales pressure
- SEO: Natural keyword integration, semantic variations, long-tail phrases
- LANGUAGE: WRITE EVERYTHING IN GERMAN (Deutsch)

TITLE REQUIREMENTS (CRITICAL) - GERMAN EXAMPLES:
- Write NATURAL, human-like German blog titles that people would actually search for
- Avoid product specification lists or technical jargon in titles
- Use conversational German language: "Welches [Produkt] passt zu Ihnen?", "Alles über [Keyword]", "[Keyword]: Kompletter Ratgeber"
- Examples of GOOD titles IN GERMAN: "Welches Laufband Passt Zu Ihrem Heimgym?", "Alles Über Elektrische Laufbänder", "Laufband Kaufen: Kompletter Ratgeber {$currentYear}"
- Examples of BAD titles: "Elektrisches Laufband mit Griff - Mit Fernbedienung - 1-10km/h Produktinfo und Reviews"
- Make it feel like content a human German expert would write, not a product listing
- INTERNAL LINKING (CRITICAL): Use EXACT URLs from context below:

{$internalLinkContext}

IMPORTANT: In internal_links sections, use the EXACT URL provided in parentheses as url_key, NOT generic routes like "produkte.index".
Example: If context shows "Digitale Airfryer XXL 10L (https://example.com/produkte/product-slug)", use "produkte/product-slug" as url_key.

BANNED PHRASES (never use):
- "AI-generated", "According to sources", "Research shows" (without specifics)
- Generic statements without expertise backing
- Overly promotional language

EXPERTISE LANGUAGE TO USE IN GERMAN:
- "In unseren umfangreichen Tests von [Produkt]..."
- "Nach Jahren Erfahrung in der {$niche} Branche haben wir herausgefunden..."
- "Kunden fragen uns oft über [specific issue]..."
- "Aus unserer Datenbank von [number]+ Produkttests geht hervor..."
- "Ein häufiges Problem das wir antreffen ist..."
- "Profis in der Branche wissen dass..."

Schema (BlogV3):

{
  "version": "blog.v3",
  "locale": "de-DE",
  "author": "{$siteName} Redaktion",
  "title": "",               // NATURAL German blog title ≤70 chars (NOT a product specification list!)
  "standfirst": "",          // 2-3 Sätze Eröffnung mit Expertise-Anspruch (IN GERMAN)
  "sections": [              // 4-6 sections for comprehensive coverage (ALL IN GERMAN)
    {
      "type": "text",
      "heading": "",         // H2 ≤60 chars (IN GERMAN)
      "subheadings": [""],   // H3 ≤50 chars (optional, IN GERMAN)
      "paragraphs": [""],    // Rich content >100 Wörter pro Absatz (IN GERMAN)
      "internal_links": [{"label": "", "url_key": "EXACT_URL_FROM_CONTEXT"}]
    }
  ],
  "closing": {
    "headline": "",          // H2 ≤60 chars (IN GERMAN)
    "summary": "",           // 2-3 Absätze mit konkreter Expertenberatung (IN GERMAN)
    "primary_cta": {"label": "", "url_key": "produkte.index|top5"}
  }
}

TASK:
Write a comprehensive {$suggestedAngle} about "{$primaryKeyword}" that showcases deep {$niche} expertise IN GERMAN. Include real-world insights, specific product knowledge, and practical advice that only an experienced {$niche} specialist would know. Make it clear this content comes from hands-on experience and industry knowledge, not generic research. ALL CONTENT MUST BE IN GERMAN (Deutsch).

CRITICAL: Create a NATURAL German blog title that sounds like something a human would write and search for. Think "Welches Laufband Wählen Sie {$currentYear}?" NOT "Elektrisches Laufband mit Griff - Mit Fernbedienung - 1-10km/h Produktinfo und Reviews".

Return only minified JSON, nothing else. ALL TEXT FIELDS IN GERMAN.
PROMPT;

        $response = $this->chat([
            ['role' => 'system', 'content' => 'You are an expert content writer with deep product knowledge. Write ALL content in GERMAN (Deutsch). Return ONLY minified JSON.'],
            ['role' => 'user', 'content' => $prompt],
        ], $model, 0.4, 8000); // Niedrige Temperature für Expertise-Konsistenz, hohe Tokens für E-E-A-T Tiefe (8k für no truncation)

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
            // Return enhanced fallback with expertise (IN GERMAN)
            return json_encode([
                'version' => 'blog.v3',
                'locale' => 'de-DE',
                'author' => $siteName . ' Redaktion',
                'title' => 'Experten-Ratgeber: ' . ucfirst($primaryKeyword),
                'standfirst' => "Aus unserer jahrelangen Erfahrung mit {$niche} Produkten haben wir diesen umfassenden Ratgeber zusammengestellt.",
                'sections' => [
                    [
                        'type' => 'text',
                        'heading' => 'Was Sie über ' . $primaryKeyword . ' wissen müssen',
                        'paragraphs' => [
                            "Als {$niche} Spezialisten haben wir umfangreiche Erfahrung mit {$primaryKeyword}. In diesem Ratgeber teilen wir unsere wichtigsten Erkenntnisse.",
                        ],
                        'internal_links' => [
                            ['label' => 'Alle ' . $niche . ' Produkte ansehen', 'url_key' => 'produkte.index']
                        ]
                    ]
                ],
                'closing' => [
                    'headline' => 'Unsere Experten-Empfehlung',
                    'summary' => 'Basierend auf unserer Erfahrung empfehlen wir gründlich zu vergleichen bevor Sie eine Wahl treffen.',
                    'primary_cta' => ['label' => 'Entdecken Sie unsere Empfehlungen', 'url_key' => 'top5']
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
     * Herkent DE-kopjes als "Einleitung", "Wichtigste Vorteile", "Für wen ist dieses Modell?",
     * "Nutzung & praktische Tipps", "Spezifikationen in einfacher Sprache", "Häufig gestellte Fragen", "Fazit", "CTA:".
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
            'einleitung' => 'h2',
            'wichtigste vorteile' => 'h2',
            'für wen ist dieses modell?' => 'h3',
            'nutzung & praktische tipps' => 'h2',
            'spezifikationen in einfacher sprache' => 'h2',
            'häufig gestellte fragen' => 'h2',
            'fazit' => 'h2',
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

    /**
     * Translate Dutch text to German using OpenAI
     *
     * @param string $dutchText The Dutch text to translate
     * @return string|null The German translation, or null on failure
     */
    public function translateToGerman(string $dutchText): ?string
    {
        if (empty($dutchText)) {
            return null;
        }

        $prompt = <<<PROMPT
Übersetzen Sie den folgenden niederländischen Text ins Deutsche. Behalten Sie die gleiche Struktur und denselben Ton bei.

WICHTIG:
- Natürliches Deutsch verwenden, keine maschinelle Übersetzung
- Produktbegriffe korrekt übersetzen (Dubbele Mand → Doppelter Korb, Heteluchtfriteuse → Heißluftfritteuse)
- Zahlen und Einheiten beibehalten
- Marken- und Modellnamen nicht übersetzen
- Nur den übersetzten Text zurückgeben, keine Erklärungen

Niederländischer Text:
{$dutchText}

Deutsche Übersetzung:
PROMPT;

        try {
            $response = $this->client->chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'Sie sind ein professioneller Übersetzer von Niederländisch nach Deutsch, spezialisiert auf E-Commerce-Produktbeschreibungen.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.3,
                'max_tokens' => 1000,
            ]);

            $translation = $response->choices[0]->message->content ?? null;

            return $translation ? trim($translation) : null;
        } catch (Throwable $e) {
            \Log::error('Translation to German failed: ' . $e->getMessage());
            return null;
        }
    }
}
