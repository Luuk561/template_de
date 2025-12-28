@extends('layouts.app')

@section('title', 'Pagina niet gevonden - 404')
@section('meta-description', 'Sorry, we konden de pagina die je zocht niet vinden. Ontdek onze beste producten en vind wat je zoekt.')

@section('content')

@php
    $primaryColor = getSetting('primary_color', '#7c3aed');
    $siteName = getSetting('site_name', config('app.name'));
    $siteNiche = getSetting('site_niche', 'producten');
@endphp

<style>
    :root {
        --primary-color: {{ $primaryColor }};
    }

    .cta-button {
        background-color: var(--primary-color);
        border-radius: 12px;
        font-weight: 600;
        letter-spacing: -0.015em;
        transition: all 0.3s ease;
    }

    .cta-button:hover {
        transform: translateY(-1px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }

    .cta-button-secondary {
        background-color: rgba(0, 0, 0, 0.05);
        color: #1d1d1f;
        border-radius: 12px;
        font-weight: 600;
        letter-spacing: -0.015em;
        transition: all 0.3s ease;
    }

    .cta-button-secondary:hover {
        background-color: rgba(0, 0, 0, 0.08);
        transform: translateY(-1px);
    }

    .apple-card {
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        transition: all 0.4s ease;
    }

    .apple-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    }

    .text-apple-gray {
        color: #86868b;
    }
</style>

<!-- Main 404 Section -->
<section class="min-h-screen w-full bg-white text-gray-900 flex items-center">
    <div class="w-full max-w-4xl mx-auto px-6 sm:px-8 lg:px-12 text-center">
        
        <!-- 404 Number -->
        <div class="mb-8">
            <h1 class="text-2xl sm:text-2xl lg:text-2xl font-bold text-gray-900 leading-none tracking-tight">
                404
            </h1>
        </div>

        <!-- Error Message -->
        <div class="space-y-6 mb-12">
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-gray-900 leading-tight tracking-tight">
                De pagina die je zocht<br>konden we niet vinden.
            </h2>
            <p class="text-xl text-apple-gray max-w-2xl mx-auto leading-relaxed">
                Misschien is de link verlopen, of is de pagina verplaatst. 
                Geen probleem — we helpen je graag verder.
            </p>
        </div>

        <!-- CTA Buttons -->
        <div class="flex flex-col sm:flex-row gap-4 justify-center mb-12">
            <a href="{{ route('home') }}" 
               class="cta-button inline-flex items-center justify-center px-8 py-4 text-white font-semibold text-lg">
                Ga naar homepage
            </a>
            
            <a href="{{ route('producten.index') }}" 
               class="cta-button-secondary inline-flex items-center justify-center px-8 py-4 font-semibold text-lg">
                Bekijk alle {{ $siteNiche }}
            </a>
        </div>

        <!-- Divider -->
        <div class="w-full h-px bg-gradient-to-r from-transparent via-gray-200 to-transparent"></div>

    </div>
</section>

<!-- Suggestions Section -->
<section class="w-full py-8 sm:py-8 bg-gray-50">
    <div class="max-w-4xl mx-auto px-6 sm:px-8 lg:px-12">
        
        <div class="text-center mb-8">
            <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-3 tracking-tight">
                Populaire bestemmingen
            </h2>
            <p class="text-lg text-apple-gray max-w-xl mx-auto">
                Hier vind je waar de meeste mensen naar op zoek zijn.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            
            <!-- Popular Products -->
            <div class="apple-card rounded-lg p-4 text-center">
                <h3 class="text-base font-semibold text-gray-900 mb-3">Populairste {{ $siteNiche }}</h3>
                <a href="{{ route('producten.index') }}" 
                   class="text-blue-600 font-semibold hover:text-blue-700 transition-colors text-sm">
                    Bekijk alle producten →
                </a>
            </div>

            <!-- Top Rated -->
            <div class="apple-card rounded-lg p-4 text-center">
                <h3 class="text-base font-semibold text-gray-900 mb-3">Best beoordeelde</h3>
                <a href="{{ route('producten.top') }}" 
                   class="text-blue-600 font-semibold hover:text-blue-700 transition-colors text-sm">
                    Bekijk Top 5 →
                </a>
            </div>

            <!-- Reviews -->
            <div class="apple-card rounded-lg p-4 text-center">
                <h3 class="text-base font-semibold text-gray-900 mb-3">Reviews & ervaringen</h3>
                <a href="{{ route('reviews.index') }}" 
                   class="text-blue-600 font-semibold hover:text-blue-700 transition-colors text-sm">
                    Lees reviews →
                </a>
            </div>
        </div>
    </div>
</section>

@endsection

@section('structured-data')
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "WebPage",
    "@id": "{{ url()->current() }}",
    "url": "{{ url()->current() }}",
    "name": "404 - Pagina niet gevonden",
    "description": "Sorry, we konden de pagina die je zocht niet vinden. Ontdek onze beste {{ $siteNiche }} en vind wat je zoekt.",
    "inLanguage": "nl-NL",
    "isPartOf": {
        "@type": "WebSite",
        "@id": "{{ config('app.url') }}/#website",
        "url": "{{ config('app.url') }}",
        "name": "{{ $siteName }}"
    }
}
</script>
@endsection