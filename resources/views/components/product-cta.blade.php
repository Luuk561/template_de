@props(['product', 'size' => 'default', 'classes' => ''])

@php
    $affiliateLink = getProductAffiliateLink($product);
    $isAvailable = $product->is_available ?? true;

    $sizeClasses = match($size) {
        'small' => 'text-xs py-2 px-3',
        'large' => 'py-4 px-8 text-lg',
        default => 'py-3 px-6',
    };
@endphp

@if($isAvailable)
    <a href="{{ $affiliateLink }}"
       target="_blank"
       rel="nofollow sponsored"
       class="cta-button text-white font-bold rounded-lg transition-all hover:opacity-90 text-center inline-flex items-center justify-center gap-2 {{ $sizeClasses }} {{ $classes }}">
        Preis prüfen auf Amazon
    </a>
@else
    <div class="flex flex-col gap-2 {{ $classes }}">
        <div class="bg-gray-100 text-gray-500 font-semibold rounded-lg text-center inline-flex items-center justify-center gap-2 border border-gray-200 {{ $sizeClasses }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
            </svg>
            Niet leverbaar
        </div>
        @if($size === 'default')
            <p class="text-xs text-center text-gray-500">
                Ansehen alternatieven <a href="#alternatieven" class="underline hover:text-gray-700">hieronder</a>
            </p>
        @endif
    </div>
@endif
