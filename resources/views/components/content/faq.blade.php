@props(['items' => []])

@if(!empty($items))
<section class="my-24 max-w-4xl mx-auto px-6">
  <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-16 leading-tight tracking-tight">
    Veelgestelde vragen
  </h2>
  
  <div class="space-y-12">
    @foreach($items as $item)
      @if(isset($item['q']) && isset($item['a']) && $item['q'] && $item['a'])
        <div class="border-b border-gray-100 pb-8 last:border-b-0">
          <h3 class="text-2xl font-medium text-gray-900 mb-4 leading-snug">
            {{ $item['q'] }}
          </h3>
          <p class="text-xl text-gray-600 font-light leading-relaxed">
            {{ $item['a'] }}
          </p>
        </div>
      @endif
    @endforeach
  </div>
</section>
@endif