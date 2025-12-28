@props(['url' => '', 'caption' => '', 'alt' => ''])

@if($url)
<figure class="my-24 max-w-6xl mx-auto px-6">
  <div class="relative overflow-hidden rounded-2xl">
    <img src="{{ $url }}" 
         alt="{{ $alt ?: $caption }}"
         class="w-full h-auto object-cover shadow-lg"
         loading="lazy">
  </div>
  @if($caption)
    <figcaption class="text-center text-sm text-gray-500 font-light mt-6 max-w-2xl mx-auto">
      {{ $caption }}
    </figcaption>
  @endif
</figure>
@endif