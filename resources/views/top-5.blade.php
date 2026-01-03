@extends('layouts.app')

{{-- Meta tags worden automatisch gegenereerd via layouts/app.blade.php --}}

@section('breadcrumbs')
    <x-breadcrumbs :items="['Top 5' => route('produkte.top')]" />
@endsection

@section('content')
@php
    \Carbon\Carbon::setLocale('de');
    $huidigeMaand = \Carbon\Carbon::now('Europe/Berlin')->translatedFormat('F');
    $primaryColor = getSetting('primary_color', '#7c3aed');
@endphp

<style>
    :root {
        --primary-color: {{ $primaryColor }};
        --primary-dark: color-mix(in srgb, {{ $primaryColor }} 20%, #000 80%);
    }

    .cta-button {
        background-color: var(--primary-color);
    }

    .cta-button:hover {
        opacity: 0.9;
    }

    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    /* Apply primary-dark to all text colors */
    .text-gray-900 {
        color: var(--primary-dark) !important;
    }

    .text-gray-800 {
        color: var(--primary-dark) !important;
    }

    .text-gray-700 {
        color: color-mix(in srgb, {{ $primaryColor }} 15%, #000 75%) !important;
    }

    .text-gray-600 {
        color: color-mix(in srgb, {{ $primaryColor }} 12%, #000 65%) !important;
    }
</style>

<section class="w-full bg-gradient-to-b from-white via-gray-50 to-white pt-24 pb-12">
    <div class="max-w-7xl mx-auto px-6 sm:px-8">
        <div class="text-center max-w-4xl mx-auto space-y-4">
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black leading-tight">
                @if(hasStructuredContent('Produkte_top_hero_titel'))
                    {!! getContent('Produkte_top_hero_titel.title', ['Monat' => $huidigeMaand]) !!}
                @else
                    {!! getContent('Produkte_top_hero_titel', ['Monat' => $huidigeMaand, 'fallback' => 'Top 5 Selectie']) !!}
                @endif
            </h1>

            <p class="text-lg sm:text-xl text-gray-600">
                Door experts geselecteerd op basis van Preis-kwaliteit en Testberichte
            </p>
        </div>
    </div>
</section>

@if($products->count())
<!-- TOP 5 LIST -->
<section id="top5-products" class="w-full py-8 bg-white">
    <div class="max-w-7xl mx-auto px-6 sm:px-8">
        <div class="text-center mb-10">
            <div class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 rounded-full border border-gray-200 mb-4">
                <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span class="text-sm font-medium text-gray-700">Dagelijks bijgewerkt</span>
            </div>
        </div>

        <div class="space-y-4 w-full">
            @foreach($products as $index => $product)
                @php
                    $affiliateLink = getProductAffiliateLink($product);
                    $savings = ($product->strikethrough_price && $product->price)
                        ? $product->strikethrough_price - $product->price
                        : 0;
                    $rating = $product->rating_average ?? 0;
                    $fullStars = floor($rating);
                    $position = $index + 1;

                    // All rankings use primary color
                    $primaryColor = getSetting('primary_color', '#7c3aed');
                    $rankingColor = '';
                @endphp

                <div class="bg-white rounded-2xl hover:bg-gray-50 transition-all duration-200 overflow-hidden border border-gray-200 w-full {{ !$loop->last ? 'border-b' : '' }}">
                    <!-- Mobile Layout (< 768px) -->
                    <div class="md:hidden p-4 w-full">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center text-white font-black text-lg shadow-lg" style="background: {{ $primaryColor }};">
                                {{ $position }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="font-bold text-sm text-gray-900 line-clamp-2 leading-tight mb-1">
                                    {{ $product->title }}
                                </h3>
                                @if($product->brand)
                                    <p class="text-xs text-gray-500 font-medium">{{ $product->brand }}</p>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-4 mb-3">
                            <div class="w-28 h-28 flex items-center justify-center p-2">
                                <img src="{{ $product->image_url ?? 'https://via.placeholder.com/100x100?text=Kein+Bild' }}"
                                     alt="{{ $product->title }}"
                                     class="max-w-full max-h-full object-contain">
                            </div>
                            <div class="flex-1">
                                @if($rating > 0)
                                    <div class="flex items-center gap-1 mb-2">
                                        <div class="flex items-center">
                                            @for($i = 1; $i <= 5; $i++)
                                                <svg class="w-3.5 h-3.5 {{ $i <= $fullStars ? 'text-yellow-400' : 'text-gray-300' }} fill-current" viewBox="0 0 20 20">
                                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                                </svg>
                                            @endfor
                                        </div>
                                        <span class="text-xs text-gray-600 ml-1 font-medium">{{ number_format($rating, 1) }}</span>
                                        @if($product->rating_count)
                                            <span class="text-xs text-gray-400">({{ $product->rating_count }})</span>
                                        @endif
                                    </div>
                                @endif

                                @if($product->review || $product->blogPosts->isNotEmpty())
                                    <div class="flex gap-1.5 text-xs mb-2">
                                        @if($product->review)
                                            <span class="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full font-semibold">Review</span>
                                        @endif
                                        @if($product->blogPosts->isNotEmpty())
                                            <span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-semibold">Blog</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <a href="{{ route('produkte.show', $product->slug) }}"
                               class="flex-1 bg-white hover:bg-gray-50 text-gray-900 text-sm font-semibold py-3 px-4 rounded-xl text-center transition border-2 border-gray-200">
                                Details
                            </a>
                            <a href="{{ $affiliateLink }}" target="_blank" rel="nofollow sponsored"
                               class="flex-1 cta-button text-white text-sm font-semibold py-3 px-4 rounded-xl text-center transition shadow-sm">
                                Preis prüfen auf Amazon
                            </a>
                        </div>
                    </div>

                    <!-- Desktop Layout (>= 768px) -->
                    <div class="hidden md:grid md:grid-cols-12 gap-4 p-5 items-center">
                        <!-- Position -->
                        <div class="col-span-1 flex items-center">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center text-white font-black text-lg shadow-lg flex-shrink-0" style="background: {{ $primaryColor }};">
                                {{ $position }}
                            </div>
                        </div>

                        <!-- Image -->
                        <div class="col-span-2 flex items-center">
                            <div class="w-full h-20 flex items-center justify-center">
                                <img src="{{ $product->image_url ?? 'https://via.placeholder.com/100x100?text=Kein+Bild' }}"
                                     alt="{{ $product->title }}"
                                     class="max-w-full max-h-full object-contain">
                            </div>
                        </div>

                        <!-- Product Info -->
                        <div class="col-span-4">
                            @if($product->brand)
                                <p class="text-sm text-gray-500 font-medium mb-2 uppercase tracking-wide">{{ $product->brand }}</p>
                            @endif
                            <h3 class="font-bold text-lg text-gray-900 line-clamp-2 leading-tight mb-3">
                                {{ $product->title }}
                            </h3>
                            @if($rating > 0)
                                <div class="flex items-center gap-1 mb-3">
                                    <div class="flex items-center">
                                        @for($i = 1; $i <= 5; $i++)
                                            <svg class="w-4 h-4 {{ $i <= $fullStars ? 'text-yellow-400' : 'text-gray-300' }} fill-current" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                            </svg>
                                        @endfor
                                    </div>
                                    <span class="text-sm text-gray-600 ml-1 font-medium">{{ number_format($rating, 1) }}</span>
                                    @if($product->rating_count)
                                        <span class="text-sm text-gray-400">({{ $product->rating_count }})</span>
                                    @endif
                                </div>
                            @endif

                            @if($product->review || $product->blogPosts->isNotEmpty())
                                <div class="flex gap-2 text-xs">
                                    @if($product->review)
                                        <span class="bg-emerald-100 text-emerald-700 px-3 py-1.5 rounded-full font-semibold border border-emerald-200">Review</span>
                                    @endif
                                    @if($product->blogPosts->isNotEmpty())
                                        <span class="bg-blue-100 text-blue-700 px-3 py-1.5 rounded-full font-semibold border border-blue-200">Blog</span>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <!-- Actions -->
                        <div class="col-span-5 flex gap-2 justify-end">
                            <a href="{{ route('produkte.show', $product->slug) }}"
                               class="bg-white hover:bg-gray-50 text-gray-900 text-sm font-semibold py-2.5 px-5 rounded-xl text-center transition border-2 border-gray-200 whitespace-nowrap">
                                Details
                            </a>
                            <a href="{{ $affiliateLink }}" target="_blank" rel="nofollow sponsored"
                               class="cta-button text-white text-sm font-semibold py-2.5 px-5 rounded-xl text-center transition shadow-sm whitespace-nowrap">
                                Preis prüfen auf Amazon
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

    </div>
</section>
@else
    <section class="w-full py-20 text-center text-gray-500 bg-white">
        <p>Er zijn momenteel geen Produkte in de Top 5.</p>
    </section>
@endif

<!-- SEO BLOK -->
<section class="w-full py-16 px-6 sm:px-8 bg-white">
    @if(hasStructuredContent('Produkte_top_seo_blok'))
        {{-- STRUCTURED MODE: Two-column layout (text left, button right) --}}
        <div class="max-w-7xl mx-auto">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                {{-- Tekst links --}}
                <div>
                    <h2 class="text-3xl sm:text-4xl font-bold mb-6 text-gray-900">{!! getContent('Produkte_top_seo_blok.title') !!}</h2>
                    <p class="text-lg text-gray-700 mb-8">{!! getContent('Produkte_top_seo_blok.intro') !!}</p>

                    <div class="space-y-6">
                        <div>
                            <h3 class="text-xl font-bold mb-2 text-gray-900">{!! getContent('Produkte_top_seo_blok.section1_title') !!}</h3>
                            <p class="text-gray-700">{!! getContent('Produkte_top_seo_blok.section1_text') !!}</p>
                        </div>

                        <div>
                            <h3 class="text-xl font-bold mb-2 text-gray-900">{!! getContent('Produkte_top_seo_blok.section2_title') !!}</h3>
                            <p class="text-gray-700">{!! getContent('Produkte_top_seo_blok.section2_text') !!}</p>
                        </div>

                        <div>
                            <h3 class="text-xl font-bold mb-2 text-gray-900">{!! getContent('Produkte_top_seo_blok.section3_title') !!}</h3>
                            <p class="text-gray-700">{!! getContent('Produkte_top_seo_blok.section3_text') !!}</p>
                        </div>
                    </div>
                </div>
                {{-- Button rechts --}}
                <div class="flex justify-center lg:justify-end">
                    <a href="{{ route('produkte.index') }}" class="cta-button inline-block px-8 py-4 text-white font-semibold rounded-xl shadow-lg transition hover:scale-105">
                        Alle {{ $niche }} ansehen
                    </a>
                </div>
            </div>
        </div>
    @else
        {{-- FALLBACK: HTML MODE --}}
        <div class="max-w-4xl mx-auto prose prose-gray prose-lg">
            {!! getContent('Produkte_top_seo_blok', ['fallback' => '<p>Hier komt tekst te staan.</p>']) !!}
        </div>
    @endif
</section>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.scroll-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
    });
</script>
@endsection
