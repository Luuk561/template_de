@props(['pros' => [], 'cons' => [], 'heading' => 'Voor- en nadelen'])

@if(!empty($pros) || !empty($cons))
<section class="my-24 max-w-5xl mx-auto px-6">
  @if($heading)
    <h2 class="text-4xl md:text-5xl font-bold text-gray-900 mb-16 leading-tight tracking-tight text-center">
      {{ $heading }}
    </h2>
  @endif
  
  <div class="grid md:grid-cols-2 gap-8">
    @if(!empty($pros))
      <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-2xl p-8 border-l-4 border-green-500">
        <div class="flex items-center mb-6">
          <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center mr-3">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <h3 class="text-2xl font-semibold text-green-800">Voordelen</h3>
        </div>
        <ul class="space-y-4">
          @foreach($pros as $pro)
            @if($pro)
              <li class="flex items-start">
                <svg class="w-5 h-5 text-green-600 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <span class="text-lg text-gray-700 font-light">{{ $pro }}</span>
              </li>
            @endif
          @endforeach
        </ul>
      </div>
    @endif
    
    @if(!empty($cons))
      <div class="bg-gradient-to-br from-red-50 to-pink-50 rounded-2xl p-8 border-l-4 border-red-500">
        <div class="flex items-center mb-6">
          <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center mr-3">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </div>
          <h3 class="text-2xl font-semibold text-red-800">Nadelen</h3>
        </div>
        <ul class="space-y-4">
          @foreach($cons as $con)
            @if($con)
              <li class="flex items-start">
                <svg class="w-5 h-5 text-red-600 mr-3 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
                <span class="text-lg text-gray-700 font-light">{{ $con }}</span>
              </li>
            @endif
          @endforeach
        </ul>
      </div>
    @endif
  </div>
</section>
@endif