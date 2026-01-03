<?php

namespace App\View\Composers;

use Illuminate\View\View;
use Illuminate\Support\Str;

class MetaComposer
{
    public function compose(View $view): void
    {
        // Site settings
        $primaryColor = getSetting('primary_color', '#7c3aed');
        $font = getSetting('font_family', 'Urbanist');
        $googleFont = str_replace(' ', '+', $font);
        $siteName = getSetting('site_name', config('app.name'));
        $favicon = getSetting('favicon_url', asset('favicon.png'));

        // Date and locale
        \Carbon\Carbon::setLocale('de');
        $niche = getSetting('site_niche', 'Produkte');
        $month = \Carbon\Carbon::now('Europe/Berlin')->translatedFormat('F');
        $year = \Carbon\Carbon::now('Europe/Berlin')->format('Y');
        $routeName = optional(request()->route())->getName();

        // Black Friday logic
        $bfFlag = (bool) config('blackfriday.active', false);
        $bfStart = config('blackfriday.start');
        $bfUntil = config('blackfriday.until');

        $previewOn = in_array(strtolower((string) request('bf')), ['1','on','true'], true);
        $today = \Carbon\Carbon::today('Europe/Berlin');
        $inWindow = ($bfStart && $bfUntil)
            ? $today->between(
                \Carbon\Carbon::parse($bfStart, 'Europe/Berlin'),
                \Carbon\Carbon::parse($bfUntil, 'Europe/Berlin')
              )
            : false;
        $bfActive = $previewOn || $bfFlag || $inWindow;

        // Model data for detail pages
        $modelTitle = $this->getModelTitle($view);
        $modelExcerpt = $this->getModelExcerpt($view);

        // Meta title and description templates (GERMAN)
        $titleMap = [
            'home' => "Beste {$niche} im {$month} {$year} | {$siteName}",
            'producten.index' => "Beste {$niche} vergleichen & kaufen | {$siteName}",
            'producten.top' => "Top 5 {$niche} — Empfohlene Auswahl | {$siteName}",
            'producten.show' => ":title | {$siteName}",
            'producten.merken' => "Beste Marken {$niche} | {$siteName}",
            'reviews.index' => "Ehrliche Testberichte über {$niche} | {$siteName}",
            'reviews.show' => ":title — Testbericht | {$siteName}",
            'blogs.index' => "Tipps & Ratgeber über {$niche} | {$siteName}",
            'blogs.show' => ":title | {$siteName}",
            'team.index' => "Unser Team | {$siteName}",
            'team.show' => ":title | {$siteName}",
            '*' => "{$siteName} — Vergleichen & kaufen",
        ];

        $descMap = [
            'home' => "Entdecken Sie die besten {$niche} von {$year}. Vergleichen Sie Modelle, sehen Sie aktuelle Angebote und finden Sie schnell, was perfekt zu Ihren Wünschen passt bei {$siteName}.",
            'producten.index' => "Sehen und vergleichen Sie alle {$niche}. Filtern Sie einfach nach Marke, Preis, Spezifikationen und Angeboten. Finden Sie direkt Ihr ideales Produkt bei {$siteName}.",
            'producten.top' => "Unser Expertenteam wählte die Top 5 besten {$niche} mit klaren Vor- und Nachteilen. Treffen Sie schnell die richtige Wahl mit {$siteName}.",
            'producten.show' => ":excerpt",
            'reviews.index' => "Lesen Sie ehrliche Produkttestberichte und Bewertungen über {$niche}. Basierend auf gründlicher Forschung und Spezifikationsvergleichen bei {$siteName}.",
            'reviews.show' => ":excerpt",
            'blogs.index' => "Praktische Kaufratgeber, Tipps und Inspiration über {$niche}. Alles was Sie wissen müssen, um eine kluge Wahl zu treffen mit {$siteName}.",
            'blogs.show' => ":excerpt",
            '*' => "Vergleichen und finden Sie die besten {$niche} für Ihre Situation. Filtern Sie schnell nach Spezifikationen, Preis und Bewertungen. Wählen Sie klug mit {$siteName}.",
        ];

        // Build meta title and description
        $tplTitle = $titleMap[$routeName] ?? $titleMap['*'];
        $tplDesc = $descMap[$routeName] ?? $descMap['*'];

        if ($modelTitle) $tplTitle = str_replace(':title', $modelTitle, $tplTitle);
        if ($modelExcerpt) $tplDesc = str_replace(':excerpt', $modelExcerpt, $tplDesc);

        // Black Friday variant
        if ($bfActive) {
            $tplTitle = "Black Friday Deals {$year} — {$tplTitle}";
            $tplDesc = "Black Friday Angebote: {$tplDesc}";
        }

        // Pagination suffix
        $page = (int) request()->get('page');
        if ($page > 1) $tplTitle .= " | Seite {$page}";

        // Normalize and limit length
        $metaTitle = $modelTitle
            ? trim(preg_replace('/\s+/', ' ', $tplTitle))
            : Str::limit(trim(preg_replace('/\s+/', ' ', $tplTitle)), 60);
        $metaDesc = Str::limit(trim(preg_replace('/\s+/', ' ', $tplDesc)), 155);

        // Canonical URL logic
        $canonicalUrl = url()->current();
        if (request()->routeIs('producten.index') &&
            (request()->has('sort') || request()->has('brand') || request()->has('min_price') ||
             request()->has('max_price') || request()->has('min_rating') || request()->has('discount'))) {
            $canonicalUrl = route('producten.index');
        }

        // Favicon paths
        $faviconDir = dirname($favicon);

        // Share all data with the view
        $view->with(compact(
            'primaryColor',
            'font',
            'googleFont',
            'siteName',
            'favicon',
            'faviconDir',
            'niche',
            'month',
            'year',
            'bfActive',
            'bfUntil',
            'metaTitle',
            'metaDesc',
            'canonicalUrl'
        ));
    }

    private function getModelTitle(View $view): ?string
    {
        $data = $view->getData();

        if (isset($data['product'])) {
            return strip_tags($data['product']->title);
        }
        if (isset($data['post'])) {
            return strip_tags($data['post']->title);
        }
        if (isset($data['review'])) {
            return strip_tags($data['review']->title);
        }
        if (isset($data['teamMember'])) {
            return strip_tags($data['teamMember']->name);
        }

        return null;
    }

    private function getModelExcerpt(View $view): ?string
    {
        $data = $view->getData();

        if (isset($data['product'])) {
            return strip_tags($data['product']->meta_description ?? Str::limit(strip_tags($data['product']->description ?? ''), 155));
        }
        if (isset($data['post'])) {
            return Str::limit(strip_tags($data['post']->excerpt ?? $data['post']->content ?? ''), 155);
        }
        if (isset($data['review'])) {
            return Str::limit(strip_tags($data['review']->excerpt ?? $data['review']->content ?? ''), 155);
        }

        return null;
    }
}
