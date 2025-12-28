@extends('layouts.app')

@section('title', strip_tags($review->seo_title))
@section('meta_description', strip_tags($review->seo_description ?? $review->meta_description ?? ''))

@if($review->meta_robots)
@section('meta_robots', $review->meta_robots)
@endif

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        'Reviews' => route('reviews.index'),
        \Illuminate\Support\Str::limit($review->title, 60) => ''
    ]" />
@endsection

@section('content')
<!-- Content vanuit database -->
        @php
            use App\Support\ContentJson;
            $json = ContentJson::decode($review->content);
            $isV3 = ContentJson::isV3($json, 'review.v3');
            $isV2 = ContentJson::isV2($json, 'review.v2');
        @endphp

    @php
        $product = $review->product;

        // Check if this is a custom review (Moovv, etc.) with custom affiliate link
        $customAffiliate = isset($json['custom_affiliate']) ? $json['custom_affiliate'] : null;

        if ($customAffiliate) {
            // Custom affiliate product (e.g., Moovv)
            $affiliateLink = $customAffiliate['link'];
        } else {
            // Regular bol.com product
            $affiliateLink = getBolAffiliateLink($product->url, $product->title);
        }
    @endphp

    @if ($review->created_at->isAfter('2025-08-23') && $isV3)
        {{-- JSON v3 Premium Review Rendering --}}
        
        {{-- White Header Section - Full Width --}}
        <div class="w-full bg-white">
            {{-- Back Button --}}
            <div class="max-w-6xl mx-auto px-6 pt-8 pb-4">
                <a href="{{ route('reviews.index') }}" class="inline-flex items-center text-gray-600 hover:text-gray-900 transition-colors duration-200">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Terug naar reviews
                </a>
            </div>
            
            {{-- Header Content --}}
            <div class="max-w-6xl mx-auto px-6 pt-8 pb-16">
            <div class="grid md:grid-cols-2 gap-12 items-center">
                {{-- Product Visual --}}
                @if($review->image_url || $product?->image_url)
                    <div class="text-center">
                        <img src="{{ $review->image_url ?? $product->image_url }}"
                             alt="{{ Str::limit($customAffiliate['product_name'] ?? $product->title, 80) }}"
                             class="w-80 h-80 object-contain mx-auto">
                        @if($product?->price)
                            <p class="text-3xl font-light text-gray-600 mt-6">
                                €{{ number_format($product->price, 2, ',', '.') }}
                            </p>
                        @endif
                    </div>
                @endif
                
                {{-- Review Title & Intro --}}
                <div class="text-center md:text-left">
                    <div class="mb-4">
                        <span class="inline-block bg-purple-100 text-purple-700 px-4 py-2 rounded-full text-sm font-medium">
                            Product Review
                        </span>
                    </div>
                    
                    <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6 leading-tight tracking-tight">
                        {{ Str::limit($review->title, 120) }}
                    </h1>

                    @if($review->teamMember)
                        <div class="mb-4">
                            <x-author-byline :teamMember="$review->teamMember" :date="$review->created_at" :compact="true" />
                        </div>
                    @else
                        <p class="text-sm text-gray-500 mb-4">
                            Door {{ getSetting('site_name', config('app.name', 'Website')) }}
                        </p>
                    @endif

                    <div class="inline-flex items-center text-gray-500 text-sm mb-6">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 002 2z" />
                        </svg>
                        {{ $review->created_at->format('d M Y') }} • Product Review • {{ ceil(str_word_count(strip_tags($review->content)) / 200) }} min leestijd
                    </div>
                    
                    @if(!empty($json['intro']))
                        <p class="text-lg text-gray-700 leading-relaxed">
                            {{ $json['intro'] }}
                        </p>
                    @endif
                </div>
            </div>
            </div>
        </div>

        {{-- Gray Content Section --}}
        <div class="w-full bg-gray-100 min-h-screen py-8">
            <div class="max-w-4xl mx-auto px-6">
                {{-- Content Sections --}}
                @foreach(ContentJson::getArray($json, 'sections') as $section)
                    @switch($section['type'] ?? '')
                        @case('text')
                            <x-content.text-section 
                                :heading="ContentJson::getString($section, 'heading')"
                                :paragraphs="ContentJson::getArray($section, 'paragraphs')" />
                            @break
                            
                        @case('pros_cons')
                            <x-content.pros-cons 
                                :pros="ContentJson::getArray($section, 'pros')"
                                :cons="ContentJson::getArray($section, 'cons')" />
                            @break
                            
                        @case('quote')
                            <x-content.quote :quote="ContentJson::getString($section, 'quote')" />
                            @break
                            
                        @case('image')
                            <x-content.image 
                                :url="ContentJson::getString($section, 'url')"
                                :caption="ContentJson::getString($section, 'caption')"
                                :alt="ContentJson::getString($section, 'alt')" />
                            @break
                            
                        @case('faq')
                            <x-content.faq :items="ContentJson::getArray($section, 'items')" />
                            @break
                            
                        @case('conclusion')
                            <x-content.conclusion 
                                :heading="ContentJson::getString($section, 'heading')"
                                :paragraphs="ContentJson::getArray($section, 'paragraphs')" />
                            @break
                            
                        @case('steps')
                            <x-content.steps 
                                :heading="ContentJson::getString($section, 'heading')"
                                :items="ContentJson::getArray($section, 'items')" />
                            @break
                            
                        @case('pros-cons')
                            <x-content.pros-cons 
                                :heading="ContentJson::getString($section, 'heading')"
                                :pros="ContentJson::getArray($section, 'pros')"
                                :cons="ContentJson::getArray($section, 'cons')" />
                            @break
                    @endswitch
                @endforeach

                {{-- Review Verdict --}}
                @if(!empty($json['verdict']))
                    <section class="my-16 bg-white rounded-3xl p-8 md:p-12 shadow-lg">
                        <h2 class="text-2xl md:text-3xl font-bold text-center text-gray-900 mb-8 tracking-tight">
                            {{ ContentJson::getString($json['verdict'], 'headline', 'Onze eindconclusie') }}
                        </h2>
                            
                            <div class="grid md:grid-cols-2 gap-12 mb-12">
                                @if(!empty($json['verdict']['buy_if']))
                                    <div class="text-center">
                                        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                                            <svg class="w-8 h-8 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <h3 class="text-xl font-bold text-gray-900 mb-4">Perfect als je:</h3>
                                        <ul class="space-y-3 text-lg text-gray-600 font-light">
                                            @foreach($json['verdict']['buy_if'] as $reason)
                                                <li>{{ $reason }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                                
                                @if(!empty($json['verdict']['skip_if']))
                                    <div class="text-center">
                                        <div class="w-16 h-16 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-6">
                                            <svg class="w-8 h-8 text-orange-500" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <h3 class="text-xl font-bold text-gray-900 mb-4">Sla over als:</h3>
                                        <ul class="space-y-3 text-lg text-gray-600 font-light">
                                            @foreach($json['verdict']['skip_if'] as $reason)
                                                <li>{{ $reason }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                            
                        @if(!empty($json['verdict']['bottom_line']))
                            <div class="text-center mt-8">
                                <p class="text-lg md:text-xl font-light text-gray-700 italic">
                                    "{{ ContentJson::getString($json['verdict'], 'bottom_line') }}"
                                </p>
                            </div>
                        @endif
                    </section>
                @endif

                {{-- Bottom CTA Hero --}}
                <div class="mt-16">
                    @if($customAffiliate)
                        {{-- Custom affiliate CTA --}}
                        <section class="my-24">
                            <div class="max-w-6xl mx-auto bg-gradient-to-br from-gray-50 via-white to-gray-50 rounded-3xl p-12 md:p-20 text-center">
                                <h3 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6 tracking-tight">
                                    {{ $customAffiliate['product_name'] ?? $review->title }}
                                </h3>

                                @if(isset($customAffiliate['discount_code']))
                                    <div class="mb-8 inline-block bg-green-50 border-2 border-green-200 rounded-xl p-6">
                                        <p class="text-lg font-semibold text-green-900 mb-2">
                                            @if(isset($customAffiliate['discount_amount']))
                                                €{{ $customAffiliate['discount_amount'] }} korting met code:
                                            @else
                                                {{ $customAffiliate['discount_percentage'] ?? 10 }}% korting met code:
                                            @endif
                                        </p>
                                        <code class="text-2xl font-bold text-green-700 bg-white px-4 py-2 rounded border-2 border-green-300">
                                            {{ $customAffiliate['discount_code'] }}
                                        </code>
                                    </div>
                                @endif

                                <a href="{{ $affiliateLink }}"
                                   target="_blank"
                                   rel="nofollow sponsored"
                                   class="inline-flex items-center bg-gray-900 hover:bg-gray-800 text-white font-medium px-12 py-6 rounded-full transition-all duration-300 text-xl shadow-lg hover:shadow-xl transform hover:-translate-y-1">
                                    Bekijk {{ $customAffiliate['product_name'] ?? 'product' }}
                                    <svg class="w-5 h-5 ml-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            </div>
                        </section>
                    @else
                        <x-cta.hero-bottom :product="$review->product" text="Bekijk actuele prijs" />
                    @endif
                </div>
            </div>
        </div>
    @elseif ($review->created_at->isAfter('2025-08-23') && $isV2)
        {{-- Legacy Container Layout for v2 --}}
        <div class="max-w-7xl mx-auto px-4 py-12">
            <div class="bg-white rounded-2xl shadow-xl p-6 sm:p-8 md:p-12">
                <!-- Terugknop -->
                <div class="mb-6">
                    <a href="{{ route('reviews.index') }}" class="inline-block bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-5 py-2 rounded-full shadow transition">
                        &larr; Terug naar overzicht
                    </a>
                </div>

                <!-- Productinformatie blok -->
                <div class="flex flex-col sm:flex-row items-start gap-6 mb-12">
                    @if($product?->image_url)
                        <a href="{{ $affiliateLink }}" target="_blank" rel="nofollow sponsored" class="block w-full sm:w-1/3">
                            <img src="{{ $product->image_url }}"
                                 alt="{{ Str::limit($product->title, 80) }}"
                                 class="w-full h-auto object-contain">
                        </a>
                    @endif

                    <div class="flex-1">
                        @if($product?->title)
                            <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 leading-tight mb-4">
                                {{ Str::limit($product->title, 80) }}
                            </h1>
                        @endif

                        <a href="{{ $affiliateLink }}" target="_blank" rel="nofollow sponsored"
                           class="inline-block bg-yellow-400 hover:bg-yellow-500 text-black font-semibold py-2 px-4 rounded-full transition">
                            Bekijk op bol
                        </a>
                    </div>
                </div>
                {{-- JSON v2 Rendering --}}
                <div class="review-v2-content">
                    {{-- Product Context & Key Takeaways --}}
                    @if(!empty($json['product_context']) || !empty($json['key_takeaways']))
                        <div class="bg-gradient-to-r from-purple-50 to-blue-50 rounded-2xl p-6 mb-8">
                            @if(!empty($json['product_context']['use_cases']))
                                <h3 class="font-semibold text-purple-900 mb-3">Geschikt voor:</h3>
                                <div class="flex flex-wrap gap-2 mb-4">
                                    @foreach($json['product_context']['use_cases'] as $useCase)
                                        <span class="bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm">{{ $useCase }}</span>
                                    @endforeach
                                </div>
                            @endif
                            
                            @if(!empty($json['key_takeaways']))
                                <x-aside.keytakeaways :items="$json['key_takeaways']" title="Review hoogtepunten" />
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
                            :faq="ContentJson::getArray($section, 'faq')"
                            :pros="ContentJson::getArray($section, 'pros')"
                            :cons="ContentJson::getArray($section, 'cons')"
                        >
                            {{-- Internal Links --}}
                            @if(!empty($section['internal_links']))
                                <div class="mt-4 flex flex-wrap gap-2">
                                    @foreach($section['internal_links'] as $link)
                                        @if(isset($link['label']) && isset($link['url_key']))
                                            <a href="{{ ContentJson::mapInternalUrl($link['url_key']) }}"
                                               class="inline-flex items-center text-purple-600 hover:text-purple-700 font-medium text-sm underline">
                                                {{ $link['label'] }}
                                                <svg class="w-3 h-3 ml-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                                </svg>
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                            {{-- Inline CTA --}}
                            @if(isset($section['recommend_cta_inline']) && $section['recommend_cta_inline'] && $review->product)
                                <x-cta.product-inline :product="$review->product" text="Bekijk dit product" />
                            @endif
                        </x-content.section>
                    @endforeach

                    {{-- Verdict Section --}}
                    @if(!empty($json['verdict']))
                        <div class="bg-gradient-to-br from-purple-50 to-pink-50 border border-purple-200 rounded-2xl p-6 shadow-inner">
                            <div class="flex items-center mb-4">
                                <svg class="w-7 h-7 text-purple-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" clip-rule="evenodd" />
                                    <path fill-rule="evenodd" d="M4 5a2 2 0 012-2v1a1 1 0 001 1h6a1 1 0 001-1V3a2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm2.5 7a1.5 1.5 0 100-3 1.5 1.5 0 000 3zm2.45.5a2.5 2.5 0 11-3.4-3.4 2.5 2.5 0 013.4 3.4zm1.05-.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3z" clip-rule="evenodd" />
                                </svg>
                                <h2 class="text-xl font-bold text-purple-900">Ons eindoordeel</h2>
                            </div>
                            
                            @if(!empty($json['verdict']['score_explained']))
                                <p class="text-gray-800 mb-4 leading-relaxed">{{ $json['verdict']['score_explained'] }}</p>
                            @endif
                            
                            <div class="grid md:grid-cols-2 gap-4 mb-4">
                                @if(!empty($json['verdict']['buy_if']))
                                    <div class="bg-green-50 border border-green-200 rounded-xl p-4">
                                        <h4 class="font-semibold text-green-900 mb-2 flex items-center">
                                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                            </svg>
                                            Koop dit als je:
                                        </h4>
                                        <ul class="text-green-800 text-sm space-y-1">
                                            @foreach($json['verdict']['buy_if'] as $reason)
                                                <li class="flex items-start">
                                                    <span class="w-1.5 h-1.5 bg-green-600 rounded-full mr-2 mt-2 flex-shrink-0"></span>
                                                    {{ $reason }}
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                                
                                @if(!empty($json['verdict']['skip_if']))
                                    <div class="bg-red-50 border border-red-200 rounded-xl p-4">
                                        <h4 class="font-semibold text-red-900 mb-2 flex items-center">
                                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                            Sla over als je:
                                        </h4>
                                        <ul class="text-red-800 text-sm space-y-1">
                                            @foreach($json['verdict']['skip_if'] as $reason)
                                                <li class="flex items-start">
                                                    <span class="w-1.5 h-1.5 bg-red-600 rounded-full mr-2 mt-2 flex-shrink-0"></span>
                                                    {{ $reason }}
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                            
                            @if(!empty($json['verdict']['bottom_line']))
                                <div class="bg-white/70 rounded-lg p-4 border border-purple-200">
                                    <p class="font-semibold text-purple-900">{{ $json['verdict']['bottom_line'] }}</p>
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Bottom CTA --}}
                    @if($review->product)
                        <x-cta.product-primary :product="$review->product" text="Bekijk actuele prijs" classes="mt-8" />
                    @else
                        <x-cta.list-primary text="Ontdek alle producten" classes="mt-8" />
                    @endif
                </div>
            </div>
        </div>
    @else
        {{-- Oude reviews zonder JSON structuur --}}
        <div class="max-w-7xl mx-auto px-4 py-12">
            <div class="bg-white rounded-2xl shadow-xl p-6 sm:p-8 md:p-12">
                <!-- Terugknop -->
                <div class="mb-6">
                    <a href="{{ route('reviews.index') }}" class="inline-block bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-5 py-2 rounded-full shadow transition">
                        &larr; Terug naar overzicht
                    </a>
                </div>

                <!-- Productinformatie blok -->
                <div class="flex flex-col sm:flex-row items-start gap-6 mb-12">
                    @if($product?->image_url)
                        <a href="{{ $affiliateLink }}" target="_blank" rel="nofollow sponsored" class="block w-full sm:w-1/3">
                            <img src="{{ $product->image_url }}"
                                 alt="{{ Str::limit($product->title, 80) }}"
                                 class="w-full h-auto object-contain">
                        </a>
                    @endif

                    <div class="flex-1">
                        @if($product?->title)
                            <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 leading-tight mb-4">
                                {{ Str::limit($product->title, 80) }}
                            </h1>
                        @endif

                        <a href="{{ $affiliateLink }}" target="_blank" rel="nofollow sponsored"
                           class="inline-block bg-yellow-400 hover:bg-yellow-500 text-black font-semibold py-2 px-4 rounded-full transition">
                            Bekijk op bol
                        </a>
                    </div>
                </div>
                
                <!-- Fallback naar oude structuur voor bestaande reviews -->
                <article class="space-y-8 sm:space-y-12 text-gray-800 leading-relaxed">
                @if ($review->intro)
                    <section class="border-l-4 border-blue-600 pl-4 sm:pl-6">
                        <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-4">Introductie</h2>
                        {!! $review->intro !!}
                    </section>
                @endif

                @if ($review->experience)
                    <section class="border-l-4 border-purple-600 pl-4 sm:pl-6">
                        <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-4">Gebruikservaring</h2>
                        {!! $review->experience !!}
                    </section>
                @endif

                @if ($review->positives)
                    <section class="border-l-4 border-green-600 pl-4 sm:pl-6">
                        <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-4">Pluspunten</h2>
                        <div class="prose prose-sm sm:prose-base max-w-none">
                            {!! $review->positives !!}
                        </div>
                    </section>
                @endif

                @if ($review->conclusion)
                    <section class="bg-gradient-to-br from-purple-50 to-blue-50 border border-purple-200 rounded-2xl p-6 shadow-inner">
                        <div class="flex items-center mb-4">
                            <svg class="w-6 sm:w-8 h-6 sm:h-8 text-purple-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10c0 4.418-3.582 8-8 8s-8-3.582-8-8 3.582-8 8-8 8 3.582 8 8zm-9-4a1 1 0 112 0v3a1 1 0 01-2 0V6zm1 8a1.25 1.25 0 100-2.5 1.25 1.25 0 000 2.5z" clip-rule="evenodd" />
                            </svg>
                            <h2 class="text-xl sm:text-2xl font-bold text-purple-800">Conclusie</h2>
                        </div>
                        <div class="text-gray-700 text-sm sm:text-base sm:text-lg">
                            {!! $review->conclusion !!}
                        </div>
                    </section>
                @endif
                </article>
            </div>
        </div>
    @endif

    <!-- CTA onderaan -->
    <div class="max-w-7xl mx-auto px-4">
        <section class="bg-gradient-to-r from-blue-600 to-blue-800 text-white rounded-xl shadow-lg p-6 sm:p-8 mb-16 text-center">
            <h2 class="text-2xl sm:text-3xl font-bold mb-4">Benieuwd naar andere producten?</h2>
            <p class="text-base sm:text-lg mb-6 max-w-2xl mx-auto">
                Ontdek ons volledige assortiment en vind het product dat het beste bij jouw wensen past.
            </p>
            <a href="{{ route('producten.index') }}"
            class="inline-block bg-white text-blue-800 font-semibold py-3 px-8 rounded-full shadow-lg hover:bg-gray-100 transition duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2">
                Bekijk alle producten
            </a>
        </section>

        <!-- Teruglink -->
        <div class="mt-12 text-center">
            <a href="{{ route('reviews.index') }}" class="text-blue-600 hover:text-blue-800 font-semibold text-sm">
                &larr; Terug naar alle reviews
            </a>
        </div>
    </div>
@php
    use Spatie\SchemaOrg\Schema;

    $siteName = getSetting('site_name', config('app.name'));
    $favicon = getSetting('favicon_url', asset('favicon.png'));

    $product = $review->product;

    $structuredReview = Schema::review()
        ->name($review->title)
        ->reviewBody(strip_tags($review->experience ?? $review->intro ?? $review->conclusion ?? ''))
        ->datePublished($review->created_at->toW3cString())
        ->author(Schema::organization()->name($siteName))
        ->publisher(
            Schema::organization()
                ->name($siteName)
                ->logo(
                    Schema::imageObject()->url($favicon)
                )
        );

    if ($product) {
        $structuredReview->itemReviewed(
            Schema::product()
                ->name($product->title)
                ->image([$product->image_url])
                ->sku($product->ean ?? $product->id)
                ->url($product->url)
        );

        if ($product->rating_average) {
            $structuredReview->reviewRating(
                Schema::rating()
                    ->ratingValue($product->rating_average)
                    ->bestRating(5)
            );
        }
    }
@endphp

{{-- Sticky CTA Button - Only for v3 reviews --}}
@if ($review->created_at->isAfter('2025-08-23') && $isV3 && $product)
<div class="fixed bottom-6 left-1/2 transform -translate-x-1/2 z-50">
    <div class="bg-white shadow-2xl rounded-2xl border border-gray-200 px-6 py-4 flex items-center space-x-4 max-w-sm">
        <img src="{{ $product->image_url }}" 
             alt="{{ $product->title }}" 
             class="w-12 h-12 object-contain rounded-lg">
        <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900 truncate">{{ Str::limit($product->title, 25) }}</p>
            @if($product->price)
                <p class="text-lg font-bold text-gray-900">€{{ number_format($product->price, 2, ',', '.') }}</p>
            @endif
        </div>
        <a href="{{ $affiliateLink }}" 
           target="_blank"
           rel="nofollow sponsored"
           class="bg-green-600 hover:bg-green-700 text-white font-semibold px-4 py-2 rounded-xl text-sm transition-colors duration-200 whitespace-nowrap">
            Bekijk op bol.com
        </a>
    </div>
</div>
@endif

<script type="application/ld+json">
{!! $structuredReview->toScript() !!}
</script>
@endsection

{{-- Sticky Discount CTA for Custom Reviews --}}
@if($customAffiliate && isset($customAffiliate['discount_code']))
    <x-cta.sticky-discount
        :discountCode="$customAffiliate['discount_code']"
        :discountPercentage="$customAffiliate['discount_percentage'] ?? 10"
        :affiliateLink="$affiliateLink"
        :productName="$customAffiliate['product_name'] ?? null"
    />
@endif
