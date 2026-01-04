@props(['stars' => 0, 'count' => null, 'size' => 'sm'])

@php
    $rating = (float) $stars;
    $fullStars = floor($rating);

    $sizeClasses = match($size) {
        'xs' => 'w-3 h-3',
        'sm' => 'w-4 h-4',
        'md' => 'w-5 h-5',
        'lg' => 'w-6 h-6',
        default => 'w-4 h-4',
    };
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-1']) }}>
    <div class="flex items-center gap-0.5">
        @for($i = 1; $i <= 5; $i++)
            <svg class="{{ $sizeClasses }} {{ $i <= $fullStars ? 'text-yellow-400' : 'text-gray-300' }} fill-current" viewBox="0 0 20 20">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
            </svg>
        @endfor
    </div>

    @if($rating > 0)
        <span class="text-sm font-medium text-gray-700">{{ number_format($rating, 1, ',', '.') }}</span>
    @endif

    @if($count)
        <span class="text-xs text-gray-500">({{ $count }})</span>
    @endif

    {{-- Amazon Source Label --}}
    @if($rating > 0)
        <span class="text-xs text-gray-400 ml-0.5" title="Bewertungen von Amazon.de">Amazon</span>
    @endif
</div>
