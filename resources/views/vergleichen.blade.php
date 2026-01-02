@extends('layouts.app')

@section('title', 'Vergleichen Produkte')
@section('meta_description', 'Vergleichen Sie Ihre Lieblingsprodukte direkt nebeneinander nach Spezifikationen, Preis, Testberichten und mehr. Finden Sie schnell die beste Wahl für Ihre Situation und Ihr Budget.')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        'Produkte' => route('produkte.index'),
        'Vergleichen' => '',
    ]" />
@endsection

@section('head')
    <meta name="ai-eans" content='@json($products->pluck("ean")->values())'>
@endsection

@section('content')
@php
    $primaryColor = getSetting('primary_color', '#1D4ED8');
@endphp

<style>
    :root {
        --primary-color: {{ $primaryColor }};
        --primary-dark: color-mix(in srgb, {{ $primaryColor }} 20%, #000 80%);
    }

    .text-gray-900 {
        color: var(--primary-dark) !important;
    }

    .text-gray-800 {
        color: var(--primary-dark) !important;
    }

    .text-gray-700 {
        color: color-mix(in srgb, {{ $primaryColor }} 15%, #000 75%) !important;
    }
</style>

<section class="w-full pt-24 pb-12 px-6 sm:px-8 bg-white">
    <div class="max-w-7xl mx-auto">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4 mb-8">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">Vergleichen Produkte</h1>
                <p class="text-gray-600 text-sm">
                    Bis zu 3 Produkte nebeneinander vergleichen
                </p>
            </div>

            @if(count($products) < 3)
                <a href="{{ route('produkte.index', ['terug_naar_vergelijker' => '1', 'eans' => request('eans')]) }}"
                   class="inline-flex items-center gap-2 font-semibold px-6 py-3 rounded-lg transition hover:opacity-90"
                   style="background-color: {{ $primaryColor }}; color: white !important;">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Produkt hinzufügen
                </a>
            @else
                <div class="inline-flex items-center gap-2 font-semibold px-6 py-3 rounded-lg opacity-50 cursor-not-allowed"
                     style="background-color: {{ $primaryColor }}; color: white !important;">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Max. 3 Produkte
                </div>
            @endif
        </div>
    </div>

    @if($products->isEmpty())
        <div class="max-w-7xl mx-auto text-center py-20">
            <h2 class="text-xl font-semibold text-gray-700 mb-4">Keine Produkte ausgewählt</h2>
            <a href="{{ route('produkte.index') }}"
               class="inline-block text-white px-6 py-3 rounded-lg font-semibold transition hover:opacity-90"
               style="background-color: {{ $primaryColor }}">
                Produkte bekijken
            </a>
        </div>
    @endif    

    <!-- Mobile/Tablet: Scrollable Table -->
    <div class="max-w-7xl mx-auto lg:hidden overflow-x-auto mb-10">
        <table class="table-fixed border-collapse min-w-max w-full bg-white rounded-xl overflow-hidden shadow-sm">
            <thead>
                <tr>
                    <th class="w-32 sm:w-40 bg-white"></th>
                    @foreach($products as $product)
                        <th class="px-3 pb-6 pt-4 text-center align-bottom w-[130px] sm:w-[150px] bg-white">
                            <div class="relative flex flex-col items-center space-y-2">
                                <img src="{{ $product->image_url }}" alt="{{ $product->title }}" loading="lazy" class="w-16 h-16 object-contain mx-auto">
                                <div class="text-center min-h-[2.5rem] flex items-center">
                                    <p class="font-bold text-xs text-gray-900 leading-tight line-clamp-2">{{ Str::limit($product->title, 25) }}</p>
                                </div>
                                <p class="text-xs text-gray-500 font-medium">{{ $product->brand }}</p>
                                <div class="mt-3 space-y-2 w-full">
                                    @php
                                        $affiliateLink = getProductAffiliateLink($product);
                                    @endphp

                                    <a href="{{ $affiliateLink }}" target="_blank" rel="nofollow sponsored"
                                       class="block text-xs font-bold py-1.5 px-2 rounded transition text-center hover:opacity-90 bg-blue-600 no-underline"
                                       style="color: white !important; text-decoration: none !important;">
                                        Preis prüfen
                                    </a>

                                    <a href="{{ route('produkte.show', $product->slug) }}"
                                       class="block text-xs text-gray-900 font-medium py-1.5 px-2 rounded transition text-center border border-gray-300 bg-white hover:bg-gray-50 no-underline"
                                       style="text-decoration: none !important;">
                                        Details
                                    </a>
                                </div>
                                @php
                                    $eans = collect(explode(',', request('eans', '')));
                                    $updatedEans = $eans->reject(fn($ean) => $ean == $product->ean)->implode(',');
                                @endphp
                                <a href="{{ url('/vergleichen?eans=' . $updatedEans) }}"
                                class="absolute -top-2 -right-2 bg-red-500 hover:bg-red-600 text-xs w-6 h-6 rounded-full shadow-lg flex items-center justify-center transition transform hover:scale-110 no-underline"
                                style="color: white !important; text-decoration: none !important;"
                                title="{{ $product->title }} entfernen">
                                    ×
                                </a>
                            </div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @php
                    $allSpecs = $products->flatMap(fn($p) => $p->specifications->pluck('value', 'name'))->keys()->unique()->values();
                @endphp
                
                @foreach($allSpecs as $specName)
                    <tr class="border-t border-gray-100 hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-4 text-xs sm:text-sm font-semibold text-gray-700">
                            <div class="break-words leading-tight">{{ $specName }}</div>
                        </td>
                        @foreach($products as $product)
                            @php
                                $spec = $product->specifications->firstWhere('name', $specName);
                                $value = $spec ? $spec->value : '—';
                                $clean = strip_tags($value);
                                $isLong = strlen($clean) > 50;
                            @endphp
                            <td class="px-3 py-4 text-xs text-gray-700 align-top w-[130px] sm:w-[150px]">
                                @if($isLong)
                                    <div x-data="{ expanded: false }" class="relative">
                                        <div x-show="!expanded" class="leading-snug break-words">
                                            {{ Str::limit($clean, 50) }}
                                        </div>
                                        <div x-show="expanded" x-transition class="leading-snug break-words whitespace-pre-line">
                                            {{ $clean }}
                                        </div>
                                        <button @click="expanded = !expanded" 
                                                class="mt-1 text-blue-600 text-xs hover:text-blue-800 font-medium focus:outline-none">
                                            <span x-show="!expanded">→ Mehr</span>
                                            <span x-show="expanded">← Weniger</span>
                                        </button>
                                    </div>
                                @else
                                    <div class="leading-snug break-words">{{ $clean ?: '—' }}</div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Desktop: Table Layout -->
    <div class="max-w-7xl mx-auto hidden lg:block overflow-x-auto mb-10">
        <table class="table-fixed border-collapse min-w-max w-full bg-white rounded-xl overflow-hidden shadow-sm">
            <thead>
                <tr>
                    <th class="w-48 bg-white"></th>
                    @foreach($products as $product)
                        <th class="px-6 pb-6 pt-6 text-center align-bottom w-[280px] bg-white">
                            <div class="relative flex flex-col items-center">
                                <img src="{{ $product->image_url }}" alt="{{ $product->title }}" loading="lazy" class="w-20 h-20 object-contain mx-auto mb-3">
                                <p class="font-semibold text-sm text-gray-900 leading-tight text-center break-words">{{ \Illuminate\Support\Str::limit($product->title, 50) }}</p>
                                <p class="text-xs text-gray-500 mt-1">{{ $product->brand }}</p>
                                <div class="mt-3 space-y-2 w-full">
                                    @php
                                        $affiliateLink = getProductAffiliateLink($product);
                                    @endphp

                                    <a href="{{ $affiliateLink }}" target="_blank" rel="nofollow sponsored"
                                       class="block text-sm font-semibold py-2 px-3 rounded transition text-center hover:opacity-90 bg-blue-600 no-underline"
                                       style="color: white !important; text-decoration: none !important;">
                                        Preis prüfen auf Amazon
                                    </a>

                                    <a href="{{ route('produkte.show', $product->slug) }}"
                                       class="block text-sm text-gray-900 font-medium py-2 px-3 rounded transition text-center border border-gray-300 bg-white hover:bg-gray-50 no-underline"
                                       style="text-decoration: none !important;">
                                        Details ansehen
                                    </a>
                                </div>
                                @php
                                    $eans = collect(explode(',', request('eans', '')));
                                    $updatedEans = $eans->reject(fn($ean) => $ean == $product->ean)->implode(',');
                                @endphp
                                <a href="{{ url('/vergleichen?eans=' . $updatedEans) }}"
                                class="absolute top-0 right-0 bg-red-500 hover:bg-red-600 text-[10px] sm:text-xs px-1.5 sm:px-2 py-1 rounded-full shadow no-underline"
                                style="color: white !important; text-decoration: none !important;"
                                title="{{ $product->title }} entfernen">
                                    ✕
                                </a>
                            </div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @php
                    $allSpecs = $products->flatMap(fn($p) => $p->specifications->pluck('value', 'name'))->keys()->unique()->values();
                @endphp
                @foreach($allSpecs as $specName)
                    <tr class="border-t border-gray-100 hover:bg-gray-50 transition-colors">
                        <th class="text-left font-semibold text-gray-700 px-6 py-4 w-48 text-sm">
                            {{ $specName }}
                        </th>
                        @foreach($products as $product)
                            @php
                                $value = optional($product->specifications->firstWhere('name', $specName))->value;
                                $clean = strip_tags($value);
                                $tooLong = strlen($clean) > 220;
                            @endphp
                            <td class="px-6 py-4 text-sm text-gray-700 align-top w-[280px] break-words">
                                <div x-data="{ open: false }" class="relative">
                                    <div x-show="open" x-collapse.duration.300ms x-cloak class="whitespace-pre-line leading-snug break-words">
                                        {{ $clean ?? '—' }}
                                    </div>
                                    <div x-show="!open" x-cloak class="line-clamp-3 whitespace-pre-line leading-snug overflow-hidden break-words">
                                        {{ $clean ?? '—' }}
                                    </div>
                                    @if($tooLong)
                                        <button @click="open = !open"
                                                class="mt-1 text-blue-600 text-[10px] sm:text-xs hover:underline focus:outline-none">
                                            <span x-show="!open">Mehr anzeigen</span>
                                            <span x-show="open">Weniger anzeigen</span>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

@include('components.ai-popup')
@endsection
