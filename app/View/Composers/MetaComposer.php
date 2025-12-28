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
        \Carbon\Carbon::setLocale('nl');
        $niche = getSetting('site_niche', 'producten');
        $month = \Carbon\Carbon::now('Europe/Amsterdam')->translatedFormat('F');
        $year = \Carbon\Carbon::now('Europe/Amsterdam')->format('Y');
        $routeName = optional(request()->route())->getName();

        // Black Friday logic
        $bfFlag = (bool) config('blackfriday.active', false);
        $bfStart = config('blackfriday.start');
        $bfUntil = config('blackfriday.until');

        $previewOn = in_array(strtolower((string) request('bf')), ['1','on','true'], true);
        $today = \Carbon\Carbon::today('Europe/Amsterdam');
        $inWindow = ($bfStart && $bfUntil)
            ? $today->between(
                \Carbon\Carbon::parse($bfStart, 'Europe/Amsterdam'),
                \Carbon\Carbon::parse($bfUntil, 'Europe/Amsterdam')
              )
            : false;
        $bfActive = $previewOn || $bfFlag || $inWindow;

        // Model data for detail pages
        $modelTitle = $this->getModelTitle($view);
        $modelExcerpt = $this->getModelExcerpt($view);

        // Meta title and description templates
        $titleMap = [
            'home' => "Beste {$niche} in {$month} {$year} | {$siteName}",
            'producten.index' => "Beste {$niche} vergelijken & kopen | {$siteName}",
            'producten.top' => "Top 5 {$niche} — Aanbevolen keuzes | {$siteName}",
            'producten.show' => ":title | {$siteName}",
            'producten.merken' => "Beste merken {$niche} | {$siteName}",
            'reviews.index' => "Eerlijke reviews over {$niche} | {$siteName}",
            'reviews.show' => ":title — Review | {$siteName}",
            'blogs.index' => "Tips & gidsen over {$niche} | {$siteName}",
            'blogs.show' => ":title | {$siteName}",
            'team.index' => "Ons team | {$siteName}",
            'team.show' => ":title | {$siteName}",
            '*' => "{$siteName} — Vergelijken & kopen",
        ];

        $descMap = [
            'home' => "Ontdek de beste {$niche} van {$year}. Vergelijk modellen, bekijk actuele deals en vind snel wat perfect bij jouw wensen past via {$siteName}.",
            'producten.index' => "Bekijk en vergelijk alle {$niche}. Filter eenvoudig op merk, prijs, specificaties en aanbiedingen. Vind direct jouw ideale product via {$siteName}.",
            'producten.top' => "Onze expertteam selecteerde de Top 5 beste {$niche} met duidelijke voor- en nadelen. Maak snel de juiste keuze met {$siteName}.",
            'producten.show' => ":excerpt",
            'reviews.index' => "Lees eerlijke productreviews en beoordelingen over {$niche}. Gebaseerd op grondig onderzoek en specificatievergelijkingen via {$siteName}.",
            'reviews.show' => ":excerpt",
            'blogs.index' => "Praktische koopgidsen, tips en inspiratie over {$niche}. Alles wat je moet weten om een slimme keuze te maken met {$siteName}.",
            'blogs.show' => ":excerpt",
            '*' => "Vergelijk en vind de beste {$niche} voor jouw situatie. Filter snel op specificaties, prijs en reviews. Kies slim met {$siteName}.",
        ];

        // Build meta title and description
        $tplTitle = $titleMap[$routeName] ?? $titleMap['*'];
        $tplDesc = $descMap[$routeName] ?? $descMap['*'];

        if ($modelTitle) $tplTitle = str_replace(':title', $modelTitle, $tplTitle);
        if ($modelExcerpt) $tplDesc = str_replace(':excerpt', $modelExcerpt, $tplDesc);

        // Black Friday variant
        if ($bfActive) {
            $tplTitle = "Black Friday Deals {$year} — {$tplTitle}";
            $tplDesc = "Black Friday aanbiedingen: {$tplDesc}";
        }

        // Pagination suffix
        $page = (int) request()->get('page');
        if ($page > 1) $tplTitle .= " | Pagina {$page}";

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
            return strip_tags($data['product']->seo_title);
        }
        if (isset($data['post'])) {
            return strip_tags($data['post']->title);
        }
        if (isset($data['review'])) {
            return strip_tags($data['review']->seo_title);
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
            return Str::limit(strip_tags($data['product']->description ?? ''), 155);
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
