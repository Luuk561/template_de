@props(['product' => null, 'text' => 'Dieses Produkt ansehen', 'classes' => ''])

@if($product)
  @php
    $affiliateLink = getProductAffiliateLink($product);
  @endphp

  <div class="my-12 text-center {{ $classes }}">
    <a href="{{ $affiliateLink }}"
       target="_blank"
       rel="nofollow sponsored"
       data-product="{{ $product->title ?? 'Product' }}"
       class="inline-flex items-center text-gray-600 hover:text-gray-900 font-light underline underline-offset-4 transition duration-200 text-lg">
      {{ $text }}
      <svg class="w-4 h-4 ml-2" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
      </svg>
    </a>
  </div>
@endif