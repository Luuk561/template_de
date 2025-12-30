@extends('layouts.app')

{{-- Meta tags --}}
@section('title', $pageTitle ?? $teamMember->name . ' - ' . getSetting('site_name'))
@section('meta_description', $metaDescription ?? $teamMember->quote)

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        'Team' => route('team.index'),
        $teamMember->name => route('team.show', $teamMember->slug),
    ]" />
@endsection

@section('content')

@php
    $primaryColor = getSetting('primary_color', '#7c3aed');
    $siteName = getSetting('site_name');
@endphp

<style>
    /* Force prose styling to work despite wrapper divs */
    .bio-content .team-bio,
    .bio-content .team-bio > div {
        all: unset;
        display: block;
    }

    .bio-content h2 {
        font-size: 1.875rem !important;
        font-weight: 700 !important;
        color: #111827 !important;
        margin-top: 3rem !important;
        margin-bottom: 1.5rem !important;
    }

    .bio-content h2:first-of-type {
        margin-top: 0 !important;
    }

    .bio-content p {
        color: #374151 !important;
        line-height: 1.75 !important;
        margin-bottom: 1.5rem !important;
    }

    .bio-content a {
        color: #9333ea !important;
        text-decoration: none !important;
    }

    .bio-content a:hover {
        text-decoration: underline !important;
    }
</style>

<!-- PROFILE HEADER WITH GRADIENT -->
<section class="w-full bg-gradient-to-br from-purple-50 via-blue-50 to-white py-8 md:py-20">
    <div class="max-w-5xl mx-auto px-4">
        <!-- Back Button -->
        <a href="{{ route('team.index') }}" class="inline-flex items-center gap-2 text-gray-600 hover:text-purple-600 transition-colors mb-6 md:mb-8 group">
            <svg class="w-5 h-5 group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Terug naar team
        </a>

        <div class="flex flex-col md:flex-row items-center md:items-start gap-6 md:gap-8">
            <!-- Photo -->
            @if($teamMember->photo_url)
            <div class="w-32 h-32 md:w-40 md:h-40 rounded-full overflow-hidden flex-shrink-0 border-4 border-white shadow-xl">
                <img
                    src="{{ $teamMember->photo_url }}"
                    alt="{{ $teamMember->name }}"
                    loading="lazy"
                    class="w-full h-full object-cover"
                >
            </div>
            @else
            <div class="w-32 h-32 md:w-40 md:h-40 rounded-full bg-gradient-to-br from-purple-400 to-blue-400 flex items-center justify-center flex-shrink-0 border-4 border-white shadow-xl">
                <span class="text-4xl md:text-5xl font-bold text-white">{{ substr($teamMember->name, 0, 1) }}</span>
            </div>
            @endif

            <!-- Info -->
            <div class="flex-1 text-center md:text-left">
                <h1 class="text-4xl sm:text-5xl md:text-6xl font-bold text-gray-900 mb-2 md:mb-3">{{ $teamMember->name }}</h1>
                <p class="text-xl md:text-2xl text-purple-600 font-medium mb-4 md:mb-6">{{ $teamMember->role }}</p>

                <!-- Quote -->
                <blockquote class="text-base md:text-xl text-gray-700 italic mb-4 md:mb-6 max-w-2xl mx-auto md:mx-0">
                    "{{ $teamMember->quote }}"
                </blockquote>

                <!-- Tags & Stats -->
                <div class="flex flex-wrap items-center justify-center md:justify-start gap-3 md:gap-4">
                    <div class="inline-flex items-center gap-2 bg-white px-3 md:px-4 py-1.5 md:py-2 rounded-full shadow-sm border border-gray-200">
                        <svg class="w-4 md:w-5 h-4 md:h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-xs md:text-sm font-medium text-gray-700">{{ ucfirst($teamMember->focus) }}</span>
                    </div>

                    <div class="inline-flex items-center gap-2 bg-white px-3 md:px-4 py-1.5 md:py-2 rounded-full shadow-sm border border-gray-200">
                        <svg class="w-4 md:w-5 h-4 md:h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        <span class="text-xs md:text-sm font-medium text-gray-700">{{ $reviews->count() + $blogs->count() }} artikel{{ ($reviews->count() + $blogs->count()) !== 1 ? 'en' : '' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- BIO CONTENT -->
<section class="w-full py-8 md:py-16 bg-white">
    <div class="max-w-4xl mx-auto px-4">
        <div class="bio-content prose prose-sm md:prose-lg prose-purple max-w-none">
            {!! $teamMember->bio !!}
        </div>
    </div>
</section>

<!-- CONTENT BY THIS MEMBER -->
@if($reviews->count() > 0 || $blogs->count() > 0)
<section class="w-full py-8 md:py-16 bg-gray-50">
    <div class="max-w-5xl mx-auto px-4">
        <div class="text-center mb-8 md:mb-12">
            <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-2 md:mb-3">Geschreven door {{ $teamMember->name }}</h2>
            <p class="text-base md:text-lg text-gray-600">{{ $reviews->count() + $blogs->count() }} artikel{{ $reviews->count() + $blogs->count() !== 1 ? 'en' : '' }} en nog veel meer onderweg</p>
        </div>

        <div class="grid lg:grid-cols-2 gap-6 md:gap-8">
            @if($reviews->count() > 0)
            <!-- Testberichte -->
            <div>
                <div class="flex items-center gap-2 md:gap-3 mb-4 md:mb-6">
                    <svg class="w-5 md:w-6 h-5 md:h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                    </svg>
                    <h3 class="text-xl md:text-2xl font-bold text-gray-900">Testberichte</h3>
                    <span class="bg-purple-100 text-purple-700 px-2 md:px-3 py-1 rounded-full text-xs md:text-sm font-medium">{{ $reviews->count() }}</span>
                </div>
                <div class="space-y-3 md:space-y-4">
                    @foreach($reviews as $review)
                    <a href="{{ route('testberichte.show', $review->slug) }}"
                       class="block p-6 bg-white hover:shadow-lg rounded-xl border border-gray-200 hover:border-purple-200 transition-all group">
                        <h4 class="font-bold text-gray-900 mb-2 group-hover:text-purple-600 transition-colors line-clamp-2">
                            {{ $review->title }}
                        </h4>
                        @if($review->rating)
                        <div class="flex items-center gap-1 mb-3">
                            @for($i = 1; $i <= 5; $i++)
                                <svg class="w-5 h-5 {{ $i <= $review->rating ? 'text-yellow-400' : 'text-gray-300' }} fill-current"
                                     xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/>
                                </svg>
                            @endfor
                            <span class="ml-2 text-sm text-gray-600 font-medium">{{ number_format($review->rating, 1) }}</span>
                        </div>
                        @endif
                        <p class="text-sm text-gray-500">{{ $review->created_at->format('d M Y') }}</p>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif

            @if($blogs->count() > 0)
            <!-- Blogs -->
            <div>
                <div class="flex items-center gap-2 md:gap-3 mb-4 md:mb-6">
                    <svg class="w-5 md:w-6 h-5 md:h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                    <h3 class="text-xl md:text-2xl font-bold text-gray-900">Blogs</h3>
                    <span class="bg-blue-100 text-blue-700 px-2 md:px-3 py-1 rounded-full text-xs md:text-sm font-medium">{{ $blogs->count() }}</span>
                </div>
                <div class="space-y-3 md:space-y-4">
                    @foreach($blogs as $blog)
                    <a href="{{ route('ratgeber.show', $blog->slug) }}"
                       class="block p-6 bg-white hover:shadow-lg rounded-xl border border-gray-200 hover:border-blue-200 transition-all group">
                        <h4 class="font-bold text-gray-900 group-hover:text-blue-600 transition-colors mb-2 line-clamp-2">
                            {{ $blog->title }}
                        </h4>
                        <p class="text-sm text-gray-500">{{ $blog->created_at->format('d M Y') }}</p>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
</section>
@else
<!-- No Content Yet -->
<section class="w-full py-8 md:py-16 bg-gray-50">
    <div class="max-w-4xl mx-auto px-4 text-center">
        <div class="bg-white rounded-2xl p-8 md:p-12 border border-gray-200">
            <svg class="w-16 h-16 md:w-20 md:h-20 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            <h2 class="text-xl md:text-2xl font-bold text-gray-900 mb-2">Binnenkort meer content</h2>
            <p class="text-sm md:text-base text-gray-600">{{ $teamMember->name }} is bezig met nieuwe Testberichte en artikelen.</p>
        </div>
    </div>
</section>
@endif

@endsection
