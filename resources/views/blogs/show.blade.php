@extends('layouts.app')

@section('title', strip_tags($post->meta_title ?? $post->title))
@section('meta_description', $post->meta_description ?? '')

@if($post->meta_robots)
@section('meta_robots', $post->meta_robots)
@endif

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        'Blogs' => route('blogs.index'),
        \Illuminate\Support\Str::limit($post->title, 60) => ''
    ]" />
@endsection

@section('content')
@php
    $secondImage = $post->product?->images->get(1)?->url ?? $post->product?->image_url;
    $affiliateLink = $post->product && $post->product->url
        ? getBolAffiliateLink($post->product->url, $post->product->title)
        : '#';
@endphp

<!-- Content -->
        @php
            use App\Support\ContentJson;
            $json = ContentJson::decode($post->content);
            $isV3 = ContentJson::isV3($json, 'blog.v3');
            $isV2 = ContentJson::isV2($json, 'blog.v2');

            // Detect blog type
            // Product Blog: has product_id AND created after template system launch
            // General Blog: no product_id OR older blogs with product_id (backwards compat)
            $isProductBlog = $post->product_id !== null && $post->created_at->isAfter('2025-11-05');
            $isGeneralBlog = !$isProductBlog;
        @endphp

    @if ($post->created_at->isAfter('2025-08-23 11:15:00') && $isV3)
        {{-- JSON v3 Premium Rendering - Full Width Layout --}}
        
        {{-- White Header Section - Full Width --}}
        <div class="w-full bg-white">
            {{-- Back Button --}}
            <div class="max-w-4xl mx-auto px-6 pt-8 pb-4">
            <a href="{{ route('blogs.index') }}" class="inline-flex items-center text-gray-600 hover:text-gray-900 transition-colors duration-200">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Terug naar overzicht
            </a>
        </div>
        
            {{-- Header Content --}}
            <div class="max-w-6xl mx-auto px-6 pt-8 pb-16">
            @if($post->product && $secondImage)
                {{-- Hero with Product Image --}}
                <div class="grid md:grid-cols-2 gap-16 items-center">
                    <div class="text-center md:text-left">
                        <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6 leading-tight tracking-tight">
                            {{ ContentJson::getString($json, 'title', $post->title) }}
                        </h1>

                        @if($post->teamMember)
                            <div class="mb-6">
                                <x-author-byline :teamMember="$post->teamMember" :date="$post->created_at" :compact="true" />
                            </div>
                        @endif

                        @if(!empty($json['standfirst']))
                            <p class="text-lg md:text-xl text-gray-600 font-light leading-relaxed mb-6">
                                {{ $json['standfirst'] }}
                            </p>
                        @endif
                        
                        <div class="inline-flex items-center text-gray-500 text-sm">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            {{ $post->created_at->format('d M Y') }} • {{ ceil(str_word_count(strip_tags($post->content)) / 200) }} min leestijd
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <img src="{{ $secondImage }}" 
                             alt="{{ $post->product->title }}" 
                             class="w-64 h-64 object-contain mx-auto">
                        <p class="text-sm text-gray-500 mt-4 font-medium">{{ $post->product->title }}</p>
                    </div>
                </div>
            @else
                {{-- Hero without Product - Typography Focus --}}
                <div class="text-center max-w-4xl mx-auto">
                    <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6 leading-tight tracking-tight">
                        {{ ContentJson::getString($json, 'title', $post->title) }}
                    </h1>

                    @if($post->teamMember)
                        <div class="mb-8 flex justify-center">
                            <x-author-byline :teamMember="$post->teamMember" :date="$post->created_at" :compact="true" />
                        </div>
                    @endif

                    @if(!empty($json['standfirst']))
                        <p class="text-xl md:text-2xl text-gray-600 font-light leading-relaxed mb-8 max-w-3xl mx-auto">
                            {{ $json['standfirst'] }}
                        </p>
                    @endif
                    
                    <div class="inline-flex items-center text-gray-500 text-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 002 2z" />
                        </svg>
                        {{ $post->created_at->format('d M Y') }} • {{ ceil(str_word_count(strip_tags($post->content)) / 200) }} min leestijd
                    </div>
                </div>
            @endif
            </div>
        </div>

        {{-- Gray Content Section --}}
        <div class="w-full bg-gray-100 min-h-screen py-16">
            <div class="max-w-4xl mx-auto px-6">
                {{-- Subtle Product Reference --}}
        @if($post->product)
            <x-cta.subtle-top :product="$post->product" />
        @endif

        {{-- Content Sections --}}
        @foreach(ContentJson::getArray($json, 'sections') as $section)
            @switch($section['type'] ?? '')
                @case('text')
                    <x-content.text-section 
                        :heading="ContentJson::getString($section, 'heading')"
                        :subheadings="ContentJson::getArray($section, 'subheadings')"
                        :paragraphs="ContentJson::getArray($section, 'paragraphs')"
                        :internal_links="ContentJson::getArray($section, 'internal_links')" />
                    @break
                    
                @case('quote')
                    <div class="my-16 max-w-2xl mx-auto px-6 text-center">
                        <blockquote class="text-xl md:text-2xl font-light text-gray-800 italic leading-relaxed">
                            @php
                                $quoteText = '';
                                if (isset($section['quote']['text'])) {
                                    $quoteText = $section['quote']['text'];
                                } elseif (is_string($section['quote'] ?? '')) {
                                    $quoteText = $section['quote'];
                                }
                            @endphp
                            "{{ $quoteText }}"
                        </blockquote>
                    </div>
                    @break
                    
                @case('image')
                    <x-content.image 
                        :url="ContentJson::getString($section['image'] ?? [], 'url', '')"
                        :caption="ContentJson::getString($section['image'] ?? [], 'caption', '')"
                        :alt="ContentJson::getString($section['image'] ?? [], 'caption', '')" />
                    @break
                    
                @case('faq')
                    <x-content.faq :items="ContentJson::getArray($section, 'faq')" />
                    @break
            @endswitch
        @endforeach

                {{-- Custom Affiliate Discount Block (Moovv etc.) --}}
                @if(!empty($json['custom_affiliate']['discount_block']))
                    <section class="my-12 bg-gradient-to-r from-green-50 to-blue-50 border-l-4 border-green-500 rounded-xl p-6 md:p-8 shadow-md">
                        <div class="flex items-start">
                            <svg class="w-8 h-8 text-green-600 mr-4 flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div class="flex-1">
                                <h3 class="text-xl font-bold text-gray-900 mb-3">Exclusieve korting voor onze lezers</h3>
                                <div class="prose prose-lg max-w-none text-gray-700">
                                    {!! nl2br(e($json['custom_affiliate']['discount_block'])) !!}
                                </div>
                                @if(!empty($json['custom_affiliate']['link']))
                                    <a href="{{ $json['custom_affiliate']['link'] }}"
                                       target="_blank"
                                       rel="nofollow sponsored"
                                       class="inline-flex items-center mt-4 bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-3 rounded-lg transition-colors duration-200">
                                        Bekijk {{ $json['custom_affiliate']['product_name'] ?? 'dit product' }}
                                        <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </section>
                @endif

                {{-- Closing Section --}}
                @if(!empty($json['closing']))
                    <section class="my-16 bg-white rounded-3xl p-8 md:p-12 shadow-lg">
                        @if(!empty($json['closing']['headline']))
                            <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-6 leading-tight tracking-tight">
                                {{ $json['closing']['headline'] }}
                            </h2>
                        @endif
                
                @if(!empty($json['closing']['summary']))
                    <div class="prose prose-xl max-w-none mb-12">
                        <p class="text-lg text-gray-700 leading-relaxed font-light">
                            {{ $json['closing']['summary'] }}
                        </p>
                    </div>
                @endif
                
                {{-- Single Primary CTA --}}
                @if(!empty($json['closing']['primary_cta']))
                    @php
                        $cta = $json['closing']['primary_cta'];
                        $ctaUrl = match($cta['url_key'] ?? '') {
                            'custom_affiliate' => $json['custom_affiliate']['link'] ?? route('producten.index'),
                            'producten.index' => route('producten.index'),
                            'top5' => url('/top-5'),
                            'blogs.index' => route('blogs.index'),
                            'reviews.index' => route('reviews.index'),
                            default => route('producten.index')
                        };
                        $isExternalLink = ($cta['url_key'] ?? '') === 'custom_affiliate';
                    @endphp

                    <div class="text-center">
                        <a href="{{ $ctaUrl }}"
                           @if($isExternalLink) target="_blank" rel="nofollow sponsored" @endif 
                           class="inline-flex items-center bg-gray-900 hover:bg-gray-800 text-white font-medium px-8 py-4 rounded-full transition-all duration-300 text-lg shadow-lg hover:shadow-xl transform hover:-translate-y-1">
                            {{ $cta['label'] ?? 'Ontdek meer' }}
                            <svg class="w-5 h-5 ml-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                            </svg>
                        </a>
                    </div>
                        @endif
                    </section>
                @endif
            </div>
        </div>
    @elseif ($post->created_at->isAfter('2025-08-23') && $isV2)
        {{-- Legacy Container Layout for v2 --}}
        <div class="max-w-7xl mx-auto px-4 py-12">
            <div class="bg-white rounded-2xl shadow-xl p-6 sm:p-8 md:p-12">
                <!-- Terugknop -->
                <div class="mb-6">
                    <a href="{{ route('blogs.index') }}" class="inline-block bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-5 py-2 rounded-full shadow transition">
                        &larr; Terug naar overzicht
                    </a>
                </div>
                
                <!-- Header -->
                <div class="flex flex-col md:flex-row items-center md:items-start md:justify-between gap-10 mb-14">
                    <div class="md:w-2/3 text-center md:text-left">
                        <h1 class="text-3xl sm:text-4xl md:text-5xl font-extrabold bg-gradient-to-r from-blue-800 via-purple-700 to-gray-800 text-transparent bg-clip-text leading-tight mb-3">
                            {{ $post->title }}
                        </h1>
                        <p class="text-sm text-gray-500">Geplaatst op {{ $post->created_at->format('d M Y') }}</p>
                    </div>

                    @if ($post->product && $secondImage)
                        <div class="md:w-1/3 flex flex-col items-center text-center">
                            <a href="{{ $affiliateLink }}" target="_blank" rel="nofollow sponsored" class="group block">
                                <div class="p-6 bg-white rounded-xl shadow-md border border-gray-100 transition-transform duration-300 group-hover:scale-105">
                                    <img src="{{ $secondImage }}" alt="{{ $post->product->title }}" class="w-40 sm:w-56 h-40 sm:h-56 object-contain">
                                </div>
                            </a>
                            <a href="{{ $affiliateLink }}" target="_blank" rel="nofollow sponsored" class="mt-3 text-sm text-blue-600 hover:underline">
                                Bekijk dit product op bol
                            </a>
                        </div>
                    @endif
                </div>
            {{-- JSON v2 Fallback Rendering --}}
            <article class="space-y-8 sm:space-y-12 text-gray-800 leading-relaxed">
                <div class="blog-v2-content">
                    {{-- Hero Section --}}
                    @if(!empty($json['hero_kicker']) || !empty($json['one_liner']))
                        <div class="mb-16">
                            @if(!empty($json['hero_kicker']))
                                <p class="text-sm font-light text-gray-500 uppercase tracking-wide mb-4">
                                    {{ $json['hero_kicker'] }}
                                </p>
                            @endif
                            @if(!empty($json['one_liner']))
                                <p class="text-2xl text-gray-700 font-light leading-relaxed">
                                    {{ $json['one_liner'] }}
                                </p>
                            @endif
                        </div>
                    @endif

                    {{-- Sections --}}
                    @foreach(ContentJson::getArray($json, 'sections') as $section)
                        <x-content.section 
                            :type="ContentJson::getString($section, 'type')"
                            :heading="ContentJson::getString($section, 'heading')"
                            :summary="ContentJson::getString($section, 'summary')"
                            :paragraphs="ContentJson::getArray($section, 'paragraphs')"
                            :bullets="ContentJson::getArray($section, 'bullets')"
                            :steps="ContentJson::getArray($section, 'steps')"
                            :faq="ContentJson::getArray($section, 'faq')" />
                    @endforeach

                    {{-- Verdict --}}
                    @if(!empty($json['verdict']))
                        <div class="mb-16">
                            <h2 class="text-3xl font-light text-gray-900 mb-6 tracking-tight">
                                {{ ContentJson::getString($json['verdict'], 'headline', 'Onze conclusie') }}
                            </h2>
                            <p class="text-xl text-gray-600 font-light leading-relaxed">
                                {{ ContentJson::getString($json['verdict'], 'body') }}
                            </p>
                        </div>
                    @endif

                    {{-- Bottom CTA --}}
                    @if($post->product)
                        <x-cta.product-primary :product="$post->product" text="Bekijk actuele prijs" classes="mt-8" />
                    @else
                        <x-cta.list-primary text="Ontdek alle opties" classes="mt-8" />
                    @endif
                </div>
            </div>
        </div>
    @else
        {{-- Legacy Container Layout for old blogs --}}
        <div class="max-w-7xl mx-auto px-4 py-12">
            <div class="bg-white rounded-2xl shadow-xl p-6 sm:p-8 md:p-12">
                <!-- Terugknop -->
                <div class="mb-6">
                    <a href="{{ route('blogs.index') }}" class="inline-block bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-5 py-2 rounded-full shadow transition">
                        &larr; Terug naar overzicht
                    </a>
                </div>
                
                <!-- Header -->
                <div class="flex flex-col md:flex-row items-center md:items-start md:justify-between gap-10 mb-14">
                    <div class="md:w-2/3 text-center md:text-left">
                        <h1 class="text-3xl sm:text-4xl md:text-5xl font-extrabold bg-gradient-to-r from-blue-800 via-purple-700 to-gray-800 text-transparent bg-clip-text leading-tight mb-3">
                            {{ $post->title }}
                        </h1>
                        <p class="text-sm text-gray-500">Geplaatst op {{ $post->created_at->format('d M Y') }}</p>
                    </div>

                    @if ($post->product && $secondImage)
                        <div class="md:w-1/3 flex flex-col items-center text-center">
                            <a href="{{ $affiliateLink }}" target="_blank" rel="nofollow sponsored" class="group block">
                                <div class="p-6 bg-white rounded-xl shadow-md border border-gray-100 transition-transform duration-300 group-hover:scale-105">
                                    <img src="{{ $secondImage }}" alt="{{ $post->product->title }}" class="w-40 sm:w-56 h-40 sm:h-56 object-contain">
                                </div>
                            </a>
                            <a href="{{ $affiliateLink }}" target="_blank" rel="nofollow sponsored" class="mt-3 text-sm text-blue-600 hover:underline">
                                Bekijk dit product op bol
                            </a>
                        </div>
                    @endif
                </div>
                
                <!-- Fallback naar oude structuur voor bestaande blogs -->
                @if ($post->intro)
                    <section class="bg-blue-50 border-l-4 border-blue-500 pl-6 pr-4 py-4 rounded-lg">
                        <h2 class="text-xl sm:text-2xl font-bold text-blue-900 mb-3">Introductie</h2>
                        {!! $post->intro !!}
                    </section>
                @endif

                @if ($post->main_content)
                    <section class="bg-purple-50 border-l-4 border-purple-500 pl-6 pr-4 py-4 rounded-lg">
                        <h2 class="text-xl sm:text-2xl font-bold text-purple-900 mb-3">Uitleg & Achtergrond</h2>
                        {!! $post->main_content !!}
                    </section>
                @endif

                @if ($post->benefits)
                    <section class="bg-green-50 border-l-4 border-green-500 pl-6 pr-4 py-4 rounded-lg">
                        <h2 class="text-xl sm:text-2xl font-bold text-green-900 mb-3">Voordelen</h2>
                        <div class="prose prose-sm max-w-none">
                            {!! $post->benefits !!}
                        </div>
                    </section>
                @endif

                @if ($post->usage_tips)
                    <section class="bg-yellow-50 border-l-4 border-yellow-500 pl-6 pr-4 py-4 rounded-lg">
                        <h2 class="text-xl sm:text-2xl font-bold text-yellow-900 mb-3">Gebruikstips</h2>
                        {!! $post->usage_tips !!}
                    </section>
                @endif

                @if ($post->closing)
                    <section class="bg-gradient-to-br from-blue-50 to-purple-50 border border-blue-200 rounded-2xl p-6 shadow-inner">
                        <div class="flex items-center mb-4">
                            <svg class="w-7 h-7 sm:w-8 sm:h-8 text-blue-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10c0 4.418-3.582 8-8 8s-8-3.582-8-8 3.582-8 8-8 8 3.582 8 8zm-9-4a1 1 0 112 0v3a1 1 0 01-2 0V6zm1 8a1.25 1.25 0 100-2.5 1.25 1.25 0 000 2.5z" clip-rule="evenodd" />
                            </svg>
                            <h2 class="text-xl sm:text-2xl font-bold text-blue-800">Afsluiting</h2>
                        </div>
                        <div class="text-gray-700 text-base sm:text-lg">
                            {!! $post->closing !!}
                        </div>
                    </section>
                @endif
            </div>
        </div>
    @endif

@if (!($post->created_at->isAfter('2025-08-23') && $isV3))
    <!-- Legacy CTA blocks only for non-v3 content -->
    <div class="max-w-7xl mx-auto px-4">
        <!-- CTA Productblok -->
        <div class="mt-16">
        @if ($post->product)
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 text-white rounded-lg shadow-lg p-6 sm:p-8 flex flex-col items-center text-center">
                <h2 class="text-2xl sm:text-3xl font-bold mb-4">Benieuwd naar dit product?</h2>
                <p class="text-base sm:text-lg mb-6 max-w-xl">Bekijk de actuele prijs, lees reviews en ontdek waarom dit product zo populair is.</p>
                <a href="{{ $affiliateLink }}" target="_blank" rel="nofollow sponsored"
                   class="inline-block bg-yellow-400 hover:bg-yellow-500 text-black font-semibold py-3 px-8 rounded-full shadow-lg transition duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:ring-offset-2">
                    Bekijk op bol
                </a>
            </div>
        @elseif ($post->created_at->isAfter('2025-09-10') && $post->type === 'general')
            {{-- Alleen tonen voor GSC-gegenereerde content (na 10 sept 2025) --}}
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 text-white rounded-lg shadow-lg p-6 sm:p-8 flex flex-col items-center text-center">
                <h2 class="text-2xl sm:text-3xl font-bold mb-4">Op zoek naar de beste producten?</h2>
                <p class="text-base sm:text-lg mb-6 max-w-xl">Ontdek onze zorgvuldig geselecteerde producten en kies de perfecte match voor jouw situatie.</p>
                <a href="{{ route('producten.index') }}"
                   class="inline-block bg-white text-blue-800 font-semibold py-3 px-8 rounded-full shadow-lg hover:bg-gray-100 transition duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2">
                    Bekijk het assortiment
                </a>
            </div>
        @endif
    </div>

    @if ($post->created_at->isAfter('2025-09-10') && $post->type === 'general')
    <!-- CTA Top 5 - Alleen voor GSC content -->
    <section class="bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-lg shadow-lg p-6 sm:p-8 mt-16 mb-20 flex flex-col sm:flex-row items-center justify-between">
        <div class="mb-4 sm:mb-0">
            <h2 class="text-2xl sm:text-3xl font-bold mb-2">Bekijk onze Top 5 Aanbevelingen</h2>
            <p class="text-base sm:text-lg">Maak kiezen makkelijk met onze best beoordeelde producten, speciaal voor jou geselecteerd.</p>
        </div>
        <a href="{{ url('/top-5') }}"
           class="inline-block bg-white text-purple-700 font-semibold py-3 px-8 rounded-full shadow-lg hover:bg-gray-100 transition duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2">
            Bekijk de Top 5
        </a>
        </section>
    @endif

        <!-- Teruglink -->
        <div class="text-center">
            <a href="{{ route('blogs.index') }}" class="text-blue-600 hover:text-blue-800 font-semibold text-sm">
                &larr; Terug naar alle blogs
            </a>
        </div>
    </div>
@endif
@php
    use Spatie\SchemaOrg\Schema;

    $siteName = getSetting('site_name', config('app.name'));
    $logoUrl = asset(getSetting('site_logo', 'images/logo.png'));

    $structuredBlog = Schema::blogPosting()
        ->headline($post->title)
        ->description(strip_tags($post->meta_description ?? $post->intro ?? ''))
        ->author(Schema::organization()->name($siteName))
        ->datePublished($post->created_at->toW3cString())
        ->dateModified($post->updated_at->toW3cString())
        ->mainEntityOfPage(url()->current())
        ->publisher(
            Schema::organization()
                ->name($siteName)
                ->logo(
                    Schema::imageObject()->url(asset(getSetting('favicon_url', 'favicon.png')))
                )
        );

    if ($post->product) {
        $structuredBlog->about(
            Schema::product()
                ->name($post->product->title)
                ->image($post->product->images->pluck('image_url')->toArray())
                ->url($post->product->url)
        );
    }
@endphp

{{-- Sticky CTA Button - For product blogs with bol.com products --}}
@if ($post->created_at->isAfter('2025-08-23') && $isV3 && $post->product)
<div class="fixed bottom-6 left-1/2 transform -translate-x-1/2 z-50">
    <div class="bg-white shadow-2xl rounded-2xl border border-gray-200 px-6 py-4 flex items-center space-x-4 max-w-sm">
        <img src="{{ $post->product->image_url }}"
             alt="{{ $post->product->title }}"
             class="w-12 h-12 object-contain rounded-lg">
        <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900 truncate">{{ Str::limit($post->product->title, 25) }}</p>
            @if($post->product->price)
                <p class="text-lg font-bold text-gray-900">€{{ number_format($post->product->price, 2, ',', '.') }}</p>
            @endif
        </div>
        <a href="{{ $affiliateLink }}"
           target="_blank"
           rel="nofollow sponsored"
           class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-xl text-sm transition-colors duration-200 whitespace-nowrap">
            Bekijk nu
        </a>
    </div>
</div>
@endif

{{-- Sticky Discount CTA - For custom affiliate blogs (Moovv etc.) --}}
@if ($post->created_at->isAfter('2025-08-23') && $isV3 && !empty($json['custom_affiliate']['discount_block']))
    <x-cta.sticky-discount
        :discountCode="$json['custom_affiliate']['discount_code'] ?? ''"
        :discountPercentage="$json['custom_affiliate']['discount_percentage'] ?? null"
        :discountAmount="$json['custom_affiliate']['discount_amount'] ?? null"
        :discountType="$json['custom_affiliate']['discount_type'] ?? 'percentage'"
        :affiliateLink="$json['custom_affiliate']['link'] ?? '#'"
        :productName="$json['custom_affiliate']['product_name'] ?? null"
    />
@endif

<script type="application/ld+json">
{!! $structuredBlog->toScript() !!}
</script>

{{-- Product Link Hover Preview --}}
<script src="{{ asset('js/product-preview.js') }}"></script>
@endsection
