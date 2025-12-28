
@props(['heading' => 'Stappen', 'items' => []])

@if(!empty($items))
<section class="my-24 max-w-4xl mx-auto px-6">
  @if($heading)
    <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-16 leading-tight tracking-tight">
      {{ $heading }}
    </h2>
  @endif
  
  <div class="space-y-12">
    @foreach($items as $index => $step)
      @if(isset($step['title']) && isset($step['detail']) && $step['title'] && $step['detail'])
        <div class="flex items-start">
          <div class="flex-shrink-0 w-8 h-8 bg-gray-900 text-white rounded-full flex items-center justify-center text-sm font-medium mr-6 mt-1">
            {{ $index + 1 }}
          </div>
          <div class="flex-1">
            <h3 class="text-2xl font-medium text-gray-900 mb-4 leading-snug">{{ $step['title'] }}</h3>
            <p class="text-xl text-gray-600 font-light leading-relaxed">{{ $step['detail'] }}</p>
          </div>
        </div>
      @endif
    @endforeach
  </div>
</section>
@endif