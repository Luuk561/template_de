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

// Produkte Übersicht (German routes)
Route::get('/produkte', [ProductController::class, 'index'])->name('produkte.index');
Route::get('/produkte/{slug}', [ProductController::class, 'show'])->name('produkte.show');
Route::get('/vergleichen', [ProductVergelijkController::class, 'index'])->name('produkte.vergleichen');
Route::post('/vergleichen/ai-conclusie', [AIConclusieController::class, 'conclude'])->name('ai.conclusie');

// Search API for autocomplete
Route::get('/api/search', [ProductController::class, 'searchApi'])->name('api.search');

// Top 5 Seite
Route::get('/top-5', [ProductController::class, 'top'])->name('produkte.top');

// Beste Marken Seite
Route::get('/beste-marken', [ProductController::class, 'merken'])->name('produkte.merken');

// Blog/Ratgeber Seiten
Route::get('/ratgeber', [BlogController::class, 'index'])->name('ratgeber.index');
Route::get('/ratgeber/{slug}', [BlogController::class, 'show'])->name('ratgeber.show');

// Testberichte Übersicht und Detailseite
Route::get('/testberichte', [ReviewController::class, 'index'])->name('testberichte.index');
Route::get('/testberichte/{slug}', [ReviewController::class, 'show'])->name('testberichte.show');

// Team Seiten (E-E-A-T)
Route::get('/team', [TeamController::class, 'index'])->name('team.index');
Route::get('/team/{slug}', [TeamController::class, 'show'])->name('team.show');

// Informationsseiten
Route::get('/information/{slug}', [InformationPageController::class, 'show'])->name('information.show');

// Rechtliche Seiten
Route::view('/impressum', 'pages.impressum')->name('impressum');
Route::view('/datenschutz', 'pages.privacy')->name('datenschutz');
Route::view('/haftungsausschluss', 'pages.disclaimer')->name('haftungsausschluss');
Route::view('/kontakt', 'pages.contact')->name('kontakt');

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

        // Statische Seiten mit Priority und Changefreq
        $sitemap
            ->add(Url::create('/')
                ->setPriority(1.0)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY))
            ->add(Url::create('/produkte')
                ->setPriority(0.9)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY))
            ->add(Url::create('/top-5')
                ->setPriority(0.8)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY))
            ->add(Url::create('/beste-marken')
                ->setPriority(0.7)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY))
            ->add(Url::create('/ratgeber')
                ->setPriority(0.7)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY))
            ->add(Url::create('/testberichte')
                ->setPriority(0.7)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY))
            ->add(Url::create('/team')
                ->setPriority(0.5)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY))
            ->add(Url::create('/datenschutz')
                ->setPriority(0.3)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_YEARLY))
            ->add(Url::create('/haftungsausschluss')
                ->setPriority(0.3)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_YEARLY))
            ->add(Url::create('/kontakt')
                ->setPriority(0.4)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY))
            ->add(Url::create('/meer-ontdekken')
                ->setPriority(0.5)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY));

        // Dynamische Produkte
        Product::whereNotNull('slug')->get()->each(function ($product) use ($sitemap) {
            $sitemap->add(Url::create("/produkte/{$product->slug}")
                ->setLastModificationDate($product->updated_at)
                ->setPriority(0.8)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY));
        });

        // Dynamische Ratgeber
        BlogPost::whereNotNull('slug')->get()->each(function ($blog) use ($sitemap) {
            $sitemap->add(Url::create("/ratgeber/{$blog->slug}")
                ->setLastModificationDate($blog->updated_at)
                ->setPriority(0.6)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY));
        });

        // Dynamische Testberichte
        Review::whereNotNull('slug')->get()->each(function ($review) use ($sitemap) {
            $sitemap->add(Url::create("/testberichte/{$review->slug}")
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

        // Dynamische Informationsseiten
        InformationPage::where('is_active', true)->whereNotNull('slug')->get()->each(function ($page) use ($sitemap) {
            $sitemap->add(Url::create("/information/{$page->slug}")
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

// llms.txt für AI Search Bots (ChatGPT, Claude, etc.)
Route::get('/llms.txt', function () {
    $siteName = getSetting('site_name', config('app.name'));
    $siteNiche = getSetting('site_niche', 'Produkte');

    $content = <<<TXT
# $siteName - AI Bot Anweisungen

## Über diese Website
Dies ist eine Affiliate-Website mit Fokus auf $siteNiche. Wir bieten ehrliche Testberichte, Vergleiche und Kaufratgeber.

## Inhaltsrichtlinien
- Alle Produkttestberichte basieren auf Spezifikationen und Nutzerbewertungen
- Preise und Verfügbarkeit werden von Amazon.de bezogen
- Ratgeber-Inhalte bieten hilfreiche Kauftipps
- Affiliate-Links werden ordnungsgemäß gekennzeichnet

## Crawling-Anweisungen
Sie dürfen unsere Inhalte für AI-Suchzwecke crawlen und indexieren.
Bitte beachten Sie unsere Sitemap: {url('/sitemap.xml')}

TXT;

    return response($content)->header('Content-Type', 'text/plain');
});
