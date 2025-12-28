@php
    $enabled = getSetting('popup_enabled', '0') === '1';
    $title = getSetting('popup_title', 'Exclusief: 20% korting!');
    $description = getSetting('popup_description', 'Gebruik onze exclusieve kortingscode.');
    $discountCode = getSetting('popup_discount_code', '');
    $discountPercentage = getSetting('popup_discount_percentage', '20');
    $discountAmount = getSetting('popup_discount_amount', '');
    $affiliateLink = getSetting('popup_affiliate_link', '#');
    $reviewSlug = getSetting('popup_review_slug', '');
    $delaySeconds = getSetting('popup_delay_seconds', '5');
    $brandName = getSetting('popup_brand_name', 'Moovv');
@endphp

@if($enabled && $discountCode)
<div id="discount-popup" class="fixed bottom-4 right-4 sm:bottom-6 sm:right-6 z-50 w-72 sm:w-80 transform transition-all duration-300" style="display: none; transform: translateY(150%);">
    <div class="bg-white rounded-lg shadow-2xl overflow-hidden border border-gray-200">

        {{-- Header --}}
        <div class="px-3 py-2 sm:px-4 sm:py-3 flex items-center justify-between" style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);">
            <div class="flex-1">
                <div class="text-white text-sm sm:text-lg font-bold">
                    @if($discountAmount)
                        €{{ $discountAmount }} korting op {{ $brandName }}
                    @else
                        {{ $discountPercentage }}% korting op {{ $brandName }}
                    @endif
                </div>
                <div class="text-green-100 text-xs hidden sm:block">{{ $title }}</div>
            </div>
            <button onclick="closeDiscountPopup()" class="text-white hover:text-green-100 transition-colors ml-2" style="font-size: 24px; line-height: 1; font-weight: 300;">&times;</button>
        </div>

        {{-- Content --}}
        <div class="p-3 sm:p-4">

            {{-- Discount Code Box --}}
            <div class="bg-green-50 rounded-lg p-3 sm:p-4 mb-3 sm:mb-4 border-2 border-green-200">
                <div class="text-center mb-2">
                    <div class="text-xs text-gray-600 font-semibold mb-2">GEBRUIK KORTINGSCODE</div>
                    <div class="bg-white rounded-md px-3 py-2 sm:px-4 sm:py-3 border border-green-300 relative">
                        <code class="text-base sm:text-lg font-bold text-green-700 tracking-wider select-all">{{ $discountCode }}</code>
                    </div>
                </div>
                <button onclick="copyDiscountCode('{{ $discountCode }}')"
                        id="copy-btn"
                        class="w-full mt-2 bg-green-600 hover:bg-green-700 text-white text-xs font-bold py-2 px-3 rounded transition-colors">
                    KOPIEER CODE
                </button>
            </div>

            {{-- Description - Hidden on mobile --}}
            <p class="text-gray-600 text-xs leading-relaxed mb-3 sm:mb-4 text-center hidden sm:block">{{ $description }}</p>

            {{-- CTA Button --}}
            <a href="{{ $affiliateLink }}"
               target="_blank"
               rel="nofollow sponsored"
               class="block w-full text-white font-bold py-2.5 sm:py-3 px-3 sm:px-4 rounded-lg text-center transition-all text-xs sm:text-sm mb-2 hover:shadow-lg"
               style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);">
                BEKIJK {{ strtoupper($brandName) }} LOOPBANDEN
            </a>

            @if($reviewSlug)
            <a href="{{ route('reviews.show', $reviewSlug) }}"
               class="block w-full text-center text-gray-600 hover:text-gray-800 text-xs font-semibold py-1.5 sm:py-2 transition-colors">
                Of lees eerst onze {{ $brandName }} review &rarr;
            </a>
            @endif
        </div>
    </div>
</div>

{{-- JavaScript for Popup Functionality --}}
<script>
(function() {
    const POPUP_KEY = 'discountPopupShown';
    const DELAY_SECONDS = {{ $delaySeconds }};
    const reviewSlug = '{{ $reviewSlug }}';

    let popupShown = false;

    function shouldShowPopup() {
        // Don't show if already shown this session
        if (sessionStorage.getItem(POPUP_KEY) === 'true') {
            return false;
        }

        // Don't show on the review page itself
        if (reviewSlug && window.location.pathname.includes(reviewSlug)) {
            return false;
        }

        return true;
    }

    function showDiscountPopup() {
        if (popupShown) return;

        const popup = document.getElementById('discount-popup');
        if (popup) {
            popup.style.display = 'block';
            setTimeout(() => {
                popup.style.transform = 'translateY(0)';
            }, 10);
            popupShown = true;

            // Mark as shown for this session only
            sessionStorage.setItem(POPUP_KEY, 'true');
        }
    }

    function closeDiscountPopup() {
        const popup = document.getElementById('discount-popup');
        if (popup) {
            popup.style.transform = 'translateY(150%)';
            setTimeout(() => {
                popup.style.display = 'none';
            }, 300);
        }
    }

    function copyDiscountCode(code) {
        navigator.clipboard.writeText(code).then(() => {
            const btn = document.getElementById('copy-btn');
            const original = btn.innerHTML;
            btn.innerHTML = 'GEKOPIEERD!';
            btn.style.background = '#16a34a';
            setTimeout(() => {
                btn.innerHTML = original;
                btn.style.background = '';
            }, 2000);
        });
    }

    // Show popup with delay if conditions are met
    if (shouldShowPopup()) {
        setTimeout(() => {
            showDiscountPopup();
        }, DELAY_SECONDS * 1000);
    }

    // Exit intent trigger (only on desktop, not on review page)
    if (window.innerWidth >= 768 && shouldShowPopup()) {
        document.addEventListener('mouseout', function(e) {
            if (!popupShown && e.clientY < 10) {
                showDiscountPopup();
            }
        });
    }

    // Make functions globally available
    window.closeDiscountPopup = closeDiscountPopup;
    window.copyDiscountCode = copyDiscountCode;
})();
</script>
@endif
