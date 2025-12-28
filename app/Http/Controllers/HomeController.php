<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public function index()
    {
        $top10Products = Cache::remember('homepage_top10', 3600, function () {
            return $this->getTop10Products();
        });

        $siteName = getSetting('site_name', 'Onze Site');
        $niche = getSetting('niche_name', 'producten');

        return view('home', compact('top10Products', 'siteName', 'niche'));
    }

    private function getTop10Products(): Collection
    {
        // Eerst: producten met popularity scores EN goede rating (populair op Bol.com + betrouwbaar)
        $popularProducts = Product::with(['review', 'blogPosts'])
            ->where('price', '>=', 25)
            ->where('rating_average', '>=', 4.0)
            ->where('popularity_score', '>', 0)
            ->orderByDesc('popularity_score')
            ->orderByDesc('rating_average')
            ->orderByDesc('rating_count')
            ->limit(10)
            ->get();

        // Als we minder dan 10 hebben, vul aan met best beoordeelde producten
        if ($popularProducts->count() < 10) {
            $needed = 10 - $popularProducts->count();
            $excludeIds = $popularProducts->pluck('id')->toArray();

            $fillProducts = Product::with(['review', 'blogPosts'])
                ->where('price', '>=', 25)
                ->where('rating_average', '>=', 4.0)
                ->whereNotIn('id', $excludeIds)
                ->orderByDesc('rating_average')
                ->orderByDesc('rating_count')
                ->limit($needed)
                ->get();

            $popularProducts = $popularProducts->merge($fillProducts);
        }

        return $popularProducts;
    }
}
