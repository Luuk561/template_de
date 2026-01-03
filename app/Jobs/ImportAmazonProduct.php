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

SEO-ANFORDERUNGEN:
- SEO Title: Max 60 Zeichen, enthält Hauptkeyword + wichtigste USPs
- Meta Description: 150-155 Zeichen, überzeugend, Call-to-Action
- Slug: Kurz, prägnant, max 5-6 Wörter, nur wichtigste Keywords (z.B. 'merach-laufband-walking-pad')

BESCHREIBUNGS-ANFORDERUNGEN:
- 2-3 prägnante Absätze (getrennt durch \\n\\n)
- Erster Absatz: Einführung und Hauptmerkmale
- Zweiter Absatz: Technische Details und Funktionen
- Dritter Absatz (optional): Fazit und Kaufanreiz
- KEINE Überschriften in der Beschreibung
- Professionell, überzeugend, alle wichtigen Infos

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
  \"improved_description\": \"Professionelle deutsche Produktbeschreibung in 2-3 Absätzen (getrennt durch \\\\n\\\\n)\",
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
