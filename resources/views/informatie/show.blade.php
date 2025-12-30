@extends('layouts.app')

@section('title', strip_tags($page->meta_title ?? $page->title))
@section('meta_description', $page->meta_description ?? $page->excerpt ?? '')

@section('content')
@php
    // Extract H2 headings for Table of Contents
    $toc = [];
    if (preg_match_all('/<h2[^>]*>(.*?)<\/h2>/i', $page->content, $matches)) {
        foreach ($matches[1] as $index => $heading) {
            $cleanHeading = strip_tags($heading);
            $toc[] = [
                'id' => 'section-' . ($index + 1),
                'title' => $cleanHeading
            ];
        }
    }

    // Add IDs to H2 headings in content for anchor links
    $contentWithIds = $page->content;
    if (!empty($toc)) {
        $index = 0;
        $contentWithIds = preg_replace_callback('/<h2([^>]*)>/i', function($matches) use (&$index, $toc) {
            $id = $toc[$index]['id'] ?? '';
            $index++;
            return '<h2' . $matches[1] . ' id="' . $id . '">';
        }, $contentWithIds);
    }

    $wordCount = str_word_count(strip_tags($page->content));
    $readingTime = ceil($wordCount / 200);
@endphp

{{-- (1) BREADCRUMBS - Subtle, Clean, SEO-optimized --}}
<div class="bg-white">
    <div class="max-w-7xl mx-auto px-6 lg:px-8 py-4">
        <nav class="flex items-center text-sm text-gray-500 font-medium">
            <a href="/" class="hover:text-gray-900 transition-colors">Home</a>
            <svg class="w-3.5 h-3.5 mx-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
            <a href="#" class="hover:text-gray-900 transition-colors">Informatie</a>
            <svg class="w-3.5 h-3.5 mx-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
            <span class="text-gray-900 truncate">{{ Str::limit($page->menu_title ?? $page->title, 40) }}</span>
        </nav>
    </div>
</div>

{{-- (2) HERO HEADER - Apple-style: Clean, Bold, Spacious --}}
<div class="bg-white">
    <div class="max-w-4xl mx-auto px-6 lg:px-8 pt-16 pb-20">
        {{-- H1 Title - Large, Bold, Clean with Gradient --}}
        <h1 class="text-5xl lg:text-6xl font-bold leading-tight tracking-tight mb-6 gradient-title">
            {{ $page->title }}
        </h1>

        {{-- Subintro - Professional, Neutral Explanation --}}
        @if($page->excerpt)
            <p class="text-xl text-gray-600 leading-relaxed max-w-3xl">
                {{ $page->excerpt }}
            </p>
        @endif

        {{-- Reading metadata - subtle --}}
        <div class="flex items-center gap-6 mt-8 text-sm text-gray-500">
            <span class="inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ $readingTime }} min leestijd
            </span>
            <span class="inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                {{ $page->created_at->format('d M Y') }}
            </span>
        </div>
    </div>
</div>

{{-- (3) MAIN CONTENT AREA - Two-column layout on desktop --}}
<div class="bg-white py-12">
    <div class="max-w-7xl mx-auto px-6 lg:px-8">
        <div class="flex flex-col lg:flex-row gap-12">

            {{-- (A) MAIN ARTICLE CONTENT (70% width on desktop) --}}
            <article class="flex-1 lg:max-w-[750px] article-content">
                {!! $contentWithIds !!}
            </article>

            {{-- (B) TABLE OF CONTENTS SIDEBAR (30% width, sticky on desktop) --}}
            @if(!empty($toc))
                <aside class="hidden lg:block lg:w-80">
                    <div class="sticky top-24">
                        <div class="bg-gray-50 rounded-2xl p-6 border border-gray-200">
                            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wide mb-4">Op deze pagina</h2>
                            <nav>
                                <ul class="space-y-3">
                                    @foreach($toc as $item)
                                        <li>
                                            <a href="#{{ $item['id'] }}"
                                               class="block text-sm text-gray-600 hover:text-gray-900 transition-colors leading-snug"
                                               onclick="event.preventDefault(); document.getElementById('{{ $item['id'] }}').scrollIntoView({ behavior: 'smooth', block: 'start' });">
                                                {{ $item['title'] }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </nav>
                        </div>
                    </div>
                </aside>
            @endif

        </div>
    </div>
</div>

{{-- (5) INTERNAL SEO LINKS - Related Articles Section --}}
@php
    // Get other information pages as related content
    $relatedPages = \App\Models\InformationPage::where('id', '!=', $page->id)
        ->where('is_active', true)
        ->orderBy('order')
        ->take(3)
        ->get();
@endphp

@if($relatedPages->count() > 0)
<div class="bg-gray-50 py-16">
    <div class="max-w-7xl mx-auto px-6 lg:px-8">
        <h2 class="text-2xl font-bold text-gray-900 mb-8">Mehr Informationen over dit onderwerp</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach($relatedPages as $related)
                <a href="{{ route('information.show', $related->slug) }}"
                   class="group bg-white rounded-2xl p-6 border border-gray-200 hover:shadow-lg transition-all duration-200">
                    <h3 class="font-bold text-gray-900 mb-2 group-hover:text-blue-600 transition-colors">
                        {{ $related->menu_title ?? $related->title }}
                    </h3>
                    @if($related->excerpt)
                        <p class="text-sm text-gray-600 line-clamp-2">
                            {{ Str::limit($related->excerpt, 100) }}
                        </p>
                    @endif
                    <div class="mt-4 inline-flex items-center text-sm text-blue-600 font-medium">
                        Mehr lesen
                        <svg class="w-4 h-4 ml-1 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</div>
@endif

{{-- (6) CTA BLOCK - Apple-style with massive whitespace --}}
<div class="bg-white py-24">
    <div class="max-w-3xl mx-auto px-6 lg:px-8 text-center">
        <h2 class="text-4xl font-bold text-gray-900 mb-4">
            Op zoek naar {{ strtolower(getSetting('site_niche', 'het beste product')) }}?
        </h2>
        <p class="text-xl text-gray-600 mb-10 max-w-2xl mx-auto">
            Vergleichen alle modellen en vind het perfecte product dat bij jouw wensen en budget past.
        </p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
            <a href="{{ route('produkte.index') }}"
               class="inline-flex items-center justify-center px-8 py-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition-all duration-200 shadow-md hover:shadow-lg min-w-[200px]">
                Alle ansehen Produkte
                <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
            <a href="{{ route('produkte.top') }}"
               class="inline-flex items-center justify-center px-8 py-4 bg-white hover:bg-gray-50 text-gray-900 font-semibold rounded-xl border-2 border-gray-300 transition-all duration-200 min-w-[200px]">
                <svg class="w-5 h-5 mr-2 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                </svg>
                Ansehen onze Top 5
            </a>
        </div>
    </div>
</div>
@endsection

@section('structured-data')
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "Article",
    "headline": "{{ addslashes($page->title) }}",
    "description": "{{ addslashes($page->excerpt ?? strip_tags(Str::limit($page->content, 200))) }}",
    "author": {
        "@type": "Organization",
        "name": "{{ getSetting('site_name', config('app.name')) }}"
    },
    "publisher": {
        "@type": "Organization",
        "name": "{{ getSetting('site_name', config('app.name')) }}"
    },
    "datePublished": "{{ $page->created_at->toIso8601String() }}",
    "dateModified": "{{ $page->updated_at->toIso8601String() }}"
}
</script>
@endsection
