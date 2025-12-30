@extends('layouts.app')

{{-- Meta tags worden automatisch gegenereerd via layouts/app.blade.php --}}

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        'Produkte' => route('produkte.index'),
    ]" />
@endsection

@section('content')
@include('components.banners.black-friday')

@php
    $heroImage = getImage('Produkte.index'); 
    $hasPageImage = !empty($heroImage);
    $primaryColor = getSetting('primary_color', '#7c3aed');
        // Black Friday flag (per site via settings) + preview via URL (?bf=on / ?bf=off)
        $bfFlag    = (bool) getSetting('bf_active', false);
    $bfStart   = getSetting('bf_start');    // bijv. '2025-11-24'
    $bfUntil   = getSetting('bf_until');    // bijv. '2025-12-02'
    $previewOn = in_array(strtolower((string) request('bf')), ['1','on','true'], true);
    $today     = \Carbon\Carbon::today('Europe/Amsterdam');
    $inWindow  = ($bfStart && $bfUntil)
        ? $today->between(
            \Carbon\Carbon::parse($bfStart, 'Europe/Amsterdam'),
            \Carbon\Carbon::parse($bfUntil, 'Europe/Amsterdam')
          )
        : false;

    $bfActive  = $previewOn || $bfFlag || $inWindow;
@endphp

<style>
    :root {
        --primary-color: {{ $primaryColor }};
        --primary-dark: color-mix(in srgb, {{ $primaryColor }} 20%, #000 80%);
    }

    .product-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 1rem;
    }

    .product-card:hover {
        border-color: #d1d5db;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
        transform: translateY(-2px);
    }

    .cta-primary {
        background-color: var(--primary-color) !important;
        color: white !important;
        text-decoration: none !important;
    }

    .cta-primary:hover {
        background-color: color-mix(in srgb, var(--primary-color) 90%, #000 10%) !important;
        color: white !important;
        text-decoration: none !important;
    }

    .cta-primary:focus {
        outline: none !important;
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.3) !important;
    }

    .product-card a {
        text-decoration: none !important;
    }

    .product-card a:hover {
        text-decoration: none !important;
    }

    .product-card .line-clamp-1 {
        display: -webkit-box;
        -webkit-line-clamp: 1;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .product-card .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    /* Apply primary-dark to text colors (but not buttons) */
    h1.text-gray-900, h2.text-gray-900, h3.text-gray-900, p.text-gray-900, span.text-gray-900 {
        color: var(--primary-dark);
    }

    h1.text-gray-800, h2.text-gray-800, h3.text-gray-800, p.text-gray-800, span.text-gray-800 {
        color: var(--primary-dark);
    }

    p.text-gray-700, span.text-gray-700 {
        color: color-mix(in srgb, {{ $primaryColor }} 15%, #000 75%);
    }

    p.text-gray-600, span.text-gray-600 {
        color: color-mix(in srgb, {{ $primaryColor }} 12%, #000 65%);
    }

    @media (max-width: 640px) {
        .product-card {
            padding: 1rem;
        }
    }
</style>

<!-- ===== HEADER + FILTERS SECTION ===== -->
<section id="filters" class="w-full bg-gradient-to-b from-white via-gray-50 to-white pt-24 pb-6">
    <div class="max-w-7xl mx-auto px-6 sm:px-8">
        <form method="GET" action="{{ route('produkte.index') }}#filters">
            <!-- Titel + Filter -->
            <div class="flex flex-col lg:flex-row lg:items-start gap-6 mb-4">
                <!-- Titel linksboven -->
                <div class="lg:w-1/4">
                    <h1 class="text-2xl sm:text-3xl font-bold leading-tight mb-1">
                        @if(hasStructuredContent('Produkte_index_hero_titel'))
                            {!! getContent('Produkte_index_hero_titel.title') !!}
                        @else
                            {!! getContent('Produkte_index_hero_titel', ['fallback' => 'Alle Produkte']) !!}
                        @endif
                    </h1>
                    <p class="text-sm text-gray-600">
                        Filtern. Vergleichen. Die beste Option finden.
                    </p>
                </div>

                <!-- Alle Filter rechtsboven -->
                <div class="lg:w-3/4">
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-4">
                        <!-- Zoekveld -->
                        <div>
                            <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Suchen..."
                                class="w-full border border-gray-300 rounded-lg shadow-sm focus:ring focus:ring-opacity-50 px-4 py-2 text-sm"
                                style="focus:ring-color: var(--primary-color);">
                        </div>

                        <!-- Sortieren -->
                        <div>
                            <select name="sort" id="sort"
                                class="w-full border border-gray-300 rounded-lg shadow-sm focus:ring focus:ring-opacity-50 px-4 py-2 text-sm">
                                <option value="">Sortieren op...</option>
                                <option value="popular" {{ request('sort') == 'popular' ? 'selected' : '' }}>Beliebtste</option>
                                <option value="price_asc" {{ request('sort') == 'price_asc' ? 'selected' : '' }}>Preis: Laag → Hoog</option>
                                <option value="price_desc" {{ request('sort') == 'price_desc' ? 'selected' : '' }}>Preis: Hoog → Laag</option>
                                <option value="rating" {{ request('sort') == 'rating' ? 'selected' : '' }}>Hoogste beoordeling</option>
                            </select>
                        </div>

                        <!-- Merken -->
                        <div>
                            <select name="brand" id="brand"
                                class="w-full border border-gray-300 rounded-lg shadow-sm focus:ring focus:ring-opacity-50 px-4 py-2 text-sm">
                                <option value="">Alle merken</option>
                                @foreach($merken as $merk)
                                    <option value="{{ $merk }}" {{ request('brand') == $merk ? 'selected' : '' }}>{{ $merk }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Min Preis -->
                        <div>
                            <input type="number" name="min_price" id="min_price" value="{{ request('min_price') }}" min="0" placeholder="Min Preis (€)"
                                class="w-full border border-gray-300 rounded-lg shadow-sm focus:ring focus:ring-opacity-50 px-4 py-2 text-sm">
                        </div>

                        <!-- Max Preis -->
                        <div>
                            <input type="number" name="max_price" id="max_price" value="{{ request('max_price') }}" min="0" placeholder="Max Preis (€)"
                                class="w-full border border-gray-300 rounded-lg shadow-sm focus:ring focus:ring-opacity-50 px-4 py-2 text-sm">
                        </div>
                    </div>

                    <!-- Buttons + Checkbox onder de filters -->
                    <div class="flex flex-wrap items-center gap-4">
                        <label class="inline-flex items-center space-x-2">
                            <input type="checkbox" name="discount" value="1" {{ request('discount') ? 'checked' : '' }}
                                class="border-gray-300 rounded w-4 h-4"
                                style="accent-color: var(--primary-color);">
                            <span class="text-sm font-medium text-gray-700">Alleen aanbiedingen</span>
                        </label>

                        <button type="submit"
                            class="cta-primary text-white font-semibold py-2 px-6 rounded-lg shadow-sm hover:shadow-md transition-all duration-200 text-sm">
                            Toepassen
                        </button>

                        <a href="{{ route('produkte.index') }}#filters"
                           class="text-sm font-medium hover:underline transition-colors"
                           style="color: var(--primary-color);">
                            Wis filters
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>

<div id="start-vergelijken" class="scroll-mt-28">

<!-- HOE TE VERGELIJKEN -->
<section class="w-full px-4 sm:px-10 lg:px-12 py-5 bg-gradient-to-r from-blue-50 via-indigo-50 to-purple-50 border-y border-blue-100">
    <div class="max-w-5xl mx-auto flex items-center justify-center gap-3 text-center">
        <svg class="w-6 h-6 text-blue-600 flex-shrink-0 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
        </svg>
        <p class="text-sm sm:text-base text-gray-800 font-semibold">
            Twijfel tussen meerdere opties? <span class="text-blue-700">Vink 2-3 Produkte aan</span> om ze naast elkaar te vergelijken
        </p>
    </div>
</section>

<!-- PRODUCTGRID -->
<section id="Produkte" class="w-full py-16 px-4 sm:px-10 lg:px-12 bg-white">
    <div class="grid grid-cols-1 min-[480px]:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6">
        @forelse($products as $product)
            @php
                $affiliateLink = getBolAffiliateLink($product->url, $product->title);
            @endphp

            @php
                $savings = ($product->strikethrough_price && $product->price) 
                    ? $product->strikethrough_price - $product->price 
                    : 0;
                $rating = $product->rating_average ?? 0;
                $fullStars = floor($rating);
                $hasHalfStar = ($rating - $fullStars) >= 0.5;
            @endphp

            <div class="product-card p-6 flex flex-col h-full relative transition-all duration-200">
                <!-- ✅ Checkbox rechtsboven -->
                <input type="checkbox" 
                        class="compare-checkbox absolute top-3 right-3 w-6 h-6 sm:w-5 sm:h-5 accent-blue-600 rounded z-10"
                        data-ean="{{ $product->ean }}" 
                        title="Vergleichen dit product"
                        aria-label="Vink aan om {{ $product->title }} te vergelijken" />

                @if($savings > 0)
                    @if($bfActive)
                        <span class="absolute top-3 left-3 rounded-lg px-2.5 py-1 text-[10px] font-bold shadow-md text-black border border-yellow-500"
                            style="background: linear-gradient(135deg,#fff7cc 0%,#ffe38f 25%,#ffd766 50%,#e9b22d 75%,#fff7cc 100%);">
                            Black Friday
                        </span>
                    @else
                        <span class="absolute top-3 left-3 bg-red-600 text-white text-[10px] px-2.5 py-1 rounded-lg font-bold shadow-md">
                            Angebot
                        </span>
                    @endif
                @endif

                <!-- Afbeelding -->
                <div class="w-full h-28 sm:h-36 flex items-center justify-center mb-4">
                    <img src="{{ $product->image_url ?? 'https://via.placeholder.com/300x300?text=Geen+Afbeelding' }}"
                         alt="{{ $product->title }} - {{ $product->brand ?? 'Product' }} - €{{ number_format($product->price ?? 0, 2, ',', '.') }}"
                         class="max-h-full max-w-full object-contain" loading="lazy">
                </div>

                <!-- Merk -->
                @if($product->brand)
                    <p class="text-xs text-gray-500 font-medium mb-1 text-center">{{ $product->brand }}</p>
                @endif

                <!-- Titel -->
                <h3 class="text-sm font-bold text-gray-900 text-center mb-2 min-h-[2.5rem] leading-tight">
                    {{ Str::limit($product->title, 70, '...') }}
                </h3>

                <!-- Rating -->
                @if($rating > 0)
                    <x-rating :stars="$rating" :count="$product->rating_count" size="sm" class="justify-center mb-3" />
                @endif

                <!-- Labels -->
                <div class="flex justify-center flex-wrap gap-1.5 text-[10px] mb-3">
                    @if($product->review)
                        <span class="bg-emerald-100 text-emerald-700 px-2.5 py-1 rounded-full font-semibold border border-emerald-200">Review</span>
                    @endif
                    @if($product->blogPosts->isNotEmpty())
                        <span class="bg-blue-100 text-blue-700 px-2.5 py-1 rounded-full font-semibold border border-blue-200">Blog</span>
                    @endif
                </div>

                <!-- Preis -->
                <div class="text-center mb-4">
                    @if($savings > 0)
                        <div class="inline-block text-white px-3 py-1.5 rounded-lg font-bold text-xs mb-2" style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);">
                            Sparen €{{ number_format($savings, 2, ',', '.') }}
                        </div>
                    @endif
                    <div class="flex items-baseline justify-center gap-2">
                        <p class="text-gray-900 font-black text-xl">€{{ number_format($product->price ?? 0, 2, ',', '.') }}</p>
                        @if($savings > 0)
                            <p class="text-gray-400 text-sm line-through">€{{ number_format($product->strikethrough_price, 2, ',', '.') }}</p>
                        @endif
                    </div>
                </div>

                <!-- Knoppen -->
                <div class="mt-auto flex flex-col gap-2">
                    <a href="{{ $affiliateLink }}" target="_blank" rel="nofollow sponsored noopener"
                       class="cta-primary text-white text-sm font-semibold py-3 px-4 rounded-xl text-center transition-all duration-200 shadow-sm">
                        Ansehen op bol.com
                    </a>
                    <a href="{{ route('produkte.show', $product->slug) }}"
                       class="bg-white hover:bg-gray-50 text-gray-900 text-sm font-semibold py-3 px-4 rounded-xl text-center transition-all duration-200 border-2 border-gray-200 hover:border-gray-300 no-underline">
                        Ansehen product
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-16">
                <img src="https://media.giphy.com/media/3og0IPxMM0erATueVW/giphy.gif" alt="Niks gevonden" class="mx-auto mb-6 w-48 h-auto">
                <h3 class="text-2xl font-bold text-gray-800 mb-2">Geen Produkte gevonden</h3>
                <p class="text-gray-600 max-w-xl mx-auto">Geen resultaat. Wis filters of probeer andere zoektermen.</p>
            </div>
        @endforelse
    </div>

    @if($products->count())
        <div class="mt-12 text-center">
            {{ $products->appends(request()->query())->links() }}
        </div>
    @endif
</section>

<!-- INFO BLOKKEN (STRUCTURED + HTML FALLBACK) -->
<section class="w-full bg-white py-16 px-6 sm:px-8">
    <div class="max-w-4xl mx-auto space-y-12">
        {{-- INFO BLOK 1 - No button needed (/Produkte is eindstation) --}}
        <div>
            @if(hasStructuredContent('Produkte_index_info_blok_1'))
                <h2 class="text-3xl font-bold mb-4 text-gray-900">{!! getContent('Produkte_index_info_blok_1.title') !!}</h2>
                <div class="prose prose-gray prose-lg">
                    <p>{!! getContent('Produkte_index_info_blok_1.text') !!}</p>
                </div>
            @else
                <div class="prose prose-gray prose-lg">
                    {!! getContent('Produkte_index_info_blok_1', ['fallback' => '<p>Informatie volgt binnenkort.</p>']) !!}
                </div>
            @endif
        </div>

        {{-- INFO BLOK 2 - No button needed (/Produkte is eindstation) --}}
        <div>
            @if(hasStructuredContent('Produkte_index_info_blok_2'))
                <h2 class="text-3xl font-bold mb-4 text-gray-900">{!! getContent('Produkte_index_info_blok_2.title') !!}</h2>
                <div class="prose prose-gray prose-lg">
                    <p>{!! getContent('Produkte_index_info_blok_2.text') !!}</p>
                </div>
            @else
                <div class="prose prose-gray prose-lg">
                    {!! getContent('Produkte_index_info_blok_2', ['fallback' => '<p>Meer info wordt aangevuld.</p>']) !!}
                </div>
            @endif
        </div>
    </div>
</section>

<!-- VERGELIJK KNOP -->
<div id="compare-bar" class="fixed bottom-4 right-4 z-50 hidden">
    <div class="flex items-center gap-3">
        <!-- Vergleichen knop -->
        <button
            id="compare-button"
            disabled
            style="background-color: var(--primary-color);"
            class="hover:brightness-90 text-white text-sm font-semibold py-2 px-4 rounded-full shadow-lg flex items-center gap-2 transition focus:outline-none focus:ring-2 focus:ring-offset-2 opacity-50 cursor-not-allowed"
        >
            <!-- Duidelijke dubbele pijl: ↔ -->
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M7 16l-4-4m0 0l4-4m-4 4h18m-4 4l4-4m0 0l-4-4"/>
                </svg>
            Vergleichenen <span id="compare-count">0/3</span>
        </button>

        <!-- ✅ Wis selectie knop -->
        <button
            id="clear-selection"
            class="bg-red-600 hover:bg-red-700 text-white text-sm font-semibold py-2 px-4 rounded-full shadow transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
            type="button"
            title="Wis alle geselecteerde Produkte"
        >
            Wis selectie
        </button>
    </div>
</div>

@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Smooth scroll
        document.querySelectorAll('.scroll-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });

        const checkboxes = document.querySelectorAll('.compare-checkbox');
        const bar = document.getElementById('compare-bar');
        const btn = document.getElementById('compare-button');
        const counter = document.getElementById('compare-count');
        const clearBtn = document.getElementById('clear-selection');

        // ✅ Lees bestaande EANs uit de URL (bij terugkeren naar index)
        const bestaandeEans = (new URLSearchParams(window.location.search)).get('eans')?.split(',') || [];

        // ✅ Vink bestaande checkboxen aan bij load
        bestaandeEans.forEach(ean => {
            const cb = document.querySelector(`.compare-checkbox[data-ean="${ean}"]`);
            if (cb) cb.checked = true;
        });

        // ✅ Update compare bar
        function updateBar() {
            const selected = [...checkboxes].filter(cb => cb.checked);
            counter.textContent = `${selected.length}/3`;

            bar.classList.toggle('hidden', selected.length < 1);

            btn.disabled = selected.length < 2;
            btn.classList.toggle('opacity-50', btn.disabled);
            btn.classList.toggle('cursor-not-allowed', btn.disabled);

            btn.dataset.compareEans = selected.map(cb => cb.dataset.ean).join(',');
        }

        // ✅ Checkbox click handler met max 3
        checkboxes.forEach(cb => {
            cb.addEventListener('change', function () {
                const selected = [...checkboxes].filter(cb => cb.checked);
                if (selected.length > 3) {
                    cb.checked = false;
                    alert('Je kunt maximaal 3 Produkte vergelijken.');
                    return;
                }
                updateBar();
            });
        });

        // ✅ "Vergleichen" knop
        btn.addEventListener('click', () => {
            const selectedEans = [...checkboxes]
                .filter(cb => cb.checked)
                .map(cb => cb.dataset.ean);

            // ✅ Combineer zonder dubbele entries
            const combined = Array.from(new Set([...bestaandeEans, ...selectedEans]));

            // ✅ Corrigeer dubbele telling door uniek te houden
            if (combined.length > 3) {
                alert('Je kunt maximaal 3 Produkte vergelijken.');
                return;
            }

            if (combined.length >= 2) {
                window.location.href = `/vergelijken?eans=${combined.join(',')}`;
            } else {
                alert('Selecteer minimaal twee Produkte om te vergelijken.');
            }
        });


        // ✅ "Wis selectie" knop
        clearBtn.addEventListener('click', () => {
            checkboxes.forEach(cb => cb.checked = false);
            updateBar();
        });

        updateBar(); // Init bij pageload
    });
</script>
@endsection

@push('pagination-meta')
    @if(isset($products) && $products instanceof \Illuminate\Contracts\Pagination\Paginator)
        @if($products->currentPage() > 1)
            <link rel="prev" href="{{ $products->previousPageUrl() }}" />
        @endif
        @if($products->hasMorePages())
            <link rel="next" href="{{ $products->nextPageUrl() }}" />
        @endif
    @endif
@endpush

@section('structured-data')
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "CollectionPage",
    "name": "{{ getSetting('site_niche', 'Produkte') }} - {{ getSetting('site_name') }}",
    "description": "Vergleichen en koop de beste {{ getSetting('site_niche', 'Produkte') }}. Filter op Preis, merk en Merkmale.",
    "mainEntity": {
        "@type": "ItemList",
        "numberOfItems": {{ $products->total() }},
        "itemListElement": [
            @foreach($products as $product)
            {
                "@type": "ListItem",
                "position": {{ $loop->iteration }},
                "item": {
                    "@type": "Product",
                    "name": "{{ $product->title }}",
                    "image": "{{ $product->image_url }}",
                    "description": "{{ Str::limit(strip_tags($product->description ?? ''), 200) }}",
                    @if($product->brand)
                    "brand": {
                        "@type": "Brand",
                        "name": "{{ $product->brand }}"
                    },
                    @endif
                    "offers": {
                        "@type": "Offer",
                        "price": "{{ $product->price }}",
                        "priceCurrency": "EUR",
                        "availability": "https://schema.org/InStock",
                        "url": "{{ $product->url }}"
                    },
                    @if($product->rating_average)
                    "aggregateRating": {
                        "@type": "AggregateRating",
                        "ratingValue": "{{ $product->rating_average }}",
                        "reviewCount": "{{ $product->rating_count ?? 1 }}"
                    },
                    @endif
                    "url": "{{ route('produkte.show', $product->slug) }}"
                }
            }@if(!$loop->last),@endif
            @endforeach
        ]
    }
}
</script>
@endsection