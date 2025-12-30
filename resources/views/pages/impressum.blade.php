@extends('layouts.app')

@section('title', 'Impressum')
@section('meta_description', 'Impressum und Angaben gemäß § 5 TMG.')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-12">
    <h1 class="text-2xl font-semibold mb-6">Impressum</h1>

    <h2 class="text-xl font-semibold mt-8 mb-3">Angaben gemäß § 5 TMG</h2>
    <p class="mb-4">
        Luuk Schlepers<br>
        Schuineslootweg 99<br>
        7777 SW Schuinesloot<br>
        Niederlande
    </p>

    <h2 class="text-xl font-semibold mt-8 mb-3">Kontakt</h2>
    <p class="mb-4">
        E-Mail: <a href="mailto:luukschlepers@icloud.com" class="text-primary underline">luukschlepers@icloud.com</a>
    </p>

    <h2 class="text-xl font-semibold mt-8 mb-3">Verantwortlich für den Inhalt nach § 55 Abs. 2 RStV</h2>
    <p class="mb-4">
        Luuk Schlepers<br>
        Schuineslootweg 99<br>
        7777 SW Schuinesloot
    </p>

    <h2 class="text-xl font-semibold mt-8 mb-3">Haftungsausschluss</h2>
    <p class="mb-4">Die vollständigen Haftungsbestimmungen finden Sie auf unserer <a href="/haftungsausschluss" class="text-primary underline">Haftungsausschluss-Seite</a>.</p>

    <h2 class="text-xl font-semibold mt-8 mb-3">Affiliate-Hinweis</h2>
    <p class="mb-4">Als Amazon-Partner verdienen wir an qualifizierten Verkäufen. Diese Website enthält Affiliate-Links, über die wir eine Provision erhalten können, wenn Sie Produkte kaufen. Dies beeinflusst nicht unsere objektiven Bewertungen und Empfehlungen.</p>
</div>
@endsection
