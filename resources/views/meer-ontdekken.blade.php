@extends('layouts.app')

@php
    $niches = config('niches');
    $currentHost = str_replace('www.', '', request()->getHost());

    if (app()->environment('local') && in_array($currentHost, ['127.0.0.1', 'localhost'])) {
        $currentHost = 'airfryermetdubbelelade.nl'; // ← Pas aan indien nodig
    }

    $currentCategory = collect($niches)->filter(fn($data) => array_key_exists($currentHost, $data['domains']))->keys()->first();
    $categoryData = $niches[$currentCategory] ?? [];

    $title = $categoryData['title'] ?? 'Meer ontdekken binnen jouw categorie';
    $metaDescription = $categoryData['meta_description'] ?? 'Ontdek verwante niche-websites binnen jouw interessegebied.';
    $heroHeading = $categoryData['hero_heading'] ?? 'Meer ontdekken binnen jouw categorie';
    $heroSubtext = $categoryData['hero_subtext'] ?? 'Deze site maakt deel uit van een netwerk van betrouwbare niche-websites.';
    $ctaText = $categoryData['cta_text'] ?? 'Bezoek verwante niche-websites';

    $seoIntro = $categoryData['seo_intro'] ?? null;
    $seoBlok = $categoryData['seo_blok'] ?? null;

    $allSites = $categoryData['domains'] ?? [];
    $relatedSites = collect($allSites)->reject(fn($_, $domain) => $domain === $currentHost);
    $primaryColor = getSetting('primary_color', '#7c3aed');
    $currentUrl = url()->current();
@endphp

@section('title', $title)
@section('meta_description', $metaDescription)

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        'Meer ontdekken' => route('meer-ontdekken'),
    ]" />
@endsection

@section('head')
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $currentUrl }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name') }}">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">

    <link rel="canonical" href="{{ $currentUrl }}">
    <meta name="robots" content="index, follow">

    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "CollectionPage",
        "name": "{{ $title }}",
        "url": "{{ $currentUrl }}",
        "description": "{{ $metaDescription }}",
        "breadcrumb": {
            "@type": "BreadcrumbList",
            "itemListElement": [
                {
                    "@type": "ListItem",
                    "position": 1,
                    "name": "Home",
                    "item": "{{ url('/') }}"
                },
                {
                    "@type": "ListItem",
                    "position": 2,
                    "name": "Meer ontdekken"
                }
            ]
        },
        "mainEntity": {
            "@type": "ItemList",
            "numberOfItems": {{ $relatedSites->count() }},
            "itemListElement": [
                @foreach($relatedSites as $domain => $omschrijving)
                {
                    "@type": "ListItem",
                    "position": {{ $loop->iteration }},
                    "url": "https://{{ str_replace(['https://', 'http://', 'www.'], '', $domain) }}"
                }@if(!$loop->last),@endif
                @endforeach
            ]
        }
    }
    </script>
@endsection

@section('content')

<style>
    :root {
        --primary-color: {{ $primaryColor }};
    }

    body {
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }

    .site-card {
        backdrop-filter: blur(10px);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .site-card:hover {
        transform: translateY(-2px) scale(1.01);
    }

    .faq-item {
        backdrop-filter: blur(10px);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
</style>

<!-- HERO -->
<section class="w-full bg-gradient-to-br from-indigo-50 via-purple-50 to-pink-50 text-gray-900 py-20 px-4 sm:px-10 lg:px-12 text-center relative overflow-hidden">
    <div class="absolute top-0 left-0 w-full h-full opacity-10">
        <div class="absolute top-10 left-10 w-72 h-72 bg-purple-400 rounded-full mix-blend-multiply filter blur-3xl animate-pulse"></div>
        <div class="absolute bottom-10 right-10 w-72 h-72 bg-indigo-400 rounded-full mix-blend-multiply filter blur-3xl animate-pulse" style="animation-delay: 2s;"></div>
    </div>
    <div class="relative z-10 max-w-4xl mx-auto">
        <div class="inline-block mb-6 px-5 py-2 bg-white rounded-full shadow-md border border-indigo-100">
            <span class="text-sm font-bold text-indigo-600">
                Ontdek ons netwerk
            </span>
        </div>
        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold leading-tight mb-6 text-gray-900">
            {{ $heroHeading }}
        </h1>
        <p class="max-w-2xl mx-auto text-lg sm:text-xl text-gray-700 leading-relaxed">
            {{ $heroSubtext }}
        </p>
    </div>
</section>

<!-- SEO INTRO -->
@if($seoIntro)
<section class="w-full py-16 px-4 bg-gray-50 text-gray-800">
    <div class="max-w-4xl mx-auto">
        <div class="prose prose-lg max-w-none text-gray-700">
            {!! $seoIntro !!}
        </div>
    </div>
</section>
@endif

<!-- OVERZICHT VAN VERWANTE SITES -->
<section class="w-full bg-gradient-to-b from-white to-gray-50 py-20 px-4 sm:px-10 lg:px-12">
    <div class="max-w-6xl mx-auto">
        @if($relatedSites->isNotEmpty())
            <div class="text-center mb-12">
                <h2 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-4">
                    Ontdek vergelijkbare sites
                </h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                    Deel van hetzelfde netwerk, dezelfde kwaliteit
                </p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($relatedSites as $domain => $omschrijving)
                    @php
                        $domeinnaam = str_replace(['https://', 'http://', 'www.'], '', $domain);
                        $siteNaam = ucfirst(str_replace(['-', '.nl'], [' ', ''], $domeinnaam));
                    @endphp
                    <div class="site-card group bg-white rounded-xl p-6 shadow-sm border border-gray-100 hover:shadow-lg hover:border-indigo-200 transition-all duration-300">
                        <div class="flex items-start justify-between mb-3">
                            <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg flex items-center justify-center text-white font-bold text-xl shadow-md">
                                {{ substr($siteNaam, 0, 1) }}
                            </div>
                            <svg class="w-5 h-5 text-gray-400 group-hover:text-indigo-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                            </svg>
                        </div>
                        <h2 class="text-lg font-bold text-gray-900 mb-2 group-hover:text-indigo-600 transition-colors">
                            {{ $siteNaam }}
                        </h2>
                        <p class="text-sm text-gray-600 mb-5 leading-relaxed">
                            {{ $omschrijving }}
                        </p>
                        <a href="https://{{ $domeinnaam }}"
                           class="inline-flex items-center gap-2 text-sm font-semibold text-indigo-600 hover:text-indigo-700 transition-colors"
                           target="_blank" rel="noopener">
                            Bezoek site
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                            </svg>
                        </a>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-center text-gray-500 text-lg">Er zijn op dit moment geen verwante websites beschikbaar voor deze categorie.</p>
        @endif
    </div>
</section>

<!-- SEO BLOK -->
@if($seoBlok)
<section class="w-full py-20 px-4 bg-gray-50 text-gray-800">
    <div class="max-w-4xl mx-auto">
        <div class="prose prose-lg max-w-none text-gray-700">
            {!! $seoBlok !!}
        </div>
    </div>
</section>
@endif

<!-- FAQ -->
@if(!empty($categoryData['faq']))
<section class="w-full bg-white py-20 px-4 sm:px-10 lg:px-12">
    <div class="max-w-4xl mx-auto">
        <div class="text-center mb-12">
            <div class="inline-block mb-4 px-4 py-2 bg-indigo-50 rounded-full">
                <span class="text-sm font-semibold text-indigo-600">FAQ</span>
            </div>
            <h2 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-4">
                Veelgestelde vragen
            </h2>
            <p class="text-lg text-gray-600">
                Alles wat je moet weten over deze categorie
            </p>
        </div>
        <div class="space-y-4">
            @foreach($categoryData['faq'] as $item)
                <div class="faq-item bg-gradient-to-br from-gray-50 to-white border border-gray-200 rounded-xl p-6 hover:border-indigo-200 hover:shadow-md transition-all duration-300">
                    <h3 class="text-lg font-bold text-gray-900 mb-3 flex items-start gap-3">
                        <svg class="w-6 h-6 text-indigo-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        {{ $item['vraag'] }}
                    </h3>
                    <p class="text-gray-700 leading-relaxed pl-9">{{ $item['antwoord'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<!-- CTA -->
<section class="w-full py-20 px-4 sm:px-10 lg:px-12 bg-gradient-to-br from-indigo-900 via-indigo-700 to-purple-600 text-white relative overflow-hidden">
    <div class="absolute top-[-100px] left-[-100px] w-[400px] h-[400px] bg-white opacity-10 rounded-full blur-3xl z-0"></div>
    <div class="absolute bottom-[-120px] right-[-80px] w-[500px] h-[500px] bg-white opacity-5 rounded-full blur-3xl z-0"></div>

    <div class="relative z-10 max-w-3xl mx-auto text-center space-y-6 sm:space-y-8">
        <h2 class="text-3xl sm:text-4xl font-extrabold">Blijf ontdekken</h2>
        <p class="text-lg text-white/90 leading-relaxed">
            Onze niche-sites zijn met zorg geselecteerd om jou te helpen de beste keuze te maken. Van sportproducten tot slimme apparaten — wij hebben het voor je uitgezocht.
        </p>
        <a href="{{ route('home') }}" class="inline-block text-sm font-medium underline hover:text-white/70 transition whitespace-nowrap">
            Terug naar de homepage →
        </a>
    </div>
</section>

@endsection
