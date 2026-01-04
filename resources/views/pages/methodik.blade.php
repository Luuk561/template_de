@extends('layouts.app')

@section('title', 'So bewerten wir – Unsere Methodik')
@section('meta_description', 'Erfahren Sie, wie wir Produkte vergleichen und bewerten. Objektiv, datenbasiert und transparent.')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        'So bewerten wir' => null,
    ]" />
@endsection

@section('content')
@php
    $niche = getSetting('site_niche', 'Produkte');
    $siteName = getSetting('site_name', config('app.name'));
    $primaryColor = getSetting('primary_color', '#7c3aed');
@endphp

<style>
    :root {
        --primary-color: {{ $primaryColor }};
        --primary-dark: color-mix(in srgb, {{ $primaryColor }} 20%, #000 80%);
    }

    .text-gray-900 {
        color: var(--primary-dark) !important;
    }
</style>

<div class="bg-white">
    {{-- Hero Section --}}
    <section class="w-full bg-gradient-to-b from-white via-gray-50 to-white pt-24 pb-12">
        <div class="max-w-4xl mx-auto px-6 text-center">
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black text-gray-900 mb-6">
                So bewerten wir
            </h1>
            <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                Objektiv, datenbasiert und transparent – so helfen wir Ihnen bei der richtigen Wahl
            </p>
        </div>
    </section>

    {{-- Content --}}
    <div class="max-w-4xl mx-auto px-6 lg:px-8 pb-16">

        {{-- Intro --}}
        <div class="mb-16">
            <p class="text-lg text-gray-700 leading-relaxed mb-6">
                Bei {{ $siteName }} analysieren wir täglich {{ $niche }}, um Ihnen die besten Optionen
                auf einen Blick zu präsentieren. Unsere Vergleiche basieren auf harten Fakten, echten Kundenerfahrungen
                und kontinuierlicher Marktbeobachtung.
            </p>
        </div>

        {{-- Section: Unsere Datenquellen --}}
        <div class="mb-16">
            <h2 class="text-3xl font-bold text-gray-900 mb-8">Unsere Datenquellen</h2>

            <div class="grid md:grid-cols-2 gap-6">
                <div class="p-6 bg-gray-50 rounded-2xl border border-gray-100">
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 w-12 h-12 rounded-xl flex items-center justify-center" style="background-color: {{ $primaryColor }}20;">
                            <svg class="w-6 h-6" style="color: {{ $primaryColor }};" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-900 mb-2">Kundenbewertungen</h3>
                            <p class="text-sm text-gray-600">
                                Analyse von Tausenden verifizierten Amazon.de Bewertungen für ein realistisches Bild der Produktqualität.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="p-6 bg-gray-50 rounded-2xl border border-gray-100">
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 w-12 h-12 rounded-xl flex items-center justify-center" style="background-color: {{ $primaryColor }}20;">
                            <svg class="w-6 h-6" style="color: {{ $primaryColor }};" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2 5a2 2 0 012-2h7a2 2 0 012 2v4a2 2 0 01-2 2H9l-3 3v-3H4a2 2 0 01-2-2V5z"/>
                                <path d="M15 7v2a4 4 0 01-4 4H9.828l-1.766 1.767c.28.149.599.233.938.233h2l3 3v-3h2a2 2 0 002-2V9a2 2 0 00-2-2h-1z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-900 mb-2">Technische Daten</h3>
                            <p class="text-sm text-gray-600">
                                Herstellerangaben zu Leistung, Abmessungen, Funktionen und Materialien bilden die Basis unserer Vergleiche.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="p-6 bg-gray-50 rounded-2xl border border-gray-100">
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 w-12 h-12 rounded-xl flex items-center justify-center" style="background-color: {{ $primaryColor }}20;">
                            <svg class="w-6 h-6" style="color: {{ $primaryColor }};" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-900 mb-2">Externe Tests</h3>
                            <p class="text-sm text-gray-600">
                                Wo verfügbar berücksichtigen wir Testberichte von Fachmagazinen und Verbraucherorganisationen.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="p-6 bg-gray-50 rounded-2xl border border-gray-100">
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 w-12 h-12 rounded-xl flex items-center justify-center" style="background-color: {{ $primaryColor }}20;">
                            <svg class="w-6 h-6" style="color: {{ $primaryColor }};" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-900 mb-2">Preis-Tracking</h3>
                            <p class="text-sm text-gray-600">
                                Tägliche Aktualisierung der Preise und Verfügbarkeit direkt von Amazon.de.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section: Bewertungskriterien --}}
        <div class="mb-16">
            <h2 class="text-3xl font-bold text-gray-900 mb-8">Bewertungskriterien</h2>
            <p class="text-gray-700 mb-8">
                Jedes Produkt wird nach einem standardisierten Set von Kriterien bewertet. Die wichtigsten Faktoren:
            </p>

            <div class="space-y-4">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5" style="color: {{ $primaryColor }};" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <h3 class="font-semibold text-gray-900">Technische Leistung & Funktionen</h3>
                        <p class="text-sm text-gray-600">Motorleistung, Verarbeitung, Features im Verhältnis zum Preis</p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5" style="color: {{ $primaryColor }};" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <h3 class="font-semibold text-gray-900">Kundenzufriedenheit</h3>
                        <p class="text-sm text-gray-600">Durchschnittliche Bewertung und Anzahl der Rezensionen</p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5" style="color: {{ $primaryColor }};" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <h3 class="font-semibold text-gray-900">Preis-Leistungs-Verhältnis</h3>
                        <p class="text-sm text-gray-600">Ist der aktuelle Preis gerechtfertigt für die gebotene Qualität?</p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5" style="color: {{ $primaryColor }};" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <h3 class="font-semibold text-gray-900">Benutzerfreundlichkeit</h3>
                        <p class="text-sm text-gray-600">Bedienung, Installation, Wartung und praktische Erfahrungen</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section: Aktualisierung --}}
        <div class="mb-16 p-8 bg-gray-50 rounded-2xl border border-gray-200">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Tägliche Aktualisierung</h2>
            <p class="text-gray-700 mb-4">
                Unsere Datenbank wird <strong>täglich automatisch aktualisiert</strong>. Preise, Verfügbarkeit und
                Bewertungen werden kontinuierlich synchronisiert, damit Sie immer die aktuellsten Informationen erhalten.
            </p>
            <p class="text-gray-700">
                Neue Produkte werden aufgenommen, sobald sie am Markt verfügbar sind und genügend Kundenbewertungen vorliegen.
            </p>
        </div>

        {{-- Section: Finanzierung --}}
        <div class="mb-16">
            <h2 class="text-3xl font-bold text-gray-900 mb-8">Wie finanzieren wir uns?</h2>
            <p class="text-gray-700 mb-6">
                {{ $siteName }} finanziert sich über Affiliate-Partnerlinks, hauptsächlich über das
                <strong>Amazon PartnerNet</strong>. Wenn Sie über einen unserer Links ein Produkt kaufen,
                erhalten wir eine kleine Provision vom Händler – <strong>für Sie entstehen keine zusätzlichen Kosten</strong>.
            </p>
            <p class="text-gray-700">
                Unsere Rankings und Bewertungen sind davon <strong>unabhängig</strong>. Alle Produkte werden nach
                denselben objektiven Kriterien bewertet, unabhängig von der Höhe eventueller Provisionen.
            </p>
        </div>

        {{-- CTA --}}
        <div class="text-center pt-8 border-t border-gray-200">
            <h3 class="text-2xl font-bold text-gray-900 mb-4">Noch Fragen?</h3>
            <p class="text-gray-600 mb-6">
                Bei Fragen zu unserer Methodik oder Feedback können Sie uns jederzeit kontaktieren.
            </p>
            <a href="/kontakt" class="inline-flex items-center gap-2 px-6 py-3 text-white font-semibold rounded-xl transition shadow-sm" style="background-color: {{ $primaryColor }};">
                Kontakt aufnehmen
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                </svg>
            </a>
        </div>

    </div>
</div>
@endsection
