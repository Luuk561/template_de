@props(['product' => null, 'text' => 'Produkt ansehen', 'classes' => ''])

@if($product)
  @php
    $affiliateLink = getProductAffiliateLink($product);
  @endphp

  <section class="my-24 {{ $classes }}">
    <div class="max-w-6xl mx-auto bg-gradient-to-br from-gray-50 via-white to-gray-50 rounded-3xl p-12 md:p-20 text-center">
      <h3 class="text-4xl md:text-5xl font-bold text-gray-900 mb-12 tracking-tight">
        {{ $product->title ?? 'Product' }}
      </h3>

      <a href="{{ $affiliateLink }}" 
         target="_blank" 
         rel="nofollow sponsored"
         class="inline-flex items-center bg-gray-900 hover:bg-gray-800 text-white font-medium px-12 py-6 rounded-full transition-all duration-300 text-xl shadow-lg hover:shadow-xl transform hover:-translate-y-1">
        {{ $text }}
        <svg class="w-5 h-5 ml-3" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd" />
        </svg>
      </a>
    </div>
  </section>
@else
  <section class="my-24 {{ $classes }}">
    <div class="max-w-6xl mx-auto bg-gradient-to-br from-gray-50 via-white to-gray-50 rounded-3xl p-12 md:p-20 text-center">
      <h3 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6 tracking-tight">
        Alle Optionen entdecken
      </h3>
      <p class="text-xl text-gray-600 font-light mb-12 max-w-2xl mx-auto">
        Vergleichen Sie alle Modelle und finden Sie die perfekte Wahl für Ihre Situation.
      </p>

      <a href="{{ route('produkte.index') }}"
         class="inline-flex items-center bg-gray-900 hover:bg-gray-800 text-white font-medium px-12 py-6 rounded-full transition-all duration-300 text-xl shadow-lg hover:shadow-xl transform hover:-translate-y-1">
        Alle Produkte ansehen
        <svg class="w-5 h-5 ml-3" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd" />
        </svg>
      </a>
    </div>
  </section>
@endif