@extends('layouts.app')

@section('title', 'Contact')
@section('meta_description', 'Heb je vragen over onze reviews, productadvies of samenwerkingen? Neem contact met ons op. Ons team staat klaar om je persoonlijk te helpen.')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-12">
    <h1 class="text-2xl font-semibold mb-6">Contact</h1>

    <p class="mb-4">Heb je vragen over de inhoud van deze website, wil je een fout melden, of heb je interesse in een samenwerking? Neem dan gerust contact met ons op.</p>

    <p class="mb-4">Je kunt ons bereiken via e-mail:</p>

    <p class="text-lg font-semibold text-primary mb-6">
        <a href="mailto:luukschlepers@icloud.com" class="underline hover:text-primary-dark">
            luukschlepers@icloud.com
        </a>
    </p>

    <p class="mb-4">We streven ernaar om je binnen 48 uur een reactie te geven.</p>

    <p>Voor vragen over privacy of verzoeken rondom gegevensverwerking, kun je ook dit e-mailadres gebruiken.</p>
</div>
@endsection
