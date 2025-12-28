@props(['product' => null, 'classes' => ''])

@if($product && $product->url)
  @php
    $sid = env('BOL_SITE_ID', 'fallback_id');
    $affiliateLink = 'https://partner.bol.com/click/click?p=2&t=url&s=' . $sid . '&f=TXL&url=' . urlencode($product->url) . '&name=' . urlencode($product->title ?? '');
  @endphp

  <div class="max-w-4xl mx-auto mb-16 text-center {{ $classes }}">
    <p class="text-lg text-gray-500 font-light">
      Dit artikel gaat over: 
      <a href="{{ $affiliateLink }}" 
         target="_blank" 
         rel="nofollow sponsored"
         class="text-gray-900 font-medium hover:underline underline-offset-4">
        {{ $product->title ?? 'Product' }}
        @if($product->price)
          (€{{ number_format($product->price, 2, ',', '.') }})
        @endif
      </a>
    </p>
  </div>
@endif