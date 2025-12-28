@props(['discountCode', 'discountPercentage' => null, 'discountAmount' => null, 'discountType' => 'percentage', 'affiliateLink', 'productName' => null])

{{-- Sticky Discount CTA - For custom affiliate products (Moovv etc.) --}}
<div class="fixed bottom-6 left-1/2 transform -translate-x-1/2 z-50 max-w-md w-full px-4">
    <div class="bg-green-600 shadow-2xl rounded-2xl px-6 py-4 text-white border-2 border-green-700">
        <div class="flex items-center justify-between gap-4">
            <div class="flex-1">
                <div class="flex items-center gap-2 mb-1">
                    <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                    </svg>
                    <p class="text-sm font-bold text-white">
                        {{ $discountCode }} =
                        @if($discountType === 'euro' && $discountAmount)
                            €{{ $discountAmount }} korting!
                        @else
                            {{ $discountPercentage ?? 10 }}% korting!
                        @endif
                    </p>
                </div>
                <p class="text-xs text-white">Exclusief voor lezers van {{ getSetting('site_name') }}</p>
            </div>
            <a href="{{ $affiliateLink }}"
               target="_blank"
               rel="nofollow sponsored"
               class="bg-white hover:bg-green-50 text-green-700 font-bold px-5 py-2.5 rounded-xl text-sm transition-colors duration-200 whitespace-nowrap shadow-lg">
                Bekijk nu
            </a>
        </div>
    </div>
</div>
