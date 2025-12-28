<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\OpenAIService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AIConclusieController extends Controller
{
    protected OpenAIService $ai;

    public function __construct(OpenAIService $ai)
    {
        $this->ai = $ai;
    }

    public function conclude(Request $request)
    {
        $eans = $request->input('eans', []);

        if (count($eans) < 2 || count($eans) > 3) {
            return response()->json([
                'html' => '<p class="text-red-600">Selecteer 2 of 3 producten om een AI-analyse te starten.</p>',
            ]);
        }

        $products = Product::with('specifications')->whereIn('ean', $eans)->get();

        if ($products->count() < 2) {
            return response()->json([
                'html' => '<p class="text-red-600">Onvoldoende productdata gevonden.</p>',
            ]);
        }

        // AI-prompt met strikte HTML-structuur
        $beschrijvingen = $products->map(function ($product, $i) {
            $specs = $product->specifications->map(fn ($spec) => "{$spec->name}: {$spec->value}")->implode(', ');

            return 'Product '.($i + 1).": {$product->title}, prijs: €{$product->price}, merk: {$product->brand}, specificaties: {$specs}";
        })->implode("\n\n");

        $prompt = <<<EOT
Je bent een ervaren productspecialist. Vergelijk onderstaande producten en geef een bondige conclusie in modern HTML-formaat.

🎯 Richtlijnen:
- Begin direct met de conclusie.
- Maximaal 100 woorden.
- Gebruik exact deze HTML-structuur (géén extra tags!):
  <h2 class="text-xl font-bold text-blue-800 mt-4 mb-2">Algemene conclusie</h2>
  <p class="mb-4">...</p>
  <h3 class="text-lg font-semibold text-blue-700 mt-4 mb-2">Beste koop</h3>
  <p class="mb-6">...</p>
- Geen opsommingen, codeblokken, aanhalingstekens of ```html.
- Vermeld géén link of knop naar het beste product.

🛒 Productgegevens:
$beschrijvingen
EOT;

        $rawHtml = trim($this->ai->generate($prompt));
        $cleanHtml = html_entity_decode(strip_tags($rawHtml, '<h2><h3><p>'));

        if (! $cleanHtml || stripos($cleanHtml, '<p') === false) {
            return response()->json([
                'html' => '<p class="text-red-600">Er ging iets mis bij het ophalen van de AI-analyse.</p>',
            ]);
        }

        // 🎯 Extract beste koop-blok
        preg_match('/Beste koop<\/h3>\s*<p[^>]*>(.*?)<\/p>/si', $cleanHtml, $matches);
        $besteText = $matches[1] ?? '';

        // 🎯 Zoek bijpassend product
        $besteProduct = $products->first(function ($product) use ($besteText) {
            $title = Str::lower($product->title);
            $brand = Str::lower($product->brand);
            $text = Str::lower($besteText);

            return Str::contains($text, Str::lower($product->title)) || (Str::contains($text, $brand) && Str::contains($text, Str::words($title, 2, '')));
        });

        if ($besteProduct) {
            $slug = $besteProduct->slug ?? Str::slug($besteProduct->title);
            $linkHtml = <<<HTML
<a href="/producten/{$slug}" class="inline-block bg-blue-700 text-white text-sm font-semibold px-5 py-2 rounded-xl shadow hover:bg-blue-800 transition">
    Bekijk beste koop
</a>
HTML;
            $cleanHtml .= "\n\n".$linkHtml;
        }

        return response()->json([
            'html' => $cleanHtml,
        ]);
    }
}
