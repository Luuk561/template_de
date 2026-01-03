<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use OpenAI;

class ImportAmazonProduct implements ShouldQueue
{
    use Queueable;

    public $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $siteUrl,
        public array $productData
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Starting import for {$this->productData['asin']} to {$this->siteUrl}");

        // Step 1: Improve content with OpenAI
        $improvedData = $this->improveWithOpenAI($this->productData);

        // Step 2: Save to temp file
        $tempFile = storage_path('app/import-' . $this->productData['asin'] . '.json');
        file_put_contents($tempFile, json_encode($improvedData, JSON_PRETTY_PRINT));

        // Step 3: Extract site path from URL
        $sitePath = $this->extractSitePath($this->siteUrl);

        // Step 4: Upload to server
        $sshServer = 'forge@46.224.108.150';
        $remotePath = "/home/forge/{$sitePath}";
        $remoteFile = "{$remotePath}/storage/app/product-import.json";

        $uploadResult = Process::run("scp {$tempFile} {$sshServer}:{$remoteFile}");

        if (!$uploadResult->successful()) {
            Log::error("Failed to upload to {$this->siteUrl}: " . $uploadResult->errorOutput());
            throw new \Exception("Upload failed: " . $uploadResult->errorOutput());
        }

        // Step 5: Execute import on remote server
        $importResult = Process::timeout(120)->run(
            "ssh {$sshServer} \"cd {$remotePath} && php artisan product:import-json --file=storage/app/product-import.json\""
        );

        if (!$importResult->successful()) {
            Log::error("Import failed on {$this->siteUrl}: " . $importResult->errorOutput());
            throw new \Exception("Import failed: " . $importResult->errorOutput());
        }

        // Step 6: Cleanup
        @unlink($tempFile);
        Process::run("ssh {$sshServer} \"rm {$remoteFile}\"");

        Log::info("Successfully imported {$this->productData['asin']} to {$this->siteUrl}");
    }

    protected function improveWithOpenAI(array $data): array
    {
        Log::info("Improving content with OpenAI for {$data['asin']}");

        try {
            $prompt = "Du bist ein professioneller SEO-Texter für deutsche Affiliate-Websites. Optimiere das folgende Produkt für Suchmaschinen und Conversions.

KRITISCHE REGELN:
- Schreibe AUSSCHLIESSLICH auf Deutsch (keine englischen, spanischen oder anderen Sprachen!)
- Entferne ALLE nicht-deutschen Texte komplett (z.B. \"Servicio al Cliente\" = LÖSCHEN!)
- Korrigiere alle Rechtschreibfehler und Grammatik
- Nutze korrekte deutsche Groß-/Kleinschreibung
- Keine fancy Unicode-Zeichen (𝙀𝙛𝙛𝙞𝙯𝙞𝙚𝙣𝙩) - nur normales Deutsch
- Behalte alle technischen Daten (Maße, Gewicht, Leistung, etc.)
- WICHTIG: Nutze NUR Informationen aus den gegebenen Produktdaten (Titel, Beschreibung, Bullet Points)
- ERFINDE KEINE neuen Funktionen oder Spezifikationen
- HERSCHREIBE die vorhandenen Informationen in besserer Struktur und Sprache

SEO-ANFORDERUNGEN:
- SEO Title: Max 60 Zeichen, enthält Hauptkeyword + wichtigste USPs
- Meta Description: 150-155 Zeichen, überzeugend, Call-to-Action
- Slug: Kurz, prägnant, max 5-6 Wörter, nur wichtigste Keywords (z.B. 'merach-laufband-walking-pad')

BESCHREIBUNGS-ANFORDERUNGEN:
- Schreibe eine ausführliche, strukturierte Produktbeschreibung mit 6-8 Absätzen
- Nutze H2-Überschriften für SEO (3-4 Überschriften maximal, strategisch platziert)
- H2 Format: <h2>Überschrift hier</h2> (KEIN Markdown ##)
- Überschriften sollten Keywords enthalten (z.B. \"Vorteile des [Produktname]\", \"Für wen ist das [Produkttyp] geeignet?\")
- Struktur mit SEO-Überschriften:
  1. Einführungsabsatz (OHNE Überschrift): Was ist das Produkt?
  2. <h2>Wichtigste Funktionen und Vorteile</h2> + 2 Absätze über Features
  3. <h2>Für wen ist das [Produkttyp] geeignet?</h2> + 1 Absatz über Zielgruppe
  4. 1-2 Absätze über technische Details (OHNE Überschrift)
  5. <h2>Praktische Tipps zur Verwendung</h2> + 1 Absatz (optional, nur wenn sinnvoll)
  6. Fazit-Absatz (OHNE Überschrift): Kaufempfehlung
- Jeder Absatz 3-5 Sätze
- Absätze durch \\n\\n trennen, H2 tags direkt in den Text integrieren
- Professionell, überzeugend, keyword-optimiert

PRODUKTINFORMATIONEN:
Marke: {$data['brand']}
Titel: {$data['title']}
Beschreibung: {$data['description']}
Bullet Points: " . implode("\n", $data['bullets'] ?? []) . "

ANTWORTFORMAT (NUR JSON, kein anderer Text):
{
  \"seo_title\": \"Kurzer prägnanter Titel max 60 chars\",
  \"meta_description\": \"Überzeugende Beschreibung 150-155 chars mit Call-to-Action\",
  \"slug\": \"kurzer-seo-optimierter-slug\",
  \"improved_description\": \"Ausführliche strukturierte Produktbeschreibung mit 6-8 Absätzen (getrennt durch \\\\n\\\\n). Jeder Absatz 3-5 Sätze. KEINE Überschriften.\",
  \"improved_bullets\": [\"Bullet 1\", \"Bullet 2\", \"Bullet 3\"],
  \"pros\": [\"Vorteil 1\", \"Vorteil 2\", \"Vorteil 3\"],
  \"cons\": [\"Nachteil 1\", \"Nachteil 2\"]
}";

            $client = OpenAI::client(config('openai.api_key'));

            $response = $client->chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.7,
            ]);

            $improved = json_decode($response->choices[0]->message->content, true);

            if ($improved && isset($improved['improved_description'])) {
                // Update content
                $data['description'] = $improved['improved_description'];

                if (isset($improved['improved_bullets'])) {
                    $data['bullets'] = $improved['improved_bullets'];
                }

                // Update SEO fields
                if (isset($improved['seo_title'])) {
                    $data['seo_title'] = $improved['seo_title'];
                }

                if (isset($improved['meta_description'])) {
                    $data['meta_description'] = $improved['meta_description'];
                }

                if (isset($improved['slug'])) {
                    $data['slug'] = $improved['slug'];
                }

                // Store pros/cons
                if (isset($improved['pros'])) {
                    $data['pros'] = $improved['pros'];
                }

                if (isset($improved['cons'])) {
                    $data['cons'] = $improved['cons'];
                }

                Log::info("Content and SEO improved successfully");
            } else {
                Log::warning("OpenAI returned invalid format, using original content");
            }

        } catch (\Exception $e) {
            Log::error("OpenAI improvement failed: " . $e->getMessage());
            // Continue with original content
        }

        return $data;
    }

    protected function extractSitePath(string $url): string
    {
        // Extract domain from URL
        // e.g., "bestelaufband.de" or "https://bestelaufband.de" -> "bestelaufband.de"
        $url = str_replace(['http://', 'https://'], '', $url);
        $url = rtrim($url, '/');

        return $url;
    }
}
