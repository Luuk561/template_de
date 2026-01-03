@extends('layouts.app')

@section('title', strip_tags($product->seo_title))
@section('meta_description', strip_tags($product->seo_description) ?: 'Ansehen dit product en ontdek of het past bij jouw situatie.')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        'Produkte' => route('produkte.index'),
        (string) \Illuminate\Support\Str::of(str_replace('-', ' ', $product->title))->words(6, '...') => null
    ]" />
@endsection

@section('content')
@php
    $affiliateLink = getProductAffiliateLink($product);

    $defaultImage = '/images/fallback.jpg';
    $activeImage = $product->image_url ?? $defaultImage;

    $primaryColor = getSetting('primary_color', '#7c3aed');

    /**
     * --- Beschreibung opschonen & robuust renderen ---
     * 1) Kies bron: ai_description_html > source_description > description
     * 2) Als het plain text is -> zet om naar nette HTML (paragraphs + simpele lijstjes)
     * 3) Strip <section> wrappers en demote <h1> naar <h2> (voorkomt dubbele grote headings)
     */
    $descRaw = $product->ai_description_html
        ?? $product->source_description
        ?? $product->description
        ?? '';

    $renderHtml = (string) $descRaw;

    // 2) Plain text → HTML (zonder externe helper)
    if ($renderHtml !== '' && $renderHtml === strip_tags($renderHtml)) {
        $text = trim($renderHtml);

        // simpele bullets naar <ul><li>
        $lines = preg_split("/\R+/", $text) ?: [];
        $inList = false;
        $buf = [];

        $pushPara = function($t) use (&$buf) {
            $t = trim($t);
            if ($t !== '') $buf[] = '<p>'.e($t).'</p>';
        };

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') { continue; }

            // herkent -, •, – als bullet
            if (preg_match('/^(\-|\•|\–)\s+(.+)$/u', $line, $m)) {
                if (!$inList) { $buf[] = '<ul>'; $inList = true; }
                $buf[] = '<li>'.e($m[2]).'</li>';
            } else {
                if ($inList) { $buf[] = '</ul>'; $inList = false; }
                $pushPara($line);
            }
        }
        if ($inList) { $buf[] = '</ul>'; }

        $renderHtml = implode("\n", $buf);
    }

    // 3) HTML opschonen: unwrap <section>, demote h1 -> h2
    if (stripos($renderHtml, '<section') !== false) {
        $renderHtml = preg_replace('#</?section[^>]*>#i', '', $renderHtml) ?? $renderHtml;
    }
    // Demote H1
    $renderHtml = preg_replace('#<\s*h1([^>]*)>#i', '<h2$1>', $renderHtml) ?? $renderHtml;
    $renderHtml = preg_replace('#</\s*h1\s*>#i', '</h2>', $renderHtml) ?? $renderHtml;

    // (optioneel) mini‑sanitization
    $renderHtml = preg_replace('#<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>#i', '', $renderHtml) ?? $renderHtml;

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

<article class="w-full pt-6 bg-white" itemscope itemtype="https://schema.org/Product">

        <!-- HERO SECTION - Compact -->
        <section class="w-full py-4 px-6 sm:px-8 bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto">
                <div class="flex items-start justify-between gap-4 mb-3">
                    <div class="flex-1">
                        @if($product->brand)
                            <p class="text-xs text-gray-500 mb-1 font-medium">{{ $product->brand }}</p>
                        @endif
                        <h1 itemprop="name" class="text-2xl sm:text-3xl font-bold text-gray-900 leading-tight mb-2">
                            {{ $product->title }}
                        </h1>

                        <!-- Rating inline -->
                        @if($product->rating_average)
                            <div class="mb-3">
                                <x-rating :stars="$product->rating_average" :count="$product->rating_count" size="sm" />
                            </div>
                        @endif

                        <!-- CTA in header -->
                        <div class="flex flex-col sm:flex-row sm:items-center gap-4" itemprop="offers" itemscope itemtype="https://schema.org/Offer">
                            <meta itemprop="url" content="{{ $product->url }}" />
                            <div class="flex flex-col gap-2">
                                <x-product-cta :product="$product" classes="whitespace-nowrap" />
                                @if(($product->is_available ?? true) && $product->delivery_time && (
                                    stripos($product->delivery_time, 'morgen') !== false ||
                                    stripos($product->delivery_time, 'vandaag') !== false ||
                                    stripos($product->delivery_time, '24') !== false
                                ))
                                    @php
                                        $now = now()->setTimezone('Europe/Amsterdam');
                                        // Check if delivery_time contains specific cutoff hour
                                        preg_match('/(\d{2}):(\d{2})/', $product->delivery_time, $matches);
                                        $cutoffHour = !empty($matches) ? (int)$matches[1] : 23;
                                        $hoursLeft = $cutoffHour - $now->hour;
                                    @endphp
                                    @if($hoursLeft > 0 && $now->hour >= 8)
                                        @php
                                            $cutoffTime = str_pad($cutoffHour, 2, '0', STR_PAD_LEFT) . ':00';
                                        @endphp
                                        <p class="text-xs text-center font-semibold" style="color: {{ $primaryColor }};">
                                            Voor {{ $cutoffTime }} besteld = morgen in huis
                                        </p>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CONTENT -->
        <section class="w-full py-8 px-6 sm:px-8 bg-white">
            <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- AFBEELDINGEN + STICKY CTA -->
            <aside class="lg:col-span-1 flex flex-col gap-4">
                <div
                    x-data="{
                        selectedIndex: 0,
                        updateImage(index, url) {
                            this.selectedIndex = index;
                            this.$refs.mainImage.src = url;
                        }
                    }"
                    class="flex flex-col gap-3"
                >
                    <!-- Grote afbeelding -->
                    <div class="w-full h-[280px] sm:h-[320px] bg-white border border-gray-200 rounded-lg flex items-center justify-center p-4">
                        <img x-ref="mainImage"
                             src="{{ $activeImage }}"
                             alt="{{ $product->title }}"
                             itemprop="image"
                             class="max-h-full max-w-full object-contain" />
                    </div>

                    <!-- Thumbnails -->
                    @php
                        $images = $product->images_json ?? [];
                    @endphp
                    @if(count($images) > 1)
                        <div class="flex gap-2 flex-wrap">
                            @foreach(array_slice($images, 0, 5) as $index => $imageUrl)
                                <img
                                    src="{{ $imageUrl }}"
                                    @click="updateImage({{ $index }}, '{{ $imageUrl }}')"
                                    :class="selectedIndex === {{ $index }} ? 'ring-2' : 'ring-0'"
                                    style="--tw-ring-color: {{ $primaryColor }};"
                                    class="w-14 h-14 object-cover border border-gray-200 rounded cursor-pointer hover:opacity-75 transition"
                                    alt="Bild {{ $index + 1 }}"
                                >
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Secondary Links -->
                @if($product->review || $product->blogPosts->isNotEmpty())
                    <div class="flex flex-col gap-2">
                        @if($product->review)
                            <a href="{{ route('testberichte.show', $product->review->slug) }}"
                               class="block bg-white hover:bg-gray-50 text-gray-900 text-center text-sm font-medium px-6 py-2 rounded-lg border border-gray-300 transition">
                                Lees review
                            </a>
                        @endif

                        @if($product->blogPosts->isNotEmpty())
                            <a href="{{ route('ratgeber.show', $product->blogPosts->first()->slug) }}"
                               class="block bg-white hover:bg-gray-50 text-gray-900 text-center text-sm font-medium px-6 py-2 rounded-lg border border-gray-300 transition">
                                Ansehen blog
                            </a>
                        @endif
                    </div>
                @endif
            </aside>

            <!-- DETAILS -->
            <section class="lg:col-span-2 flex flex-col">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">ProductBeschreibung</h2>

                <!-- AI-Beschreibung (robust render) -->
                <div class="product-copy mb-8 text-gray-700 leading-relaxed" itemprop="description">
                    @if($renderHtml !== '')
                        {!! $renderHtml !!}
                    @else
                        <p>Beschreibung volgt binnenkort.</p>
                    @endif
                </div>

                @if($product->specifications->isNotEmpty())
                    <div class="mb-8">
                        <h2 class="text-2xl font-bold text-gray-900 mb-4">Spezifikationen</h2>
                        
                        @php
                            $groupedSpecs = $product->specifications->groupBy('group');
                        @endphp
                        
                        <div class="space-y-4">
                            @foreach($groupedSpecs as $groupName => $specs)
                                <div x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }" class="bg-gray-50 rounded-xl overflow-hidden border border-gray-200">
                                    <!-- Group Header -->
                                    <button @click="open = !open" 
                                            class="w-full flex items-center justify-between p-4 text-left hover:bg-gray-100 transition-colors">
                                        <h3 class="font-bold text-gray-900 text-sm sm:text-base">
                                            {{ $groupName ?: 'Algemeen' }}
                                        </h3>
                                        <svg :class="{'rotate-180': open}" 
                                             class="w-5 h-5 text-gray-600 transform transition-transform duration-200" 
                                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>
                                    
                                    <!-- Group Content -->
                                    <div x-show="open" 
                                         x-transition:enter="transition ease-out duration-300"
                                         x-transition:enter-start="opacity-0 transform scale-y-0"
                                         x-transition:enter-end="opacity-100 transform scale-y-100"
                                         x-transition:leave="transition ease-in duration-200"
                                         x-transition:leave-start="opacity-100 transform scale-y-100"
                                         x-transition:leave-end="opacity-0 transform scale-y-0"
                                         class="transform origin-top">
                                        <div class="px-4 pb-4 space-y-3 text-sm sm:text-base text-gray-700 border-t border-gray-200">
                                            @foreach($specs as $spec)
                                                <div class="flex flex-col sm:flex-row sm:justify-between py-2 border-b border-gray-200 last:border-b-0 gap-1 sm:gap-4">
                                                    <span class="font-semibold text-gray-800 flex-shrink-0 break-words">{{ $spec->name }}</span>
                                                    <span class="font-medium text-gray-600 break-words text-left sm:text-right">{{ $spec->value }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm text-gray-700 mb-8">
                    @if($product->category_segment)
                        <div><strong>Categorie:</strong> {{ $product->category_segment }}</div>
                    @endif
                    @if($product->category_chunk)
                        <div><strong>Subcategorie:</strong> {{ $product->category_chunk }}</div>
                    @endif
                </div>
            </section>
            </div>
        </section>

        <!-- Footer info -->
        <section class="w-full py-4 px-6 sm:px-8 bg-gray-50 border-t border-gray-200">
            <div class="max-w-7xl mx-auto">
                <p class="text-xs text-gray-500">EAN: {{ $product->ean ?? 'Onbekend' }}</p>
            </div>
        </section>

<!-- Comparison CTA - Compact -->
<section class="w-full py-12 px-6 sm:px-8 bg-white border-t border-gray-200">
    <div class="max-w-4xl mx-auto">
        <div class="text-center">
            <h2 class="text-2xl font-bold text-gray-900 mb-3">
                Vergleichen met andere modellen
            </h2>
            <p class="text-gray-600 mb-6">
                Ansehen onze top selectie of alle Produkte
            </p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="{{ route('produkte.top') }}"
                   class="cta-button inline-flex items-center justify-center gap-2 px-6 py-3 rounded-lg font-semibold transition-all text-white">
                    Ansehen Top 5
                </a>
                <a href="{{ route('produkte.index') }}"
                   class="inline-flex items-center justify-center gap-2 bg-white hover:bg-gray-50 text-gray-900 px-6 py-3 rounded-lg font-semibold border border-gray-300 transition-all">
                    Alle Produkte
                </a>
            </div>
        </div>
    </div>
</section>

@php
    use Spatie\SchemaOrg\Schema;

    $siteName = getSetting('site_name', config('app.name'));
    $favicon = getSetting('favicon_url', asset('favicon.png'));

    // Gebruik de opgeschoonde HTML van hierboven voor schema
    $descForSchema = strip_tags($renderHtml);
    if (empty($descForSchema)) {
        $descForSchema = 'ProductBeschreibung wordt binnenkort toegevoegd.';
    }

    // Determine availability based on delivery_time or assume in stock
    $availabilityUrl = 'https://schema.org/InStock';
    if ($product->delivery_time && stripos($product->delivery_time, 'niet beschikbaar') !== false) {
        $availabilityUrl = 'https://schema.org/OutOfStock';
    }

    // Ensure we have at least one image
    $productImages = $product->images->pluck('image_url')->filter()->values()->toArray();
    if (empty($productImages)) {
        $productImages = [$product->image_url ?? asset('images/fallback.jpg')];
    }

    $offers = Schema::offer()
        ->url($product->url);

    $structuredProduct = Schema::product()
        ->name($product->title)
        ->description($descForSchema)
        ->image($productImages)
        ->sku($product->ean ?? 'product-' . $product->id)
        ->offers($offers);

    // Add brand if available
    if ($product->brand) {
        $structuredProduct->brand(Schema::brand()->name($product->brand));
    }

    // Add rating if available with minimum 1 review
    if ($product->rating_average && $product->rating_count && $product->rating_count >= 1) {
        $structuredProduct->aggregateRating(
            Schema::aggregateRating()
                ->ratingValue($product->rating_average)
                ->reviewCount($product->rating_count)
        );
    }
@endphp

<script type="application/ld+json">
{!! $structuredProduct->toScript() !!}
</script>

{{-- Minimale, zelfstandige opmaak voor de Beschreibung (geen Tailwind Typography vereist) --}}
<style>
.product-copy h2{font-weight:700;font-size:1.25rem;margin:1rem 0 .5rem}
.product-copy h3{font-weight:600;margin:.75rem 0 .25rem}
.product-copy p{margin:.5rem 0 1rem;line-height:1.7}
.product-copy ul{list-style:disc;margin:.5rem 0 1rem 1.25rem;padding-left:1rem}
.product-copy li{margin:.25rem 0}
</style>

<!-- Sticky Mobile CTA - Compact -->
<div id="sticky-cta" class="lg:hidden fixed bottom-0 left-0 right-0 z-50 bg-white border-t border-gray-300 shadow-lg opacity-0 pointer-events-none transition-opacity duration-300">
    <div class="px-4 py-2.5">
        <div class="flex items-center justify-between gap-3">
            <div class="flex-1">
                <p class="text-sm font-semibold text-gray-900">{{ Str::limit($product->title, 30) }}</p>
            </div>
            <x-product-cta :product="$product" classes="flex-shrink-0" />
        </div>
    </div>
</div>

<script>
(function() {
    const stickyCta = document.getElementById('sticky-cta');
    const mainCta = document.querySelector('.cta-button');

    if (!stickyCta || !mainCta) return;

    let ticking = false;

    function updateStickyVisibility() {
        const mainCtaRect = mainCta.getBoundingClientRect();
        const mainCtaBottom = mainCtaRect.bottom;

        // Show sticky CTA when main CTA is scrolled past (above viewport)
        if (mainCtaBottom < 0) {
            stickyCta.classList.remove('opacity-0', 'pointer-events-none');
            stickyCta.classList.add('opacity-100', 'pointer-events-auto');
        } else {
            stickyCta.classList.remove('opacity-100', 'pointer-events-auto');
            stickyCta.classList.add('opacity-0', 'pointer-events-none');
        }

        ticking = false;
    }

    function onScroll() {
        if (!ticking) {
            window.requestAnimationFrame(updateStickyVisibility);
            ticking = true;
        }
    }

    window.addEventListener('scroll', onScroll, { passive: true });

    // Check initial state
    updateStickyVisibility();
})();
</script>

<!-- Extra bottom padding for sticky CTA -->
<div class="lg:hidden h-16"></div>

<!-- RELATED PRODUCTS -->
@if($relatedProducts->count() > 0)
<section id="alternatieven" class="w-full py-12 px-6 sm:px-8 bg-gray-50">
    <div class="max-w-7xl mx-auto">
        @if(!($product->is_available ?? true))
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-1">Vergleichenbare Produkte</h2>
                <p class="text-sm text-gray-600">Sehen Sie sich diese verfügbaren Alternativen an</p>
            </div>
        @else
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Vergleichenbare Produkte</h2>
        @endif

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            @foreach($relatedProducts as $relatedProduct)
                @php
                    $relatedAffiliateLink = getProductAffiliateLink($relatedProduct);
                    $relatedRating = $relatedProduct->rating_average ?? 0;
                @endphp

                <div class="bg-white rounded-lg border border-gray-200 hover:border-gray-300 p-4 flex flex-col h-full transition-all hover:shadow-sm">
                    <!-- Image -->
                    <div class="h-32 flex items-center justify-center mb-3">
                        <img src="{{ $relatedProduct->image_url ?? 'https://via.placeholder.com/300x300?text=Kein+Bild' }}"
                             alt="{{ $relatedProduct->title }}"
                             class="max-h-full max-w-full object-contain" loading="lazy">
                    </div>

                    <!-- Title -->
                    <h3 class="text-xs font-semibold text-gray-900 leading-tight mb-2 min-h-[2rem]">
                        {{ Str::limit($relatedProduct->title, 50, '...') }}
                    </h3>

                    <!-- Rating -->
                    @if($relatedRating > 0)
                        <div class="flex items-center gap-1 mb-2">
                            <x-rating :stars="$relatedRating" :count="$relatedProduct->rating_count" size="xs" />
                            <span class="text-xs text-gray-500">{{ number_format($relatedRating, 1) }}</span>
                        </div>
                    @endif

                    <!-- Buttons -->
                    <div class="mt-auto flex flex-col gap-2">
                        <a href="{{ route('produkte.show', $relatedProduct->slug) }}"
                           class="bg-white hover:bg-gray-50 text-gray-900 text-xs font-medium py-2 px-3 rounded-lg text-center transition border border-gray-300">
                            Ansehen
                        </a>
                        <x-product-cta :product="$relatedProduct" size="small" />
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

</article>

@endsection
