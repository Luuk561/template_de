<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use OpenAI\Laravel\Facades\OpenAI;

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
            $prompt = "Verbeter de volgende producttekst voor een Duitse affiliate website. Herschrijf de beschrijving en bullet points zodat ze:
- Overtuigender en aantrekkelijker zijn voor klanten
- Dezelfde kwaliteit behouden maar beter geschreven zijn
- In het Duits blijven
- Alle belangrijke informatie behouden
- SEO-vriendelijk zijn

Originele titel: {$data['title']}
Originele beschrijving: {$data['description']}
Originele bullet points: " . implode("\n", $data['bullets'] ?? []) . "

Geef ALLEEN een JSON terug met deze structuur (geen extra tekst):
{
  \"improved_description\": \"...\",
  \"improved_bullets\": [\"...\", \"...\"]
}";

            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.7,
            ]);

            $improved = json_decode($response->choices[0]->message->content, true);

            if ($improved && isset($improved['improved_description'])) {
                $data['description'] = $improved['improved_description'];
                if (isset($improved['improved_bullets'])) {
                    $data['bullets'] = $improved['improved_bullets'];
                }
                Log::info("Content improved successfully");
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
