@props(['type' => 'default', 'heading' => '', 'summary' => '', 'paragraphs' => [], 'bullets' => [], 'steps' => [], 'faq' => [], 'pros' => [], 'cons' => []])

<section class="mb-16">
  @if($heading)
    <h2 class="text-3xl sm:text-4xl font-light text-gray-900 mb-6 tracking-tight">{{ $heading }}</h2>
  @endif
  
  @if($summary)
    <p class="text-xl text-gray-600 mb-8 font-light leading-relaxed">{{ $summary }}</p>
  @endif

  <div class="prose prose-lg max-w-none">
    @foreach($paragraphs as $paragraph)
      @if($paragraph)
        <p class="text-gray-800 leading-relaxed mb-6 text-lg font-light">{{ $paragraph }}</p>
      @endif
    @endforeach

    @if(!empty($bullets))
      <ul class="space-y-3 text-lg font-light text-gray-800 mb-8">
        @foreach($bullets as $bullet)
          @if($bullet)
            <li class="flex items-start">
              <span class="w-2 h-2 bg-gray-400 rounded-full mr-4 mt-3 flex-shrink-0"></span>
              {{ $bullet }}
            </li>
          @endif
        @endforeach
      </ul>
    @endif

    @if(!empty($steps))
      <x-content.steps :items="$steps" />
    @endif

    @if(!empty($faq))
      <x-content.faq :items="$faq" />
    @endif

    @if(!empty($pros) || !empty($cons))
      <x-content.pros-cons :pros="$pros" :cons="$cons" />
    @endif
  </div>

  {{ $slot }}
</section>