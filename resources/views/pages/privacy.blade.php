@extends('layouts.app')

@section('title', 'Datenschutzerklärung')
@section('meta_description', 'Lesen Sie unsere Datenschutzerklärung darüber, wie wir Ihre Daten verarbeiten, schützen und verwenden. Informationen über Cookies, Affiliate-Partner und Ihre Rechte.')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-12">
    <h1 class="text-2xl font-semibold mb-6">Datenschutzerklärung</h1>

    <p class="mb-4">Auf dieser Website legen wir großen Wert auf Ihre Privatsphäre. Wir verarbeiten nur Daten, die wir zur Verbesserung unserer Dienstleistungen benötigen, und gehen sorgfältig mit den Informationen um, die wir über Sie und Ihre Nutzung unserer Website sammeln.</p>

    <h2 class="text-xl font-semibold mt-8 mb-3">Welche Daten sammeln wir?</h2>
    <ul class="list-disc list-inside mb-4">
        <li>Anonymisierte Daten über die Website-Nutzung über analytische Cookies (wie Seitenaufrufe und Klickverhalten)</li>
        <li>Technische Daten wie Browsertyp, Gerät und Bildschirmauflösung</li>
    </ul>

    <h2 class="text-xl font-semibold mt-8 mb-3">Cookies</h2>
    <p class="mb-4">Wir verwenden funktionale und analytische Cookies, damit die Website ordnungsgemäß funktioniert und verbessert werden kann. Diese Daten können nicht auf einzelne Personen zurückgeführt werden.</p>

    <h2 class="text-xl font-semibold mt-8 mb-3">Drittanbieter</h2>
    <p class="mb-4">Diese Website enthält Affiliate-Links. Wenn Sie über einen solchen Link einen Kauf tätigen, kann der Affiliate-Partner (wie Amazon.de) Cookies setzen oder Daten gemäß seiner eigenen Richtlinien verarbeiten. Darauf haben wir keinen Einfluss.</p>

    <h2 class="text-xl font-semibold mt-8 mb-3">Ihre Rechte</h2>
    <p class="mb-4">Sie haben das Recht, Einblick in die personenbezogenen Daten zu erhalten, die wir von Ihnen verarbeiten, und Sie können die Berichtigung oder Löschung dieser Daten beantragen. Nehmen Sie hierzu Kontakt über <a href="/kontakt" class="text-primary underline">die Kontaktseite</a> auf.</p>
</div>
@endsection
