<?php

namespace App\Console\Commands\Bol;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class UpdatePopularityScores extends Command
{
    protected $signature = 'bol:update-popularity-scores {categoryId?}';

    protected $description = 'Update popularity scores van producten op basis van Bol.com popular products API';

    public function handle()
    {
        $categoryId = $this->argument('categoryId') ?? env('BOL_CATEGORY_ID');

        if (!$categoryId) {
            $this->error('Geen category ID opgegeven en geen fallback gevonden in .env (BOL_CATEGORY_ID).');
            return Command::FAILURE;
        }

        $this->info("Popularity scores updaten voor categorie ID: $categoryId");

        $token = $this->getAccessToken();
        if (!$token) {
            $this->error('Kon geen access token ophalen.');
            return Command::FAILURE;
        }

        $this->info('Token succesvol opgehaald');

        // Reset alle scores eerst naar 0
        Product::query()->update(['popularity_score' => 0]);
        $this->info('Alle popularity scores gereset naar 0');

        $url = 'https://api.bol.com/marketing/catalog/v1/products/lists/popular';
        $pageSize = 50;
        $currentPage = 1;
        $totalProcessed = 0;
        $maxPages = 10; // Max 500 producten (10 pagina's x 50)

        $this->info("Popularity scores ophalen van Bol.com...");
        $bar = $this->output->createProgressBar($maxPages * $pageSize);

        while ($currentPage <= $maxPages) {
            $response = Http::withHeaders([
                'Accept-Language' => 'nl',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ])->get($url, [
                'category-id' => $categoryId,
                'country-code' => 'NL',
                'page-size' => $pageSize,
                'page' => $currentPage,
            ]);

            if ($response->status() === 404) {
                $this->newLine();
                $this->warn("Geen verdere pagina's beschikbaar vanaf pagina $currentPage");
                break;
            }

            if (!$response->successful()) {
                $this->newLine();
                $this->error('Fout bij ophalen populaire producten: ' . $response->status());
                break;
            }

            $data = $response->json();
            $results = $data['results'] ?? [];

            if (empty($results)) {
                $this->newLine();
                $this->warn("Geen producten meer op pagina $currentPage");
                break;
            }

            foreach ($results as $index => $productData) {
                $ean = $productData['ean'] ?? null;

                if (!$ean) {
                    $bar->advance();
                    continue;
                }

                // Bereken popularity score: hogere positie = hogere score
                // Eerste product op eerste pagina krijgt hoogste score
                $positionScore = (($maxPages - $currentPage + 1) * $pageSize) - $index;

                // Update product als het in onze database bestaat
                $updated = Product::where('ean', $ean)->update([
                    'popularity_score' => $positionScore
                ]);

                if ($updated) {
                    $totalProcessed++;
                }

                $bar->advance();
            }

            $currentPage++;
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Popularity scores bijgewerkt voor {$totalProcessed} producten");

        // Clear cache
        Cache::forget('homepage_top10');
        Cache::forget('top5_products');
        $this->info('Cache gecleared voor top 10 en top 5');

        return Command::SUCCESS;
    }

    private function getAccessToken(): ?string
    {
        $clientId = config('bol.client_id');
        $clientSecret = config('bol.client_secret');

        if (!$clientId || !$clientSecret) {
            $this->error('BOL_CLIENT_ID en BOL_CLIENT_SECRET moeten ingesteld zijn in .env');
            return null;
        }

        $response = Http::asForm()->post('https://login.bol.com/token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'client_credentials',
        ]);

        if (!$response->successful()) {
            $this->error('Fout bij ophalen token: ' . $response->status());
            return null;
        }

        return $response->json()['access_token'] ?? null;
    }
}
