<?php

use App\Http\Controllers\AIConclusieController;
use App\Http\Controllers\BlackFridayController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InformationPageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductVergelijkController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\TeamController;
use App\Models\BlogPost;
use App\Models\InformationPage;
use App\Models\Product;
use App\Models\Review;
use App\Models\TeamMember;
use Illuminate\Support\Facades\Route;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

// Homepagina
Route::get('/', [HomeController::class, 'index'])->name('home');

// Producten overzicht
Route::get('/producten', [ProductController::class, 'index'])->name('producten.index');
Route::get('/producten/{slug}', [ProductController::class, 'show'])->name('producten.show');
Route::get('/vergelijken', [ProductVergelijkController::class, 'index'])->name('producten.vergelijken');
Route::post('/vergelijken/ai-conclusie', [AIConclusieController::class, 'conclude'])->name('ai.conclusie');

// Top 5 pagina
Route::get('/top-5', [ProductController::class, 'top'])->name('producten.top');

// Beste merken pagina
Route::get('/beste-merken', [ProductController::class, 'merken'])->name('producten.merken');

// Blogpagina's
Route::get('/blogs', [BlogController::class, 'index'])->name('blogs.index');
Route::get('/blogs/{slug}', [BlogController::class, 'show'])->name('blogs.show');

// Review overzicht en detailpagina
Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');
Route::get('/reviews/{slug}', [ReviewController::class, 'show'])->name('reviews.show');

// Team pagina's (E-E-A-T)
Route::get('/team', [TeamController::class, 'index'])->name('team.index');
Route::get('/team/{slug}', [TeamController::class, 'show'])->name('team.show');

// Informatie pagina's
Route::get('/informatie/{slug}', [InformationPageController::class, 'show'])->name('informatie.show');

// Informatieve pagina's
Route::view('/privacy', 'pages.privacy')->name('privacy');
Route::view('/disclaimer', 'pages.disclaimer')->name('disclaimer');
Route::view('/contact', 'pages.contact')->name('contact');

// Black Friday pagina
Route::get('/blackfriday', [BlackFridayController::class, 'show'])->name('blackfriday');

// ✅ Meer ontdekken-pagina (dynamisch gebaseerd op domein)
Route::get('/meer-ontdekken', function () {
    $currentHost = str_replace('www.', '', request()->getHost());

    // Lokale override bij testen op 127.0.0.1 of localhost
    if (app()->environment('local') && in_array($currentHost, ['127.0.0.1', 'localhost'])) {
        $currentHost = 'airfryermetdubbelelade.nl'; // ← Pas aan voor jouw lokale testsite
    }

    $niches = config('niches');

    $currentCategory = collect($niches)
        ->filter(fn ($data) => array_key_exists($currentHost, $data['domains']))
        ->keys()
        ->first();

    if (! $currentCategory) {
        abort(404, 'Categorie niet gevonden');
    }

    $siteDescriptions = $niches[$currentCategory]['domains'] ?? [];

    $relatedSites = collect(array_keys($siteDescriptions))
        ->reject(fn ($domain) => $domain === $currentHost);

    $categoryData = $niches[$currentCategory];

    return view('meer-ontdekken', compact('relatedSites', 'currentCategory', 'categoryData', 'siteDescriptions'));
})->name('meer-ontdekken');

// ✅ Dynamische sitemap.xml route (cached for 1 hour)
Route::get('/sitemap.xml', function () {
    return Cache::remember('sitemap_xml', 3600, function () {
        $sitemap = Sitemap::create();

        // Statische pagina's met priority en changefreq
        $sitemap
            ->add(Url::create('/')
                ->setPriority(1.0)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY))
            ->add(Url::create('/producten')
                ->setPriority(0.9)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY))
            ->add(Url::create('/top-5')
                ->setPriority(0.8)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY))
            ->add(Url::create('/beste-merken')
                ->setPriority(0.7)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY))
            ->add(Url::create('/blogs')
                ->setPriority(0.7)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY))
            ->add(Url::create('/reviews')
                ->setPriority(0.7)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY))
            ->add(Url::create('/team')
                ->setPriority(0.5)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY))
            ->add(Url::create('/privacy')
                ->setPriority(0.3)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_YEARLY))
            ->add(Url::create('/disclaimer')
                ->setPriority(0.3)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_YEARLY))
            ->add(Url::create('/contact')
                ->setPriority(0.4)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY))
            ->add(Url::create('/meer-ontdekken')
                ->setPriority(0.5)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY));

        // Dynamische producten
        Product::whereNotNull('slug')->get()->each(function ($product) use ($sitemap) {
            $sitemap->add(Url::create("/producten/{$product->slug}")
                ->setLastModificationDate($product->updated_at)
                ->setPriority(0.8)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY));
        });

        // Dynamische blogs
        BlogPost::whereNotNull('slug')->get()->each(function ($blog) use ($sitemap) {
            $sitemap->add(Url::create("/blogs/{$blog->slug}")
                ->setLastModificationDate($blog->updated_at)
                ->setPriority(0.6)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY));
        });

        // Dynamische reviews
        Review::whereNotNull('slug')->get()->each(function ($review) use ($sitemap) {
            $sitemap->add(Url::create("/reviews/{$review->slug}")
                ->setLastModificationDate($review->updated_at)
                ->setPriority(0.7)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY));
        });

        // Dynamische team member profiles
        TeamMember::whereNotNull('slug')->get()->each(function ($member) use ($sitemap) {
            $sitemap->add(Url::create("/team/{$member->slug}")
                ->setLastModificationDate($member->updated_at)
                ->setPriority(0.4)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY));
        });

        // Dynamische informatie pagina's
        InformationPage::where('is_active', true)->whereNotNull('slug')->get()->each(function ($page) use ($sitemap) {
            $sitemap->add(Url::create("/informatie/{$page->slug}")
                ->setLastModificationDate($page->updated_at)
                ->setPriority(0.7)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY));
        });

        return $sitemap;
    });
});

// Dynamic robots.txt
Route::get('/robots.txt', function () {
    $content = "User-agent: *\nDisallow:\n\nSitemap: " . url('/sitemap.xml');
    return response($content)->header('Content-Type', 'text/plain');
});

// llms.txt for AI search bots (ChatGPT, Claude, etc.)
Route::get('/llms.txt', function () {
    $siteName = getSetting('site_name', config('app.name'));
    $siteNiche = getSetting('site_niche', 'producten');

    $content = <<<TXT
# $siteName - AI Bot Instructions

## About This Site
This is an affiliate website focused on $siteNiche. We provide honest reviews, comparisons, and buying guides.

## Content Guidelines
- All product reviews are based on specifications and user ratings
- Prices and availability are fetched from bol.com API
- Blog content provides helpful buying guides and tips
- Affiliate links are properly disclosed

## Crawling Instructions
You may crawl and index our content for AI search purposes.
Please respect our sitemap: {url('/sitemap.xml')}

TXT;

    return response($content)->header('Content-Type', 'text/plain');
});
