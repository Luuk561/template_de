@props(['product' => null, 'classes' => ''])

@if($product)
  @php
    $affiliateLink = getProductAffiliateLink($product);
  @endphp

  <div class="max-w-4xl mx-auto mb-16 text-center {{ $classes }}">
    <p class="text-lg text-gray-500 font-light">
      Dieser Artikel handelt von:
      <a href="{{ $affiliateLink }}"
         target="_blank"
         rel="nofollow sponsored"
         class="text-gray-900 font-medium hover:underline underline-offset-4">
        {{ $product->title ?? 'Produkt' }}
      </a>
    </p>
  </div>
@endif