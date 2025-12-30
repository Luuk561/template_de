<?php

namespace App\Console\Commands\Bol;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductSpecification;
use App\Services\OpenAIService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FetchBolProduct extends Command
{
    protected $signature = 'app:fetch-bol-product {eans*}';

    protected $description = 'Hole mehrere Produkte von bol.com über API (temporär für Demo, wird zu Amazon.de)';

    protected OpenAIService $openAI;

    public function __construct(OpenAIService $openAI)
    {
        parent::__construct();
        $this->openAI = $openAI;
    }

    public function handle()
    {
        $eans = $this->argument('eans');

        if (empty($eans)) {
            $this->error('Geen EANs opgegeven. Gebruik: php artisan app:fetch-bol-product {ean1} {ean2} ...');

            return;
        }

        foreach ($eans as $ean) {
            $this->info("Product ophalen met EAN: $ean");
            $this->fetchAndStoreProduct($ean);
            usleep(150000); // 0.15 seconden
        }

        $this->info('Klaar met ophalen van alle producten.');
    }

    protected function fetchAndStoreProduct(string $ean)
    {
        $token = $this->getAccessToken();
        if (! $token) {
            $this->error('Kon geen access token ophalen.');

            return;
        }

        $response = Http::withHeaders([
            'Accept-Language' => 'nl',
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ])->get("https://api.bol.com/marketing/catalog/v1/products/{$ean}", [
            'country-code' => 'NL',
            'include-offer' => 'true',
            'include-image' => 'true',
            'include-rating' => 'true',
            'include-specifications' => 'true',
        ]);

        if (! $response->successful()) {
            $this->error("Fout bij ophalen product EAN {$ean}: ".$response->status());

            return;
        }

        $productData = $response->json();

        $price = $productData['offer']['price'] ?? null;
        $strikethroughPrice = $productData['offer']['strikethroughPrice'] ?? null;
        $deliveryDescription = $productData['offer']['deliveryDescription'] ?? null;

        if (empty($price) || (is_string($deliveryDescription) && str_contains(strtolower($deliveryDescription), 'niet beschikbaar'))) {
            $this->warn("Product EAN {$ean} is niet beschikbaar of heeft geen prijs. Wordt niet opgeslagen.");

            return;
        }

        $ratingAverage = is_array($productData['rating'] ?? null)
            ? $productData['rating']['average'] ?? null
            : $productData['rating'] ?? null;

        $categorySegment = null;
        $categoryChunk = null;
        if (! empty($productData['gpc']) && is_array($productData['gpc'])) {
            foreach ($productData['gpc'] as $category) {
                if (($category['level'] ?? '') === 'SEGMENT') {
                    $categorySegment = $category['name'] ?? null;
                }
                if (($category['level'] ?? '') === 'CHUNK') {
                    $categoryChunk = $category['name'] ?? null;
                }
            }
        }

        $brand = null;
        if (! empty($productData['specificationGroups']) && is_array($productData['specificationGroups'])) {
            foreach ($productData['specificationGroups'] as $group) {
                if (isset($group['title']) && strtolower($group['title']) === 'artikelinformatie' && ! empty($group['specifications'])) {
                    foreach ($group['specifications'] as $spec) {
                        if ((isset($spec['key']) && strtolower($spec['key']) === 'brand') || (isset($spec['name']) && strtolower($spec['name']) === 'merk')) {
                            $brand = $spec['values'][0] ?? null;
                            break 2;
                        }
                    }
                }
            }
        }

        if (empty($brand)) {
            $brand = $productData['brand'] ?? $productData['manufacturer'] ?? null;
        }

        $rawTitle = trim($productData['title'] ?? '');
        if (empty($brand) && ! empty($rawTitle)) {
            $brand = explode(' ', $rawTitle)[0];
            $this->info("Fallback merk uit titel: {$brand} (EAN: {$ean})");
        }

        // Translate Dutch title to German
        $germanTitle = $this->openAI->translateToGerman($rawTitle);
        if ($germanTitle) {
            $this->info("Titel vertaald: {$rawTitle} → {$germanTitle}");
            $rawTitle = $germanTitle;
        }

        $siteNiche = getSetting('site_niche', '');
        $nicheFilters = config("nichefilters.{$siteNiche}.filters", []);
        $titleCheck = strtolower($rawTitle);
        $descriptionCheck = strtolower($productData['description'] ?? '');
        $combinedText = $titleCheck.' '.$descriptionCheck;

        if (! empty($nicheFilters) && ! Str::contains($combinedText, $nicheFilters)) {
            $this->warn("Product EAN {$ean} past niet bij de nichefilter voor '{$siteNiche}' — overgeslagen.");

            return;
        }

        // Generate meta tags via templates (sneller dan OpenAI) - GERMAN
        $siteName = getSetting('site_name', config('app.name'));

        // Voorkom dubbele merknaam in titel
        $titleText = $brand && str_starts_with(strtolower($rawTitle), strtolower($brand)) ? $rawTitle : ($brand ? "$brand $rawTitle" : $rawTitle);
        $metaTitle = Str::limit("$titleText | Vergleichen & Sparen", 60, '');

        // Korte product naam voor description (GERMAN)
        $shortTitle = Str::limit($rawTitle, 80, '');
        $ratingText = $ratingAverage ? " ★" . number_format($ratingAverage, 1) : '';
        $priceText = $price ? " €" . number_format($price, 0, ',', '.') : '';
        $metaDescription = Str::limit("Entdecken Sie $shortTitle.$ratingText$priceText. Vergleichen Sie Spezifikationen und sehen Sie Testberichte. Wählen Sie bewusst auf $siteName!", 160, '');

        $imageUrls = collect();

        try {
            $mediaResponse = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$token,
            ])->get("https://api.bol.com/marketing/catalog/v1/products/{$ean}/media");

            if ($mediaResponse->successful()) {
                $mediaData = $mediaResponse->json();
                foreach ($mediaData['images'] ?? [] as $image) {
                    $selected = collect($image['renditions'] ?? [])
                        ->sortByDesc('width')
                        ->firstWhere('width', '>=', 550);

                    if (! $selected && ! empty($image['renditions'])) {
                        $selected = collect($image['renditions'])->sortByDesc('width')->first();
                    }

                    if ($selected && ! empty($selected['url']) && ! $imageUrls->contains($selected['url'])) {
                        $imageUrls->push($selected['url']);
                    }

                    if ($imageUrls->count() >= 3) {
                        break;
                    }
                }
            } else {
                $this->warn('Media kon niet worden opgehaald: '.$mediaResponse->status());
            }
        } catch (\Exception $e) {
            $this->error("Fout bij ophalen media voor EAN {$ean}: ".$e->getMessage());
        }

        if ($imageUrls->isEmpty() && ! empty($productData['image']['url'])) {
            $imageUrls->push($productData['image']['url']);
        }

        if ($imageUrls->isEmpty()) {
            $this->warn('Geen afbeeldingen gevonden.');
        }

        if (empty($rawTitle)) {
            $slug = 'product-'.uniqid();
        } else {
            $words = preg_split('/\s+/', $rawTitle);
            $shortTitle = implode(' ', array_slice($words, 0, 8));
            $baseSlug = Str::slug($shortTitle);

            $slug = $baseSlug;
            $counter = 1;
            while (Product::where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$counter;
                $counter++;
            }
        }

        /**
         * -----------------------------------------------------------
         * NIEUW: bronbeschrijving vastleggen + AI rewrite genereren
         * -----------------------------------------------------------
         */

        // 1) Bronbeschrijving opschonen (verwijder trailing specs/kenmerken blokken)
        $sourceDescriptionRaw = $productData['description'] ?? '';
        $sourceDescriptionClean = preg_replace(
            '/(Productspecificaties|Technische specificaties|Specificaties|Kenmerken)\s*:?.*$/is',
            '',
            $sourceDescriptionRaw
        );

        // 2) Top-specificaties samenvatten voor de prompt (max 12)
        $specPairs = [];
        foreach ($productData['specificationGroups'] ?? [] as $group) {
            foreach ($group['specifications'] ?? [] as $spec) {
                $name = $spec['name'] ?? $spec['key'] ?? null;
                $val = $spec['values'][0] ?? null;
                if ($name && $val && count($specPairs) < 12) {
                    $specPairs[$name] = is_array($val)
                        ? json_encode($val, JSON_UNESCAPED_UNICODE)
                        : (string) $val;
                }
            }
        }

        // 3) Unieke HTML + korte samenvatting via OpenAI (goedkoop model in service)
        $rewrite = $this->openAI->rewriteProductDescription([
            'title' => $rawTitle,
            'brand' => $brand,
            'niche' => $siteNiche,
            'source_description' => $sourceDescriptionClean,
            'specs' => $specPairs,
            'site_name' => getSetting('site_name', config('app.name')),
        ]);

        $aiHtml = $rewrite['html'] ?? null;
        $aiSummary = $rewrite['summary'] ?? null;
        $aiModel = $rewrite['model'] ?? null;

        // 4) Opslaan (inclusief bron + AI velden). 'description' = korte snippet fallback
        $product = Product::updateOrCreate(
            ['ean' => $productData['ean']],
            [
                'title' => $rawTitle,
                'slug' => $slug,
                'category_segment' => $categorySegment,
                'category_chunk' => $categoryChunk,

                // ✳️ Bewaar bron en AI-uitvoer
                'source_description' => $sourceDescriptionClean,
                'ai_description_html' => $aiHtml,
                'ai_summary' => $aiSummary,
                'rewritten_at' => $aiHtml ? now() : null,
                'rewrite_model' => $aiModel,
                'rewrite_version' => 'v1',

                // Korte plain-text omschrijving voor meta/snippets
                'description' => strip_tags($aiSummary ?: Str::limit(strip_tags($sourceDescriptionClean), 150)),

                'url' => $productData['url'] ?? null,
                'price' => $price,
                'strikethrough_price' => $strikethroughPrice,
                'delivery_time' => $deliveryDescription,
                'image_url' => $imageUrls->first(),
                'rating_average' => $ratingAverage,
                'brand' => $brand,
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
            ]
        );

        $this->info("Product {$rawTitle} opgeslagen in database met ID: ".$product->id);

        // Afbeeldingen opnieuw opslaan
        $product->images()->delete();
        foreach ($imageUrls as $index => $url) {
            ProductImage::create([
                'product_id' => $product->id,
                'image_url' => $url,
                'sort_order' => $index + 1,
            ]);
        }
        $this->info('Afbeeldingen opgeslagen (maximaal 3) in product_images tabel.');

        // Specificaties opnieuw opslaan
        $product->specifications()->delete();
        foreach ($productData['specificationGroups'] ?? [] as $group) {
            $groupTitle = $group['title'] ?? null;

            foreach ($group['specifications'] ?? [] as $spec) {
                $name = $spec['name'] ?? $spec['key'] ?? null;

                $value = null;
                if (! empty($spec['values'])) {
                    $first = $spec['values'][0];

                    // Als het een array is (soms bij multivalue / nested info), zet om naar JSON
                    if (is_array($first)) {
                        $value = json_encode($first, JSON_UNESCAPED_UNICODE);
                    } else {
                        $value = $first;
                    }
                }

                if ($name && $value) {
                    ProductSpecification::create([
                        'product_id' => $product->id,
                        'group' => $groupTitle,
                        'name' => $name,
                        'value' => $value,
                    ]);
                }
            }
        }

        $this->info("Specificaties opgeslagen voor product ID: {$product->id}");
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
