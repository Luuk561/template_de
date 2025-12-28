@props(['text' => '', 'url' => '', 'type' => 'internal'])

@if($text && $url)
  <span class="inline-block">
    @if($type === 'product')
      <a href="{{ $url }}" 
         target="_blank" 
         rel="nofollow sponsored"
         class="text-blue-600 hover:text-blue-800 font-medium underline underline-offset-2 decoration-2 decoration-blue-200 hover:decoration-blue-400 transition-all duration-200">
        {{ $text }}
        <svg class="w-3 h-3 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
        </svg>
      </a>
    @else
      <a href="{{ $url }}" 
         class="text-purple-600 hover:text-purple-800 font-medium underline underline-offset-2 decoration-2 decoration-purple-200 hover:decoration-purple-400 transition-all duration-200">
        {{ $text }}
        <svg class="w-3 h-3 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
        </svg>
      </a>
    @endif
  </span>
@endif