<?php

namespace App\Console\Commands\Bol;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FetchBolCategoryProducts extends Command
{
    protected $signature = 'app:fetch-bol-category-products {categoryId?} {--limit=50 : Maximum aantal producten om op te halen}';

    protected $description = 'Haal populaire producten op uit een categorie via de Bol API';

    public function handle()
    {
        $categoryId = $this->argument('categoryId') ?? env('BOL_CATEGORY_ID');

        if (! $categoryId) {
            $this->error('❌ Geen category ID opgegeven en geen fallback gevonden in .env (BOL_CATEGORY_ID).');

            return;
        }

        $this->info("Populaire producten ophalen uit categorie ID: $categoryId");

        $token = $this->getAccessToken();
        if (! $token) {
            $this->error('Kon geen token ophalen.');

            return;
        }
        $this->info('Token succesvol opgehaald!');

        $siteNiche = getSetting('site_niche');
        $nicheFilters = config("nichefilters.{$siteNiche}.filters", []);

        // Haal bestaande EANs op om duplicaten te voorkomen
        $existingEans = Product::pluck('ean')->toArray();
        $this->info('Aantal bestaande producten in database: '.count($existingEans));

        $url = 'https://api.bol.com/marketing/catalog/v1/products/lists/popular';

        $pageSize = 50;
        $currentPage = 1;
        $filteredEans = [];
        $skippedExisting = 0;
        $maxAantal = (int) $this->option('limit');

        $this->info("📊 Target: {$maxAantal} nieuwe producten ophalen (max beschikbaar indien aanwezig)");

        while (count($filteredEans) < $maxAantal) {
            $response = Http::withHeaders([
                'Accept-Language' => 'nl',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$token,
            ])->get($url, [
                'category-id' => $categoryId,
                'country-code' => 'NL',
                'page-size' => $pageSize,
                'page' => $currentPage,
                'include-offer' => 'true',
                'include-image' => 'true',
                'include-rating' => 'true',
            ]);

            if ($response->status() === 404) {
                $this->warn("📭 Geen verdere pagina's beschikbaar vanaf pagina $currentPage (404 van Bol).");
                break;
            }

            if (! $response->successful()) {
                $this->error('❌ Fout bij ophalen populaire producten: '.$response->status());
                break;
            }

            $data = $response->json();
            $results = $data['results'] ?? [];

            if (empty($results)) {
                $this->warn("Geen producten meer op pagina $currentPage.");
                break;
            }

            foreach ($results as $product) {
                $title = strtolower($product['title'] ?? '');
                $description = strtolower($product['description'] ?? '');

                // Filter: Skip producten onder minimum prijs (standaard €25, instelbaar via setting)
                $minPrice = getSetting('min_product_price', 25);
                $price = $product['offer']['price'] ?? null;
                if ($price !== null && $price < $minPrice) {
                    $this->warn("💰 Overgeslagen (prijs < €{$minPrice}): '{$product['title']}' - €{$price}");
                    continue;
                }

                $match = empty($nicheFilters) || collect($nicheFilters)->contains(function ($filter) use ($title, $description) {
                    return str_contains($title, $filter) || str_contains($description, $filter);
                });

                if (! $match) {
                    $this->warn("🔸 Overgeslagen: '{$product['title']}' past niet bij '{$siteNiche}'");

                    continue;
                }

                $ean = $product['ean'] ?? null;
                if ($ean) {
                    // Skip als product al bestaat
                    if (in_array($ean, $existingEans)) {
                        $skippedExisting++;
                        $this->info("⏭️  Overgeslagen (bestaat al): EAN $ean - {$product['title']}");

                        continue;
                    }

                    // Skip als al in deze batch
                    if (! in_array($ean, $filteredEans)) {
                        $filteredEans[] = $ean;
                        $this->info("✅ Nieuw product geselecteerd: EAN $ean");

                        if (count($filteredEans) >= $maxAantal) {
                            break;
                        }
                    }
                }
            }

            $this->info("Pagina $currentPage verwerkt.");
            $currentPage++;
        }

        $this->info('=== SAMENVATTING ===');
        $this->info('Aantal nieuwe producten gevonden: '.count($filteredEans));
        $this->info('Aantal bestaande producten overgeslagen: '.$skippedExisting);

        if (empty($filteredEans)) {
            $this->warn('❌ Geen nieuwe producten gevonden. Alle geschikte producten bestaan al.');

            return;
        }

        $this->info('Start met ophalen en opslaan van producten via FetchBolProduct command...');

        $chunks = array_chunk($filteredEans, 10);
        foreach ($chunks as $chunk) {
            Artisan::call('app:fetch-bol-product', [
                'eans' => $chunk,
            ]);
            $this->info('Chunk met 10 producten verwerkt...');
        }

        $this->info('Alle geschikte producten opgehaald en opgeslagen.');
    }

    private function getAccessToken()
    {
        if (Cache::has('bol_access_token')) {
            return Cache::get('bol_access_token');
        }

        $clientId = config('services.bol.client_id');
        $clientSecret = config('services.bol.client_secret');

        $response = Http::withBasicAuth($clientId, $clientSecret)
            ->asForm()
            ->post('https://login.bol.com/token?grant_type=client_credentials');

        if ($response->successful()) {
            $token = $response->json()['access_token'];
            $expiresIn = $response->json()['expires_in'] ?? 599;
            Cache::put('bol_access_token', $token, $expiresIn - 10);
            $this->info("Nieuw token opgehaald en gecached voor {$expiresIn} seconden.");

            return $token;
        }

        $this->error('Fout bij ophalen access token: '.$response->status());

        return null;
    }
}
