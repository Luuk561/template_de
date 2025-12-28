@props(['quote' => ''])

@if($quote)
<div class="my-24 max-w-4xl mx-auto px-6">
  <div class="bg-gradient-to-br from-blue-50 via-purple-50 to-pink-50 rounded-3xl p-12 md:p-16 text-center border-l-4 border-blue-500">
    <div class="relative">
      <svg class="w-10 h-10 text-blue-400 mx-auto mb-8" fill="currentColor" viewBox="0 0 32 32">
        <path d="M9.352 4C4.456 7.456 1 13.12 1 19.36c0 5.088 3.072 8.064 6.624 8.064 3.36 0 5.856-2.688 5.856-5.856 0-3.168-2.208-5.472-5.088-5.472-.576 0-1.344.096-1.536.192.48-3.264 3.552-7.104 6.624-9.024L9.352 4zm16.512 0c-4.8 3.456-8.256 9.12-8.256 15.36 0 5.088 3.072 8.064 6.624 8.064 3.264 0 5.856-2.688 5.856-5.856 0-3.168-2.304-5.472-5.184-5.472-.576 0-1.248.096-1.44.192.48-3.264 3.456-7.104 6.528-9.024L25.864 4z"/>
      </svg>
      
      <blockquote class="text-3xl md:text-4xl font-light text-gray-800 italic leading-relaxed mb-6">
        "{{ $quote }}"
      </blockquote>
      
      <div class="w-16 h-1 bg-gradient-to-r from-blue-500 to-purple-500 mx-auto rounded-full"></div>
    </div>
  </div>
</div>
@endif