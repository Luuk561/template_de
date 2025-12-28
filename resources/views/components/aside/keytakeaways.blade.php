@props(['items' => [], 'title' => 'Belangrijkste punten'])

@if(!empty($items))
<aside class="mb-16">
  <h3 class="text-2xl font-light text-gray-900 mb-8">{{ $title }}</h3>
  
  <ul class="space-y-4">
    @foreach($items as $item)
      @if($item)
        <li class="flex items-start text-gray-700 leading-relaxed text-lg font-light">
          <span class="w-2 h-2 bg-blue-500 rounded-full mr-4 mt-3 flex-shrink-0"></span>
          {{ $item }}
        </li>
      @endif
    @endforeach
  </ul>
</aside>
@endif