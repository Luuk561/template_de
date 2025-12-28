<?php

namespace App\Console\Commands\Traits;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

trait FetchBolProductTrait
{
    public function fetchAndStoreProduct(string $ean)
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
        ]);

        if (! $response->successful()) {
            $this->error('Fout bij ophalen product: '.$response->status());

            return;
        }

        $productData = $response->json();

        // Haal de prijs, rating, categorieën, afbeeldingen etc. op
        $price = $productData['offer']['price'] ?? null;
        $strikethroughPrice = $productData['offer']['strikethroughPrice'] ?? null;
        $deliveryDescription = $productData['offer']['deliveryDescription'] ?? null;

        if (empty($price) || (is_string($deliveryDescription) && str_contains(strtolower($deliveryDescription), 'niet beschikbaar'))) {
            $this->warn("Product $ean is niet beschikbaar of heeft geen prijs. Wordt niet opgeslagen.");

            return;
        }

        // Rating
        if (is_array($productData['rating'] ?? null)) {
            $ratingAverage = $productData['rating']['average'] ?? null;
            $ratingCount = $productData['rating']['count'] ?? null;
        } else {
            $ratingAverage = $productData['rating'] ?? null;
            $ratingCount = null;
        }

        // Haal detailed rating op van aparte endpoint (let op: /ratings met 's')
        $ratingResponse = Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ])->get("https://api.bol.com/marketing/catalog/v1/products/{$ean}/ratings");

        if ($ratingResponse->successful()) {
            $ratingData = $ratingResponse->json();
            $ratingAverage = $ratingData['averageRating'] ?? $ratingAverage;

            // Tel alle ratings op uit de distributie array
            if (!empty($ratingData['ratings']) && is_array($ratingData['ratings'])) {
                $ratingCount = collect($ratingData['ratings'])->sum('count');
            }
        }

        // Categorieën
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

        // Media ophalen
        $mediaResponse = Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ])->get("https://api.bol.com/marketing/catalog/v1/products/{$ean}/media");

        $imageUrls = collect();

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
        }

        if ($imageUrls->isEmpty() && ! empty($productData['image']['url'])) {
            $imageUrls->push($productData['image']['url']);
        }

        // Generate optimized title using OpenAI
        $rawTitle = trim($productData['title'] ?? '');
        $optimizedTitle = $this->generateOptimizedTitle(
            $rawTitle,
            $productData['description'] ?? '',
            $productData['brand'] ?? ''
        );

        // Slug genereren
        $titleForSlug = $optimizedTitle ?: $rawTitle;

        if (empty($titleForSlug)) {
            $slug = 'product-'.uniqid();
        } else {
            $words = preg_split('/\s+/', $titleForSlug);
            $shortTitle = implode(' ', array_slice($words, 0, 8));
            $baseSlug = Str::slug($shortTitle);

            $slug = $baseSlug;
            $counter = 1;

            while (Product::where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$counter;
                $counter++;
            }
        }

        // Opslaan/updaten product
        $product = Product::updateOrCreate(
            ['ean' => $productData['ean']],
            [
                'title' => $optimizedTitle ?: $rawTitle,
                'slug' => $slug,
                'category_segment' => $categorySegment,
                'category_chunk' => $categoryChunk,
                'description' => $productData['description'],
                'url' => $productData['url'] ?? null,
                'price' => $price,
                'strikethrough_price' => $strikethroughPrice,
                'delivery_time' => $deliveryDescription,
                'image_url' => $imageUrls->first(),
                'rating_average' => $ratingAverage,
                'rating_count' => $ratingCount,
            ]
        );

        $this->info('Product opgeslagen in database met ID: '.$product->id);

        // Afbeeldingen opslaan
        $product->images()->delete();
        foreach ($imageUrls as $index => $url) {
            ProductImage::create([
                'product_id' => $product->id,
                'image_url' => $url,
                'sort_order' => $index + 1,
            ]);
        }

        $this->info('Afbeeldingen opgeslagen (maximaal 3) in product_images tabel.');
    }

    private function generateOptimizedTitle($originalTitle, $description, $brand)
    {
        try {
            $client = \OpenAI::client(config('services.openai.key'));

            $description = strip_tags($description);
            $description = substr($description, 0, 500);

            $prompt = <<<PROMPT
Maak een perfecte producttitel voor dit product, zoals je ziet bij professionele webshops (bijv. "Samsung Galaxy Watch7 Smartwatch 40mm Cream").

Merk: {$brand}
Originele titel: {$originalTitle}
Beschrijving: {$description}

REGELS:
1. Begin ALTIJD met het merk
2. Daarna het producttype (bijv. "Loopband", "Slowcooker", "Smartwatch")
3. Daarna de belangrijkste specs (model, capaciteit, kleur, etc)
4. Maximaal 60 karakters
5. Geen rare hoofdletters - alleen eerste letter van woorden
6. GEEN "(EAN: ...)" toevoegen
7. Natuurlijk leesbaar, niet een opsomming
8. Nederlands, tenzij merknaam/modelnaam Engels is

Voorbeelden van GOEDE titles:
- "Samsung Galaxy Watch7 Smartwatch 40mm Cream"
- "Garmin Forerunner 55 Sporthorloge 42mm Zwart"
- "CrockPot Express Pot Multicooker 5,6L RVS"
- "Infinity Loopband 12km/u Inklapbaar met Helling"

Voorbeelden van SLECHTE titles:
- "Loopband (EAN: 123456)" (geen merk, geen specs)
- "CITYSPORTS APP en 360° tablethouder" (begint niet met producttype)
- "12 Programma's Extra Krachtige 2.0 PK Motor" (geen merk/producttype)

Genereer NU de perfecte titel:
PROMPT;

            $response = $client->chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'Je bent een expert in het schrijven van korte, krachtige producttitels voor e-commerce. Antwoord ALLEEN met de nieuwe titel, zonder quotes of extra tekst.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.3,
                'max_tokens' => 100
            ]);

            $title = trim($response->choices[0]->message->content);
            $title = trim($title, '"\'');

            // Ensure title is not too long (max 60 chars for SEO)
            if (mb_strlen($title) > 60) {
                $title = mb_substr($title, 0, 60);
                $lastSpace = mb_strrpos($title, ' ');
                if ($lastSpace !== false) {
                    $title = mb_substr($title, 0, $lastSpace);
                }
            }

            $this->info("  Generated title: {$title}");
            return $title;

        } catch (\Exception $e) {
            $this->warn('Could not generate optimized title: ' . $e->getMessage());
            return null;
        }
    }
}
