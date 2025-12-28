<?php

namespace App\Console\Commands\Bol;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class UpdateBolPrices extends Command
{
    protected $signature = 'app:update-bol-prices {--limit=50 : Maximum aantal producten om bij te werken}';

    protected $description = 'Update prijzen en ratings van bestaande producten (snelle sync)';

    public function handle()
    {
        $limit = (int) $this->option('limit');

        $this->info("Start price update voor maximaal {$limit} producten...");

        $token = $this->getAccessToken();
        if (! $token) {
            $this->error('Kon geen access token ophalen.');

            return;
        }

        // Haal bestaande producten op (oudste eerst bijwerken)
        $products = Product::whereNotNull('ean')
            ->orderBy('updated_at', 'asc')
            ->limit($limit)
            ->get();

        if ($products->isEmpty()) {
            $this->warn('Geen producten gevonden om bij te werken.');

            return;
        }

        $this->info("Gevonden {$products->count()} producten om bij te werken.");

        $updated = 0;
        $deleted = 0;
        $failed = 0;

        foreach ($products as $product) {
            $this->info("Prijs updaten voor: {$product->title} (EAN: {$product->ean})");

            $result = $this->updateProductPrice($product, $token);
            if ($result === 'deleted') {
                $deleted++;
                // Geen extra bericht - al getoond in updateProductPrice
            } elseif ($result === true) {
                $updated++;
                $this->info("✅ Prijs bijgewerkt voor {$product->title}");
            } else {
                $failed++;
                $this->warn("❌ Fout bij updaten van {$product->title}");
            }

            // Rate limiting: max 10/sec = 0.1 sec tussen calls (we gebruiken 0.15 sec voor veiligheid)
            usleep(150000); // 0.15 seconden
        }

        $this->info('Prijs update voltooid:');
        $this->info("✅ Bijgewerkt: {$updated}");
        $this->info("🗑️ Verwijderd: {$deleted}");
        $this->info("❌ Gefaald: {$failed}");

        // Update popularity scores na price update
        $this->newLine();
        $this->info('Popularity scores updaten...');
        $this->call('bol:update-popularity-scores');
    }

    protected function updateProductPrice(Product $product, string $token): bool|string
    {
        try {
            $response = Http::withHeaders([
                'Accept-Language' => 'nl',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$token,
            ])->get("https://api.bol.com/marketing/catalog/v1/products/{$product->ean}", [
                'country-code' => 'NL',
                'include-offer' => 'true',
                'include-rating' => 'true',
                'include-image' => 'true',
            ]);

            if (! $response->successful()) {
                $this->error("API fout voor EAN {$product->ean}: ".$response->status());

                return false;
            }

            $data = $response->json();

            $price = $data['offer']['price'] ?? null;
            $strikethroughPrice = $data['offer']['strikethroughPrice'] ?? null;
            $deliveryDescription = $data['offer']['deliveryDescription'] ?? null;

            // Haal rating average op
            $ratingAverage = is_array($data['rating'] ?? null)
                ? ($data['rating']['average'] ?? null)
                : ($data['rating'] ?? null);

            // Haal rating count op via aparte endpoint
            $ratingCount = null;
            $ratingResponse = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$token,
            ])->get("https://api.bol.com/marketing/catalog/v1/products/{$product->ean}/ratings");

            if ($ratingResponse->successful()) {
                $ratingData = $ratingResponse->json();
                $ratingAverage = $ratingData['averageRating'] ?? $ratingAverage;
                if (!empty($ratingData['ratings']) && is_array($ratingData['ratings'])) {
                    $ratingCount = collect($ratingData['ratings'])->sum('count');
                }
            }

            // Check beschikbaarheid
            if (empty($price) || (is_string($deliveryDescription) && str_contains(strtolower($deliveryDescription), 'niet beschikbaar'))) {
                $this->warn("Product EAN {$product->ean} is niet meer beschikbaar - wordt gemarkeerd als unavailable.");

                // Markeer als niet beschikbaar in plaats van verwijderen
                $product->update([
                    'is_available' => false,
                    'unavailable_since' => $product->unavailable_since ?? now(), // Behoud originele datum als al unavailable
                    'updated_at' => now(),
                ]);

                $this->info("✅ Product {$product->title} gemarkeerd als niet beschikbaar (URL behouden voor SEO).");

                return 'deleted'; // Return code blijft gelijk voor statistieken
            }

            // Product is weer beschikbaar - reset unavailable status
            if (!$product->is_available) {
                $this->info("Product {$product->title} is weer beschikbaar!");
                $product->update([
                    'is_available' => true,
                    'unavailable_since' => null,
                ]);
            }

            // Haal image_url op uit response
            $imageUrl = $product->image_url; // Behoud huidige als fallback
            if (!empty($data['image']['url'])) {
                $imageUrl = $data['image']['url'];
            }

            // Update prijs, rating en image_url velden
            $product->update([
                'price' => $price,
                'strikethrough_price' => $strikethroughPrice,
                'delivery_time' => $deliveryDescription,
                'rating_average' => $ratingAverage,
                'rating_count' => $ratingCount,
                'image_url' => $imageUrl,
                'updated_at' => now(),
            ]);

            return true;

        } catch (\Exception $e) {
            $this->error("Exception bij EAN {$product->ean}: ".$e->getMessage());

            return false;
        }
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
