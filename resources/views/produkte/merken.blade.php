@extends('layouts.app')

{{-- Meta tags worden automatisch gegenereerd via layouts/app.blade.php --}}

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        'Beste Merken' => null,
    ]" />
@endsection

@section('content')

@php
    $heroImage = getImage('Produkte.merken');
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

    .brand-card:hover {
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
            @if(hasStructuredContent('merken_index_hero_titel'))
                {{-- STRUCTURED MODE --}}
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black leading-tight text-gray-900">
                    {!! getContent('merken_index_hero_titel.title') !!}
                </h1>
                <p class="text-lg sm:text-xl text-gray-600">
                    {!! getContent('merken_index_hero_titel.subtitle') !!}
                </p>
            @else
                {{-- FALLBACK: HTML MODE --}}
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black leading-tight">
                    {!! getContent('merken_index_hero_titel', ['fallback' => 'Beste Merken']) !!}
                </h1>
                <p class="text-lg sm:text-xl text-gray-600">
                    Von Premium bis Budget - entdecken Sie, welche Marken wir vergleichen
                </p>
            @endif
        </div>
    </div>
</section>

<!-- MERKEN GRID -->
<section id="merken" class="w-full py-8 bg-white">
    <div class="max-w-7xl mx-auto px-6 sm:px-8">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach ($merken as $merk)
                <div class="brand-card bg-white border border-gray-200 rounded-xl p-6 flex flex-col items-center transition-all duration-200">
                    <h3 class="text-2xl font-bold text-center text-gray-900 mb-3">
                        {{ $merk->brand }}
                    </h3>
                    <div class="inline-flex items-center justify-center px-4 py-1.5 text-sm font-semibold text-white rounded-full shadow-sm mb-3" style="background: {{ $primaryColor }};">
                        {{ number_format($merk->gemiddelde_rating, 1) }}/5
                    </div>
                    <p class="text-gray-600 text-sm mb-6 text-center">
                        {{ $merk->aantal }} {{ $merk->aantal == 1 ? 'product' : 'Produkte' }}
                    </p>
                    <a href="{{ route('produkte.index', ['brand' => $merk->brand]) }}"
                       class="w-full text-center cta-button text-white font-semibold py-3 px-6 rounded-xl transition duration-200 shadow-sm">
                        Ansehen assortiment
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>

<!-- INFO BLOK -->
<section class="w-full py-16 px-6 sm:px-8 bg-white">
    @if(hasStructuredContent('merken_index_info_blok'))
        {{-- STRUCTURED MODE: Two-column layout (button left, text right) --}}
        <div class="max-w-7xl mx-auto">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                {{-- Button links --}}
                <div class="order-2 lg:order-1 flex justify-center lg:justify-start">
                    <a href="{{ route('produkte.index') }}" class="cta-button inline-block px-8 py-4 text-white font-semibold rounded-xl shadow-lg transition hover:scale-105">
                        Alle ansehen merken
                    </a>
                </div>
                {{-- Tekst rechts --}}
                <div class="order-1 lg:order-2">
                    <h2 class="text-3xl sm:text-4xl font-bold mb-6 text-gray-900">{!! getContent('merken_index_info_blok.title') !!}</h2>
                    <p class="text-lg text-gray-700 mb-8">{!! getContent('merken_index_info_blok.intro') !!}</p>

                    <div class="space-y-6">
                        <div>
                            <h3 class="text-xl font-bold mb-2 text-gray-900">{!! getContent('merken_index_info_blok.section1_title') !!}</h3>
                            <p class="text-gray-700">{!! getContent('merken_index_info_blok.section1_text') !!}</p>
                        </div>

                        <div>
                            <h3 class="text-xl font-bold mb-2 text-gray-900">{!! getContent('merken_index_info_blok.section2_title') !!}</h3>
                            <p class="text-gray-700">{!! getContent('merken_index_info_blok.section2_text') !!}</p>
                        </div>

                        <div>
                            <h3 class="text-xl font-bold mb-2 text-gray-900">{!! getContent('merken_index_info_blok.section3_title') !!}</h3>
                            <p class="text-gray-700">{!! getContent('merken_index_info_blok.section3_text') !!}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        {{-- FALLBACK: HTML MODE --}}
        <div class="max-w-4xl mx-auto prose prose-gray prose-lg">
            {!! getContent('merken_index_info_blok', ['fallback' => '<p>Vergleichen merken en maak een bewuste keuze voor kwaliteit, betrouwbaarheid en innovatie.</p>']) !!}
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
