@props(['heading' => '', 'paragraphs' => []])

<section class="my-24 max-w-4xl mx-auto px-6 text-center">
  @if($heading)
    <h2 class="text-5xl md:text-6xl font-bold mb-16 leading-tight tracking-tight">
      <span class="bg-gradient-to-r from-gray-900 via-gray-800 to-gray-900 bg-clip-text text-transparent">
        {{ $heading }}
      </span>
    </h2>
  @endif
  
  <div class="space-y-8 max-w-3xl mx-auto">
    @foreach($paragraphs as $paragraph)
      @if($paragraph)
        <p class="text-2xl text-gray-600 font-light leading-relaxed">
          {{ $paragraph }}
        </p>
      @endif
    @endforeach
  </div>
</section>