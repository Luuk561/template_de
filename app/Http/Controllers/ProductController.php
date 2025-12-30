<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query();

        // Global filter: Alleen producten van minimaal €25
        $query->where('price', '>=', 25);

        // Zoekterm op titel
        if ($request->filled('search')) {
            $query->where('title', 'like', '%'.$request->search.'%');
        }

        // Filters
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        if ($request->filled('brand')) {
            $query->where('brand', $request->brand);
        }

        if ($request->filled('min_rating')) {
            $query->where('rating_average', '>=', $request->min_rating);
        }

        if ($request->filled('discount')) {
            $query->whereColumn('strikethrough_price', '>', 'price');
        }

        // Sorteeropties (aangepast voor overeenstemming met Blade)
        switch ($request->input('sort')) {
            case 'popular':
                $query->orderByDesc('rating_count');
                break;
            case 'price_asc':
                $query->orderBy('price');
                break;
            case 'price_desc':
                $query->orderByDesc('price');
                break;
            case 'rating':
                $query->orderByDesc('rating_average')->orderByDesc('rating_count');
                break;
            default:
                // Default: beste rating met veel reviews eerst
                $query->orderByDesc('rating_average')->orderByDesc('rating_count');
        }

        $products = $query->paginate(20);

        $merken = Product::whereNotNull('brand')
            ->where('price', '>=', 25)
            ->select('brand')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand');

        return view('produkte.index', compact('products', 'merken'));
    }

    public function show($slug)
    {
        $product = Product::with(['review', 'blogPosts', 'images', 'specifications' => function($query) {
            $query->orderBy('group')->orderBy('id');
        }])->where('slug', $slug)->firstOrFail();

        // Get related products: same brand or similar price range, exclude current product
        $relatedProducts = Product::where('id', '!=', $product->id)
            ->where('is_available', true) // Only available products
            ->where('price', '>=', 25) // Minimaal €25
            ->where(function($query) use ($product) {
                // Same brand
                if ($product->brand) {
                    $query->where('brand', $product->brand);
                }
                // Or similar price (within 20% range)
                if ($product->price) {
                    $query->orWhereBetween('price', [
                        $product->price * 0.8,
                        $product->price * 1.2
                    ]);
                }
            })
            ->where('rating_average', '>=', 4.0) // Only show well-rated products
            ->where('rating_count', '>=', 5) // Minimum reviews voor betrouwbaarheid
            ->orderByDesc('rating_average')
            ->orderByDesc('rating_count') // Bij gelijke rating: meer reviews = beter
            ->limit($product->is_available ? 4 : 6) // More alternatives if product unavailable
            ->get();

        return view('produkte.show', compact('product', 'relatedProducts'));
    }

    public function top()
    {
        $products = Cache::remember('top5_products', 3600, function () {
            return $this->getTop5Products();
        });

        $niche = getSetting('niche', 'producten');

        return view('top-5', compact('products', 'niche'));
    }

    private function getTop5Products()
    {
        // First try with exclusions and high standards
        $products = $this->getProductsWithExclusions();
        
        // If we don't have 5 products, relax criteria
        if ($products->count() < 5) {
            $products = $this->getProductsWithRelaxedCriteria();
        }
        
        // Final fallback: just get best rated products
        if ($products->count() < 5) {
            $products = Product::with(['review', 'blogPosts'])
                ->where('price', '>=', 25)
                ->where('rating_average', '>=', 3.5)
                ->orderByDesc('rating_average')
                ->limit(5)
                ->get();
        }

        return $products;
    }

    private function getProductsWithExclusions()
    {
        // Get products to exclude (Smart Picks from homepage + default index top 8)
        $smartPickIds = $this->getSmartPickIds();

        // Default index sort (rating desc) - first 8 products
        $defaultIndexIds = Product::orderByDesc('rating_average')
            ->limit(8)
            ->pluck('id')
            ->toArray();

        $excludeIds = array_unique(array_merge($smartPickIds, $defaultIndexIds));

        // Get candidate products sorted by popularity score from Bol.com
        return Product::with(['review', 'blogPosts'])
            ->where('price', '>=', 25)
            ->whereNotIn('id', $excludeIds)
            ->where('rating_average', '>=', 4.0)
            ->where('rating_count', '>=', 3)
            ->orderByDesc('popularity_score')
            ->orderByDesc('rating_average')
            ->orderByDesc('rating_count')
            ->limit(5)
            ->get();
    }

    private function getProductsWithRelaxedCriteria()
    {
        // No exclusions, just get best products
        return Product::with(['review', 'blogPosts'])
            ->where('price', '>=', 25)
            ->where('rating_average', '>=', 3.5)
            ->orderByDesc('popularity_score')
            ->orderByDesc('rating_average')
            ->orderByDesc('rating_count')
            ->limit(5)
            ->get();
    }

    public function merken()
    {
        $merken = Product::select('brand', DB::raw('COUNT(*) as aantal'), DB::raw('AVG(rating_average) as gemiddelde_rating'))
            ->whereNotNull('brand')
            ->where('price', '>=', 25)
            ->groupBy('brand')
            ->having('aantal', '>=', 3)
            ->orderByDesc('gemiddelde_rating')
            ->get();

        return view('produkte.merken', compact('merken'));
    }

    private function getSmartPickIds(): array
    {
        $picks = collect();

        // 1. Hoogste euro besparing (met goede rating) - same logic as HomeController
        $bestSavings = Product::whereNotNull('strikethrough_price')
            ->whereColumn('price', '<', 'strikethrough_price')
            ->where('price', '>=', 25)
            ->where('rating_average', '>=', 4.0)
            ->selectRaw('*, (strikethrough_price - price) as euro_savings')
            ->orderBy('euro_savings', 'desc')
            ->first();

        if ($bestSavings) {
            $picks->push($bestSavings);
        }

        // 2. Beste rating (4.5+ sterren)
        $bestRated = Product::where('rating_average', '>=', 4.5)
            ->where('price', '>=', 25)
            ->where('id', '!=', $bestSavings?->id)
            ->orderBy('rating_average', 'desc')
            ->first();

        if ($bestRated) {
            $picks->push($bestRated);
        }

        // 3. Nieuwste toevoeging (< 14 dagen)
        $newest = Product::where('created_at', '>=', now()->subDays(14))
            ->where('price', '>=', 25)
            ->whereNotIn('id', $picks->pluck('id'))
            ->orderBy('created_at', 'desc')
            ->first();

        if ($newest) {
            $picks->push($newest);
        }

        // 4. Fallback: random goed product
        while ($picks->count() < 4) {
            $fallback = Product::where('rating_average', '>=', 3.5)
                ->where('price', '>=', 25)
                ->whereNotIn('id', $picks->pluck('id'))
                ->inRandomOrder()
                ->first();

            if ($fallback) {
                $picks->push($fallback);
            } else {
                break;
            }
        }

        return $picks->pluck('id')->toArray();
    }

    /**
     * Search API for autocomplete suggestions
     */
    public function searchApi(Request $request)
    {
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $products = Product::where('is_available', true)
            ->where(function($q) use ($query) {
                $q->where('title', 'LIKE', "%{$query}%")
                  ->orWhere('brand', 'LIKE', "%{$query}%");
            })
            ->orderBy('rating_average', 'desc')
            ->limit(5)
            ->get(['id', 'slug', 'title', 'brand', 'price', 'image_url', 'rating_average']);

        return response()->json($products->map(function($product) {
            return [
                'id' => $product->id,
                'slug' => $product->slug,
                'title' => $product->title,
                'brand' => $product->brand,
                'price' => $product->price,
                'image_url' => $product->image_url,
                'rating' => $product->rating_average,
                'url' => route('produkte.show', $product->slug),
            ];
        }));
    }
}
