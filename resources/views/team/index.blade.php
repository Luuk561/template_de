@extends('layouts.app')

{{-- Meta tags --}}
@section('meta_title', $pageTitle ?? 'Ons Team - ' . getSetting('site_name'))
@section('meta_description', $metaDescription ?? 'Maak kennis met het team achter ' . getSetting('site_name'))

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        'Team' => route('team.index'),
    ]" />
@endsection

@section('content')

@php
    $primaryColor = getSetting('primary_color', '#7c3aed');
    $siteName = getSetting('site_name');
    $niche = getSetting('site_niche');
@endphp

<!-- HERO HEADER WITH GRADIENT -->
<section class="w-full bg-gradient-to-br from-purple-50 via-blue-50 to-white py-12 md:py-20 border-b border-gray-100">
    <div class="max-w-5xl mx-auto px-4 text-center">
        <div class="inline-flex items-center gap-2 bg-white px-3 md:px-4 py-1.5 md:py-2 rounded-full shadow-sm mb-4 md:mb-6">
            <svg class="w-4 md:w-5 h-4 md:h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            <span class="text-xs md:text-sm font-medium text-gray-700">Maak kennis met ons team</span>
        </div>

        <h1 class="text-3xl sm:text-4xl md:text-5xl font-bold text-gray-900 mb-3 md:mb-4 px-4">
            De experts achter <span class="text-transparent bg-clip-text bg-gradient-to-r from-purple-600 to-blue-600">{{ $siteName }}</span>
        </h1>
        <p class="text-base md:text-xl text-gray-600 max-w-2xl mx-auto px-4">
            Passie voor {{ $niche }}, gedreven door expertise. Ons team test, vergelijkt en adviseert, zodat jij de beste keuze kunt maken.
        </p>
    </div>
</section>

<!-- TEAM GRID - ENHANCED CARDS -->
@if ($teamMembers->count())
<section class="w-full py-12 md:py-20 bg-white">
    <div class="max-w-6xl mx-auto px-4">

        <!-- Team Grid - 3 columns on desktop -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
            @foreach ($teamMembers as $member)
            <a href="{{ route('team.show', $member->slug) }}" class="block group">
                <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden hover:shadow-xl hover:border-purple-200 hover:-translate-y-1 transition-all duration-300">

                    <!-- Photo Header with Gradient -->
                    <div class="relative bg-gradient-to-br from-purple-100 via-blue-50 to-purple-50 p-8 pb-0">
                        @if($member->photo_url)
                        <div class="w-24 h-24 mx-auto rounded-full overflow-hidden border-4 border-white shadow-lg group-hover:scale-105 transition-transform duration-300">
                            <img
                                src="{{ $member->photo_url }}"
                                alt="{{ $member->name }}"
                                loading="lazy"
                                class="w-full h-full object-cover"
                            >
                        </div>
                        @else
                        <div class="w-24 h-24 mx-auto rounded-full bg-gradient-to-br from-purple-400 to-blue-400 flex items-center justify-center border-4 border-white shadow-lg">
                            <span class="text-3xl font-bold text-white">{{ substr($member->name, 0, 1) }}</span>
                        </div>
                        @endif
                    </div>

                    <!-- Content -->
                    <div class="p-6 text-center">
                        <h2 class="text-2xl font-bold text-gray-900 mb-1 group-hover:text-purple-600 transition-colors">
                            {{ $member->name }}
                        </h2>
                        <p class="text-sm font-medium text-purple-600 mb-4">
                            {{ $member->role }}
                        </p>

                        <p class="text-gray-600 italic mb-6 line-clamp-3 min-h-[4.5rem]">
                            "{{ $member->quote }}"
                        </p>

                        <!-- Stats/Tags -->
                        <div class="space-y-2">
                            <div class="inline-flex items-center gap-2 bg-gray-50 px-3 py-1.5 rounded-full text-xs">
                                <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span class="font-medium text-gray-700">{{ ucfirst($member->focus) }}</span>
                            </div>
                        </div>

                        <!-- Content Count -->
                        @php
                            $contentCount = $member->blogPosts()->count() + $member->reviews()->count();
                        @endphp
                        @if($contentCount > 0)
                        <div class="mt-4 pt-4 border-t border-gray-100">
                            <p class="text-sm text-gray-500">
                                {{ $contentCount }} {{ Str::plural('artikel', $contentCount) }} geschreven
                            </p>
                        </div>
                        @endif

                        <!-- CTA -->
                        <div class="mt-4 flex items-center justify-center gap-2 text-purple-600 font-medium group-hover:gap-3 transition-all">
                            <span class="text-sm">Lees meer</span>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </a>
            @endforeach
        </div>

        <!-- Mission Statement -->
        <div class="mt-12 md:mt-20 max-w-4xl mx-auto">
            <div class="bg-gradient-to-br from-purple-50 to-blue-50 rounded-2xl p-6 md:p-12 text-center border border-purple-100">
                <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-3 md:mb-4">Onze missie</h2>
                <p class="text-base md:text-lg text-gray-700 leading-relaxed mb-4 md:mb-6">
                    Wij geloven dat de beste aankoop begint met eerlijk, helder advies. Daarom testen en vergelijken wij {{ $niche }} op basis van wat écht belangrijk is: gebruiksgemak, prestaties en duurzaamheid. Geen marketing praatjes, gewoon praktisch advies dat jou helpt de juiste keuze te maken.
                </p>
                <p class="text-sm md:text-base text-gray-600">
                    Ons team combineert verschillende expertises en perspectieven, zodat elk product vanuit meerdere invalshoeken wordt beoordeeld. Of je nu op zoek bent naar technische diepgang, praktische tips of duurzame keuzes—wij hebben het voor je uitgeplozen.
                </p>
            </div>
        </div>

    </div>
</section>
@else
<section class="w-full py-20 bg-gray-50">
    <div class="max-w-4xl mx-auto px-4 text-center">
        <p class="text-gray-600">Er zijn nog geen teamleden. Gebruik <code class="bg-gray-200 px-2 py-1 rounded font-mono text-sm">php artisan team:generate</code></p>
    </div>
</section>
@endif

@endsection
