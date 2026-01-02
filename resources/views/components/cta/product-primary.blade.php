@props(['product' => null, 'text' => 'Preis prüfen auf Amazon', 'classes' => ''])

@if($product)
  @php
    $affiliateLink = getProductAffiliateLink($product);
  @endphp

  <div class="border border-gray-200 rounded-3xl p-8 {{ $classes }}">
    <div class="text-center">
      <h3 class="text-xl font-light text-gray-900 mb-6">{{ $product->title ?? 'Product' }}</h3>

      <a href="{{ $affiliateLink }}"
         target="_blank"
         rel="nofollow sponsored"
         data-product="{{ $product->title ?? 'Product' }}"
         class="inline-flex items-center bg-gray-900 hover:bg-gray-800 text-white font-light px-8 py-4 rounded-full transition duration-200 text-lg">
        {{ $text }}
        <svg class="w-4 h-4 ml-2" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd" />
        </svg>
      </a>
    </div>
  </div>
@endif