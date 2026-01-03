@extends('layouts.app')

{{-- Meta tags worden automatisch gegenereerd via layouts/app.blade.php --}}

@section('content')

@php
    \Carbon\Carbon::setLocale('de');
    $huidigeMaand = \Carbon\Carbon::now('Europe/Berlin')->translatedFormat('F');
    $huidigJaar = \Carbon\Carbon::now('Europe/Berlin')->year;

    // Homepage.hero: structured (title+subtitle) or HTML fallback
    if (hasStructuredContent('homepage.hero')) {
        $heroContent = getContent('homepage.hero.title', ['Monat' => $huidigeMaand, 'jaar' => $huidigJaar]);
    } else {
        $heroContent = getContent('homepage.hero', ['Monat' => $huidigeMaand, 'jaar' => $huidigJaar]);
    }

    $heroImage    = getImage('homepage.hero');
    $hasPageImage = !empty($heroImage);
    $primaryColor = getSetting('primary_color', '#7c3aed');

    // Generate meta description for schema (same logic as layout)
    $siteName = getSetting('site_name', config('app.name'));
    $niche = getSetting('site_niche', 'Produkte');
    $metaDesc = "Alles über {$niche}: vergleichen, Erklärungen und aktuelle Angebote. Finden Sie schnell, was zu Ihnen passt auf {$siteName}.";

    // Black Friday is handled by the layout, no need to include here
@endphp

<style>
    :root {
        --primary-color: {{ $primaryColor }};
        --primary-dark: color-mix(in srgb, {{ $primaryColor }} 20%, #000 80%);
    }

    .cta-button {
        background-color: var(--primary-color);
        transition: all 0.2s ease;
    }

    .cta-button:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    }

    .cta-button-secondary {
        border: 2px solid #e5e7eb;
        background: white;
        color: var(--primary-dark);
        transition: all 0.2s ease;
    }

    .cta-button-secondary:hover {
        border-color: #d1d5db;
        background: #f9fafb;
    }

    .product-card:hover {
        border-color: var(--primary-color);
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

    /* Links in content sections (informatie pagina links) */
    section a:not(.cta-button):not(.cta-button-secondary) {
        color: var(--primary-color);
        text-decoration: underline;
        font-weight: 600;
        transition: all 0.2s ease;
    }

    section a:not(.cta-button):not(.cta-button-secondary):hover {
        color: var(--primary-dark);
        text-decoration-thickness: 2px;
    }
</style>

<!-- ===== HERO SECTION ===== -->
<section class="relative w-full bg-gradient-to-b from-white via-gray-50 to-white text-gray-900 pt-12 pb-4 sm:pb-6 overflow-hidden">
    @php
        $heroProducts = $top10Products->take(3);
    @endphp

    <div class="max-w-7xl mx-auto px-6 sm:px-8 w-full relative">
        <div class="flex flex-col lg:grid lg:grid-cols-5 gap-6 lg:gap-8 items-center">
            <!-- Product Images - Show first on mobile, right on desktop -->
            <div class="lg:col-span-2 lg:order-2 w-full">
                <div class="grid grid-cols-6 grid-rows-6 gap-2 h-[280px] sm:h-[320px] lg:h-[450px]">
                    @php
                        $layouts = [
                            ['col' => 'col-span-4', 'row' => 'row-span-4', 'start-col' => 'col-start-1', 'start-row' => 'row-start-1'],
                            ['col' => 'col-span-3', 'row' => 'row-span-3', 'start-col' => 'col-start-4', 'start-row' => 'row-start-3'],
                            ['col' => 'col-span-3', 'row' => 'row-span-2', 'start-col' => 'col-start-1', 'start-row' => 'row-start-5'],
                        ];
                    @endphp

                    @foreach($heroProducts as $index => $product)
                        @php
                            $layout = $layouts[$index] ?? $layouts[0];
                        @endphp
                        <a href="{{ route('produkte.show', $product->slug) }}"
                           class="group relative bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 p-4 sm:p-6 border border-gray-100 hover:border-gray-200 {{ $layout['col'] }} {{ $layout['row'] }} {{ $layout['start-col'] }} {{ $layout['start-row'] }}">
                            <div class="w-full h-full flex items-center justify-center">
                                <img src="{{ $product->image_url ?? 'https://via.placeholder.com/300x300?text=Kein+Bild' }}"
                                     alt="{{ $product->title }}"
                                     class="max-w-full max-h-full object-contain group-hover:scale-105 transition-transform duration-300">
                            </div>
                            <div class="absolute inset-0 bg-black opacity-0 group-hover:opacity-5 transition-opacity duration-300 pointer-events-none"></div>
                        </a>
                    @endforeach
                </div>
            </div>

            <!-- Content - Show second on mobile, left on desktop -->
            <div class="lg:col-span-3 lg:order-1 space-y-6 sm:space-y-8">
                <div class="space-y-4">
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-white rounded-full shadow-sm border border-gray-200">
                        <div class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></div>
                        <span class="text-xs font-medium text-gray-600">
                            Aktualisiert {{ \Carbon\Carbon::now()->locale('de')->translatedFormat('F Y') }}
                        </span>
                    </div>

                    <h1 class="text-4xl sm:text-5xl md:text-6xl font-black leading-[1.1] tracking-tight text-gray-900">
                        {!! $heroContent !!}
                    </h1>
                </div>

                <!-- Compact trust signals -->
                <div class="flex flex-wrap items-center gap-4 text-xs sm:text-sm">
                    <div class="flex items-center gap-1.5 text-gray-700">
                        <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span class="font-medium">{{ \App\Models\Product::count() }}+ Produkte</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-gray-700">
                        <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                        </svg>
                        <span class="font-medium">Unabhängig</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-gray-700">
                        <svg class="w-4 h-4 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/>
                        </svg>
                        <span class="font-medium">Direkt vergleichen</span>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                    <a href="{{ route('produkte.top') }}" class="cta-button inline-block px-6 py-3.5 text-white font-semibold rounded-xl shadow-sm w-full sm:w-auto text-center text-sm sm:text-base">
                        Top 5 ansehen
                    </a>
                    <a href="{{ route('produkte.index') }}" class="cta-button-secondary inline-block px-6 py-3.5 font-semibold rounded-xl w-full sm:w-auto text-center text-sm sm:text-base">
                        Alle Produkte
                    </a>
                </div>
            </div>
        </div>
    </div>

</section>

<!-- TOP 10 LIST -->
<section id="top-10-list" class="w-full pt-8 sm:pt-12 pb-16 sm:pb-20 lg:pb-24 bg-white">
    <div class="max-w-7xl mx-auto px-6 sm:px-8">
        <div class="text-center mb-10 sm:mb-14 space-y-4">
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight text-gray-900">
                Top 10 Beste Produkte
            </h2>
            <p class="text-gray-600 text-lg max-w-2xl mx-auto">
                Die beliebtesten Optionen auf einen Blick. Vergleichen Sie direkt und finden Sie, was zu Ihnen passt.
            </p>
            <div class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 rounded-full border border-gray-200">
                <svg class="w-3.5 h-3.5 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                </svg>
                <span class="text-sm font-medium text-gray-700">Täglich aktualisiert</span>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
            @foreach($top10Products as $index => $product)
                @php
                    $affiliateLink = getProductAffiliateLink($product);
                    $savings = ($product->strikethrough_price && $product->price)
                        ? $product->strikethrough_price - $product->price
                        : 0;
                    $rating = $product->rating_average ?? 0;
                    $fullStars = floor($rating);
                    $position = $index + 1;
                    $isLast = $loop->last;
                @endphp

                <div class="bg-white hover:bg-gray-50 transition-all duration-200 {{ !$isLast ? 'border-b border-gray-100' : '' }} relative group cursor-pointer">
                    <!-- Mobile Layout (< 768px) -->
                    <a href="{{ route('produkte.show', $product->slug) }}" class="absolute inset-0 z-0"></a>
                    <div class="md:hidden p-4 relative pointer-events-none">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="flex-shrink-0 w-10 h-10 rounded-xl flex items-center justify-center text-gray-900 font-black text-lg bg-gray-100 border-2 border-gray-200">
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

                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-20 h-20 flex items-center justify-center p-1">
                                <img src="{{ $product->image_url ?? 'https://via.placeholder.com/100x100?text=Kein+Bild' }}"
                                     alt="{{ $product->title }}"
                                     class="max-w-full max-h-full object-contain">
                            </div>
                            <div class="flex-1">
                                @if($rating > 0)
                                    <div class="mb-2 flex-wrap">
                                        <x-rating :stars="$rating" :count="$product->rating_count" size="xs" class="text-xs" />
                                        @if($product->review)
                                            <span class="bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded-full font-semibold text-[9px]">Review</span>
                                        @endif
                                        @if($product->blogPosts->isNotEmpty())
                                            <span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full font-semibold text-[9px]">Blog</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="flex gap-2 items-center pointer-events-auto">
                            <label class="flex items-center gap-1 cursor-pointer group" title="Dieses Produkt vergleichen">
                                <input
                                    type="checkbox"
                                    class="compare-checkbox hidden"
                                    data-ean="{{ $product->ean }}"
                                    aria-label="Wählen Sie aus, um {{ $product->title }} zu vergleichen" />
                                <div class="compare-icon w-10 h-10 flex items-center justify-center border-2 border-gray-200 rounded-xl hover:border-gray-300 transition bg-white">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18m-4 4l4-4m0 0l-4-4"/>
                                    </svg>
                                </div>
                            </label>
                            <a href="{{ route('produkte.show', $product->slug) }}"
                               class="flex-1 bg-white hover:bg-gray-50 text-gray-900 text-sm font-semibold py-3 px-4 rounded-xl text-center transition border-2 border-gray-200">
                                Details
                            </a>
                            <a href="{{ $affiliateLink }}" target="_blank" rel="nofollow sponsored"
                               class="flex-1 cta-button text-white text-sm font-semibold py-3 px-4 rounded-xl text-center transition shadow-sm leading-tight">
                                Preis prüfen
                            </a>
                        </div>
                    </div>

                    <!-- Desktop Layout (>= 768px) -->
                    <div class="hidden md:grid md:grid-cols-12 gap-4 p-5 items-center relative pointer-events-none">
                        <!-- Position -->
                        <div class="col-span-1 flex justify-center">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center text-gray-900 font-black text-xl bg-gray-100 border-2 border-gray-200">
                                {{ $position }}
                            </div>
                        </div>

                        <!-- Image -->
                        <div class="col-span-2 flex items-center justify-center">
                            <div class="w-16 h-16 flex items-center justify-center">
                                <img src="{{ $product->image_url ?? 'https://via.placeholder.com/100x100?text=Kein+Bild' }}"
                                     alt="{{ $product->title }}"
                                     class="max-w-full max-h-full object-contain">
                            </div>
                        </div>

                        <!-- Product Info -->
                        <div class="col-span-4">
                            @if($product->brand)
                                <p class="text-xs text-gray-500 font-medium mb-0.5">{{ $product->brand }}</p>
                            @endif
                            <h3 class="font-bold text-sm text-gray-900 line-clamp-2 leading-tight mb-1">
                                {{ $product->title }}
                            </h3>
                            @if($rating > 0)
                                <div class="flex items-center gap-1 flex-wrap">
                                    <x-rating :stars="$rating" :count="$product->rating_count" size="xs" class="text-xs" />
                                    @if($product->review)
                                        <span class="bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded-full font-semibold border border-emerald-200 text-[9px]">Review</span>
                                    @endif
                                    @if($product->blogPosts->isNotEmpty())
                                        <span class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full font-semibold border border-blue-200 text-[9px]">Blog</span>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <!-- Actions -->
                        <div class="col-span-5 flex gap-2 items-center pointer-events-auto">
                            <label class="flex items-center gap-1 cursor-pointer group" title="Dieses Produkt vergleichen">
                                <input
                                    type="checkbox"
                                    class="compare-checkbox hidden"
                                    data-ean="{{ $product->ean }}"
                                    aria-label="Wählen Sie aus, um {{ $product->title }} zu vergleichen" />
                                <div class="compare-icon w-10 h-10 flex items-center justify-center border-2 border-gray-200 rounded-xl hover:border-gray-300 transition flex-shrink-0 bg-white">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18m-4 4l4-4m0 0l-4-4"/>
                                    </svg>
                                </div>
                            </label>
                            <a href="{{ route('produkte.show', $product->slug) }}"
                               class="flex-1 bg-white hover:bg-gray-50 text-gray-900 text-sm font-semibold py-2.5 px-3 rounded-xl text-center transition border-2 border-gray-200">
                                Details
                            </a>
                            <a href="{{ $affiliateLink }}" target="_blank" rel="nofollow sponsored"
                               class="flex-1 cta-button text-white text-sm font-semibold py-2.5 px-3 rounded-xl text-center transition shadow-sm leading-tight">
                                Jetzt kaufen
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Link to all products -->
        <div class="text-center mt-10">
            <a href="{{ route('produkte.index') }}"
               class="inline-flex items-center gap-2 text-gray-900 hover:text-gray-600 font-semibold text-base transition group">
                Alle Produkte ansehen
                <svg class="w-5 h-5 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                </svg>
            </a>
        </div>
    </div>
</section>

<!-- VERGELIJK KNOP -->
<div id="compare-bar" class="fixed bottom-6 right-6 z-50 hidden">
    <div class="flex items-center gap-3 bg-white rounded-2xl shadow-2xl border border-gray-200 p-2">
        <button
            id="compare-button"
            disabled
            class="cta-button text-white text-sm font-semibold py-3 px-6 rounded-xl flex items-center gap-2 transition focus:outline-none focus:ring-2 focus:ring-offset-2 opacity-50 cursor-not-allowed"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M7 16l-4-4m0 0l4-4m-4 4h18m-4 4l4-4m0 0l-4-4"/>
            </svg>
            Vergleichen <span id="compare-count" class="font-bold">0/3</span>
        </button>

        <button
            id="clear-selection"
            class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold py-3 px-5 rounded-xl transition focus:outline-none"
            type="button"
            title="Alle ausgewählten Produkte löschen"
        >
            Löschen
        </button>
    </div>
</div>

<!-- INFO (STRUCTURED + HTML FALLBACK) -->
<section class="w-full py-16 sm:py-20 lg:py-24 px-6 sm:px-8 bg-white">
    @if(hasStructuredContent('homepage.info'))
        {{-- STRUCTURED MODE: Two-column layout (text left, button right) --}}
        <div class="max-w-7xl mx-auto">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                {{-- Tekst links --}}
                <div>
                    <h2 class="text-3xl sm:text-4xl font-bold mb-6 text-gray-900">{!! getContent('homepage.info.title') !!}</h2>
                    <div class="prose prose-gray prose-lg max-w-none">
                        <p class="text-lg text-gray-700">{!! getContent('homepage.info.text') !!}</p>
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
            {!! getContent('homepage.info') !!}
        </div>
    @endif
</section>

<!-- SEO BLOK 1 (STRUCTURED + HTML FALLBACK) -->
<section class="w-full bg-gray-100 text-gray-900 py-16 sm:py-20 lg:py-24 px-6 sm:px-8">
    @if(hasStructuredContent('homepage.seo1'))
        {{-- STRUCTURED MODE: Simple title + text layout --}}
        <div class="max-w-7xl mx-auto">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                {{-- Button links --}}
                <div class="order-2 lg:order-1 flex justify-center lg:justify-start">
                    <a href="{{ route('produkte.index') }}" class="cta-button inline-block px-8 py-4 text-white font-semibold rounded-xl shadow-lg transition hover:scale-105">
                        Entdecken Sie alle {{ $niche }}
                    </a>
                </div>
                {{-- Tekst rechts --}}
                <div class="order-1 lg:order-2">
                    <h2 class="text-3xl sm:text-4xl font-bold mb-6">{!! getContent('homepage.seo1.title') !!}</h2>
                    <p class="text-lg mb-6 text-gray-700">{!! getContent('homepage.seo1.intro') !!}</p>

                    <div class="space-y-6">
                        <div>
                            <h3 class="text-xl font-semibold mb-2">{!! getContent('homepage.seo1.section1_title') !!}</h3>
                            <p class="text-gray-700">{!! getContent('homepage.seo1.section1_text') !!}</p>
                        </div>
                        <div>
                            <h3 class="text-xl font-semibold mb-2">{!! getContent('homepage.seo1.section2_title') !!}</h3>
                            <p class="text-gray-700">{!! getContent('homepage.seo1.section2_text') !!}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        {{-- FALLBACK: HTML MODE (backwards compatible) --}}
        <div class="max-w-4xl mx-auto prose prose-gray prose-lg">
            {!! getContent('homepage.seo1') !!}
        </div>
    @endif
</section>

<!-- FAQ BLOK (STRUCTURED + HTML FALLBACK) -->
@php
    $faqKeys = ['homepage.faq_1', 'homepage.faq_2', 'homepage.faq_3'];
    $faqs = collect();

    // Try structured content first
    foreach ($faqKeys as $key) {
        if (hasStructuredContent($key)) {
            // Structured mode: get .question and .answer (raw, no HTML wrapper)
            $question = \App\Models\ContentBlock::where('key', "{$key}.question")->value('content');
            $answer = \App\Models\ContentBlock::where('key', "{$key}.answer")->value('content');
            if (!empty($question) && !empty($answer)) {
                $faqs->push(['question' => $question, 'answer' => $answer]);
            }
        } else {
            // Fallback: HTML mode (old format)
            $block = \App\Models\ContentBlock::where('key', $key)->value('content');
            if ($block) {
                $parts = explode('</h3>', $block, 2);
                if (count($parts) === 2) {
                    $question = strip_tags($parts[0]);
                    $answer = $parts[1];
                    $faqs->push(['question' => $question, 'answer' => $answer]);
                }
            }
        }
    }
@endphp

@if($faqs->count())
<section class="w-full py-16 sm:py-20 lg:py-24 px-6 sm:px-8 bg-white text-gray-900">
    <div class="max-w-7xl mx-auto space-y-12">
        <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black text-center tracking-tight text-gray-900">
            Häufig gestellte Fragen
        </h2>
        <div class="space-y-4">
            @foreach($faqs as $faq)
                <div x-data="{ open: false }" class="bg-white border-2 border-gray-200 rounded-2xl overflow-hidden hover:border-gray-300 transition">
                    <button @click="open = !open"
                            class="flex justify-between items-center w-full text-left px-6 py-5 text-lg font-semibold text-gray-900"
                            aria-expanded="false">
                        {{ $faq['question'] }}
                        <svg :class="{'rotate-180': open}" class="w-5 h-5 flex-shrink-0 ml-4 text-gray-500 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-2"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 -translate-y-2"
                         class="px-6 pb-5 text-gray-600 leading-relaxed">
                        {!! $faq['answer'] !!}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<!-- SEO BLOK 2 (STRUCTURED + HTML FALLBACK) -->
<section class="w-full py-16 sm:py-20 lg:py-24 px-6 sm:px-8 bg-white text-gray-900">
    @if(hasStructuredContent('homepage.seo2'))
        {{-- STRUCTURED MODE: Simple title + text layout --}}
        <div class="max-w-7xl mx-auto">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                {{-- Tekst links --}}
                <div>
                    <h2 class="text-3xl sm:text-4xl font-bold mb-6">{!! getContent('homepage.seo2.title', ['Monat' => $huidigeMaand]) !!}</h2>
                    <p class="text-lg mb-6 text-gray-700">{!! getContent('homepage.seo2.intro') !!}</p>

                    <div class="space-y-6">
                        <div>
                            <h3 class="text-xl font-semibold mb-2">{!! getContent('homepage.seo2.section1_title') !!}</h3>
                            <p class="text-gray-700">{!! getContent('homepage.seo2.section1_text') !!}</p>
                        </div>
                        <div>
                            <h3 class="text-xl font-semibold mb-2">{!! getContent('homepage.seo2.section2_title') !!}</h3>
                            <p class="text-gray-700">{!! getContent('homepage.seo2.section2_text') !!}</p>
                        </div>
                        <div>
                            <h3 class="text-xl font-semibold mb-2">{!! getContent('homepage.seo2.section3_title') !!}</h3>
                            <p class="text-gray-700">{!! getContent('homepage.seo2.section3_text') !!}</p>
                        </div>
                    </div>
                </div>
                {{-- Button rechts --}}
                <div class="flex justify-center lg:justify-end">
                    <a href="{{ route('produkte.index') }}" class="cta-button inline-block px-8 py-4 text-white font-semibold rounded-xl shadow-lg transition hover:scale-105">
                        Mit dem Filtern beginnen
                    </a>
                </div>
            </div>
        </div>
    @else
        {{-- FALLBACK: HTML MODE --}}
        <div class="max-w-4xl mx-auto prose prose-lg">
            {!! getContent('homepage.seo2', ['Monat' => $huidigeMaand]) !!}
        </div>
    @endif
</section>

@endsection

@php
    $siteName = getSetting('site_name', config('app.name'));
    $siteUrl = config('app.url');
    $favicon = getSetting('favicon_url', asset('favicon.png'));
@endphp

@section('structured-data')
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@graph": [
        {
            "@type": "WebSite",
            "@id": "{{ $siteUrl }}/#website",
            "url": "{{ $siteUrl }}",
            "name": "{{ $siteName }}",
            "description": "{{ $metaDesc }}",
            "inLanguage": "de-DE",
            "potentialAction": {
                "@type": "SearchAction",
                "target": "{{ $siteUrl }}/suchen?q={search_term_string}",
                "query-input": "required name=search_term_string"
            }
        },
        {
            "@type": "Organization",
            "@id": "{{ $siteUrl }}/#organization",
            "name": "{{ $siteName }}",
            "url": "{{ $siteUrl }}",
            "logo": {
                "@type": "ImageObject",
                "url": "{{ $favicon }}"
            }
        }
        @if($faqs->count())
        ,
        {
            "@type": "FAQPage",
            "mainEntity": [
                @foreach($faqs as $faq)
                    {
                        "@type": "Question",
                        "name": {!! json_encode($faq['question']) !!},
                        "acceptedAnswer": {
                            "@type": "Answer",
                            "text": {!! json_encode(strip_tags($faq['answer'])) !!}
                        }
                    }@if(!$loop->last),@endif
                @endforeach
            ]
        }
        @endif
    ]
}
</script>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Smooth scroll for anchor links
        document.querySelectorAll('.scroll-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });

        // Compare functionality
        const checkboxes = document.querySelectorAll('.compare-checkbox');
        const bar = document.getElementById('compare-bar');
        const btn = document.getElementById('compare-button');
        const counter = document.getElementById('compare-count');
        const clearBtn = document.getElementById('clear-selection');

        function updateBar() {
            const selected = [...checkboxes].filter(cb => cb.checked);
            counter.textContent = `${selected.length}/3`;

            bar.classList.toggle('hidden', selected.length < 1);

            btn.disabled = selected.length < 2;
            btn.classList.toggle('opacity-50', btn.disabled);
            btn.classList.toggle('cursor-not-allowed', btn.disabled);

            btn.dataset.compareEans = selected.map(cb => cb.dataset.ean).join(',');
        }

        checkboxes.forEach(cb => {
            cb.addEventListener('change', function () {
                const selected = [...checkboxes].filter(cb => cb.checked);
                if (selected.length > 3) {
                    cb.checked = false;
                    alert('Sie können maximal 3 Produkte vergleichen.');
                    return;
                }

                // Update visual state of icon
                const icon = cb.closest('label').querySelector('.compare-icon');
                if (cb.checked) {
                    icon.style.backgroundColor = '#111827';
                    icon.style.borderColor = '#111827';
                    icon.querySelector('svg').style.color = 'white';
                } else {
                    icon.style.backgroundColor = '';
                    icon.style.borderColor = '';
                    icon.querySelector('svg').style.color = '';
                }

                updateBar();
            });
        });

        btn.addEventListener('click', () => {
            const selectedEans = [...checkboxes]
                .filter(cb => cb.checked)
                .map(cb => cb.dataset.ean);

            if (selectedEans.length >= 2) {
                window.location.href = `/vergleichen?eans=${selectedEans.join(',')}`;
            } else {
                alert('Wählen Sie mindestens zwei Produkte zum Vergleichen aus.');
            }
        });

        clearBtn.addEventListener('click', () => {
            checkboxes.forEach(cb => {
                cb.checked = false;
                const icon = cb.closest('label').querySelector('.compare-icon');
                icon.style.backgroundColor = '';
                icon.style.borderColor = '';
                icon.querySelector('svg').style.color = '';
            });
            updateBar();
        });

        updateBar();
    });

</script>
@endsection
