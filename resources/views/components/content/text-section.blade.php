@props(['heading' => '', 'subheadings' => [], 'paragraphs' => [], 'internal_links' => []])

<section class="my-12 max-w-4xl mx-auto px-6">
  @if($heading)
    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-6 leading-tight tracking-tight">
      {{ $heading }}
    </h2>
  @endif
  
  <div class="space-y-4">
    @if(!empty($subheadings) && count($subheadings) > 0)
      {{-- Structure content with H3 subheadings --}}
      @foreach($subheadings as $index => $subheading)
        @if($subheading)
          <div class="mb-6">
            <h3 class="text-xl md:text-2xl font-semibold text-gray-900 mb-3 leading-tight">
              {{ $subheading }}
            </h3>
            @if(isset($paragraphs[$index]) && $paragraphs[$index])
              <p class="text-lg text-gray-700 leading-relaxed font-light">
                {!! linkProductMentions(e($paragraphs[$index])) !!}
              </p>
            @endif
          </div>
        @endif
      @endforeach
      
      {{-- Remaining paragraphs without subheadings --}}
      @for($i = count($subheadings); $i < count($paragraphs); $i++)
        @if($paragraphs[$i])
          <p class="text-lg text-gray-700 leading-relaxed font-light">
            {!! linkProductMentions(e($paragraphs[$i])) !!}
          </p>
        @endif
      @endfor
    @else
      {{-- Standard paragraphs without subheadings --}}
      @foreach($paragraphs as $paragraph)
        @if($paragraph)
          <p class="text-lg text-gray-700 leading-relaxed font-light">
            {!! linkProductMentions(e($paragraph)) !!}
          </p>
        @endif
      @endforeach
    @endif
    
    {{-- Internal Links Section --}}
    @if(!empty($internal_links) && count($internal_links) > 0)
      <div class="mt-8 space-y-3">
        @foreach($internal_links as $link)
          @php
            $linkUrl = match($link['url_key'] ?? '') {
              'producten.index' => route('producten.index'),
              'top5' => url('/top-5'),
              'blogs.index' => route('blogs.index'),
              'reviews.index' => route('reviews.index'),
              default => $link['url_key'] ?? '#'
            };
            
            // Handle specific product/blog/review URLs
            if (str_starts_with($link['url_key'] ?? '', 'producten/')) {
              $productSlug = str_replace('producten/', '', $link['url_key']);
              $linkUrl = route('producten.show', $productSlug);
            } elseif (str_starts_with($link['url_key'] ?? '', 'blogs/')) {
              $blogSlug = str_replace('blogs/', '', $link['url_key']);
              $linkUrl = route('blogs.show', $blogSlug);
            } elseif (str_starts_with($link['url_key'] ?? '', 'reviews/')) {
              $reviewSlug = str_replace('reviews/', '', $link['url_key']);
              $linkUrl = route('reviews.show', $reviewSlug);
            }
          @endphp
          
          <a href="{{ $linkUrl }}" 
             class="inline-flex items-center text-blue-600 hover:text-blue-800 font-medium transition-colors duration-200">
            <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
            </svg>
            {{ $link['label'] ?? 'Meer informatie' }}
          </a>
        @endforeach
      </div>
    @endif
  </div>
</section>