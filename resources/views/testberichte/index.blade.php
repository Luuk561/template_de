@extends('layouts.app')

@php
    use App\Support\ContentJson;
@endphp

{{-- Meta tags worden automatisch gegenereerd via layouts/app.blade.php --}}

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        'Testberichte' => route('testberichte.index'),
    ]" />
@endsection

@section('content')

@php
    $heroImage = getImage('Testberichte.index');
    $hasPageImage = !empty($heroImage);
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

    .review-card:hover {
        border-color: #d1d5db;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
        transform: translateY(-2px);
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

<!-- HERO SECTION -->
<section class="w-full bg-gradient-to-b from-white via-gray-50 to-white pt-24 pb-12">
    <div class="max-w-7xl mx-auto px-6 sm:px-8">
        <div class="text-center max-w-4xl mx-auto space-y-4">
            @if(hasStructuredContent('Testberichte.hero'))
                {{-- STRUCTURED MODE --}}
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black leading-tight text-gray-900">
                    {!! getContent('Testberichte.hero.title') !!}
                </h1>
                <p class="text-lg sm:text-xl text-gray-600">
                    {!! getContent('Testberichte.hero.subtitle') !!}
                </p>
            @else
                {{-- FALLBACK: HTML MODE --}}
                {!! getContent('Testberichte.hero', [
                    'fallback' => '
                        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black leading-tight">Onze Testberichte</h1>
                        <p class="text-lg sm:text-xl text-gray-600">Eerlijke Testberichte van echte Produkte</p>
                    '
                ]) !!}
            @endif
        </div>
    </div>
</section>

<!-- REVIEW GRID -->
@if ($reviews->count())
<section class="w-full py-8 bg-white">
    <div class="max-w-7xl mx-auto px-6 sm:px-8">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach ($reviews as $review)
                @php
                    $reviewJson = ContentJson::decode($review->content);
                    $hasDiscount = isset($reviewJson['custom_affiliate']['discount_code']);
                @endphp

                <article class="review-card bg-white border border-gray-200 rounded-xl p-6 flex flex-col transition-all duration-200 h-full relative">
                    {{-- Discount Badge --}}
                    @if($hasDiscount)
                        <div class="absolute -top-3 right-4 bg-green-600 text-white text-xs font-bold px-3 py-1.5 rounded-full shadow-md flex items-center gap-1">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/>
                            </svg>
                            KORTING
                        </div>
                    @endif

                    @if($review->image_url || $review->product?->image_url)
                        <div class="h-40 w-full flex items-center justify-center mb-4">
                            <img src="{{ $review->image_url ?? $review->product->image_url }}"
                                 alt="Afbeelding van {{ $review->title }}"
                                 class="max-h-full max-w-full object-contain" loading="lazy">
                        </div>
                    @endif

                    <h3 class="text-xl font-bold text-gray-900 mb-3 text-center">{{ $review->title }}</h3>

                    <p class="text-gray-600 text-sm mb-4 flex-grow">
                        {{ $review->excerpt ?? \Illuminate\Support\Str::limit(strip_tags($review->content), 140) }}
                    </p>

                    <a href="{{ route('testberichte.show', $review->slug) }}"
                       class="mt-auto inline-block text-center cta-button text-white font-semibold py-3 px-6 rounded-xl transition shadow-sm">
                        Lees review
                    </a>
                </article>
            @endforeach
        </div>

        @if($reviews->hasPages())
            <div class="mt-12">
                {{ $reviews->links() }}
            </div>
        @endif
    </div>
</section>
@else
    <p class="text-center text-gray-500 py-16">Er zijn momenteel nog geen Testberichte beschikbaar. Kom later terug!</p>
@endif

<!-- SEO BLOK -->
<section class="w-full py-16 px-6 sm:px-8 bg-white">
    @if(hasStructuredContent('Testberichte_index_seo_blok'))
        {{-- STRUCTURED MODE: Two-column layout (text left, button right) --}}
        <div class="max-w-7xl mx-auto">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                {{-- Tekst links --}}
                <div>
                    <h2 class="text-3xl sm:text-4xl font-bold mb-6 text-gray-900">{!! getContent('Testberichte_index_seo_blok.title') !!}</h2>
                    <p class="text-lg text-gray-700 mb-8">{!! getContent('Testberichte_index_seo_blok.intro') !!}</p>

                    <div class="space-y-6">
                        <div>
                            <h3 class="text-xl font-bold mb-2 text-gray-900">{!! getContent('Testberichte_index_seo_blok.section1_title') !!}</h3>
                            <p class="text-gray-700">{!! getContent('Testberichte_index_seo_blok.section1_text') !!}</p>
                        </div>

                        <div>
                            <h3 class="text-xl font-bold mb-2 text-gray-900">{!! getContent('Testberichte_index_seo_blok.section2_title') !!}</h3>
                            <p class="text-gray-700">{!! getContent('Testberichte_index_seo_blok.section2_text') !!}</p>
                        </div>

                        <div>
                            <h3 class="text-xl font-bold mb-2 text-gray-900">{!! getContent('Testberichte_index_seo_blok.section3_title') !!}</h3>
                            <p class="text-gray-700">{!! getContent('Testberichte_index_seo_blok.section3_text') !!}</p>
                        </div>
                    </div>
                </div>
                {{-- Button rechts --}}
                <div class="flex justify-center lg:justify-end">
                    <a href="{{ route('produkte.index') }}" class="cta-button inline-block px-8 py-4 text-white font-semibold rounded-xl shadow-lg transition hover:scale-105">
                        Alle Modelle vergleichen
                    </a>
                </div>
            </div>
        </div>
    @else
        {{-- FALLBACK: HTML MODE --}}
        <div class="max-w-4xl mx-auto prose prose-gray prose-lg">
            {!! getContent('Testberichte_index_seo_blok', ['fallback' => '<p>Hier komt tekst te staan.</p>']) !!}
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
