@php
    $enabled = getSetting('popup_enabled', '0') === '1';
    $discountCode = getSetting('popup_discount_code', '');
    $discountPercentage = getSetting('popup_discount_percentage', '20');
    $discountAmount = getSetting('popup_discount_amount', '');
    $affiliateLink = getSetting('popup_affiliate_link', '#');
    $brandName = getSetting('popup_brand_name', 'Moovv');
    $reviewSlug = getSetting('popup_review_slug', '');
@endphp

@if($enabled && $discountCode)
<div id="discount-top-banner" class="w-full h-11 fixed top-0 left-0 right-0 z-[60]" style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);">
    <style>
        /* Push navigation down when banner is shown - header blijft op z-50 */
        body:has(#discount-top-banner) header {
            top: 44px !important;
        }
        /* Adjust main content padding - main heeft padding-top: 6rem (96px) standaard */
        body:has(#discount-top-banner) main {
            padding-top: calc(6rem + 44px) !important;
        }
    </style>
    <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 h-full">
        <div class="flex items-center justify-center gap-2 sm:gap-3 text-white h-full">
            <span class="font-bold text-xs sm:text-sm">
                @if($discountAmount)
                    €{{ $discountAmount }} korting op {{ $brandName }} loopbanden met code
                @else
                    {{ $discountPercentage }}% korting op {{ $brandName }} loopbanden met code
                @endif
            </span>
            <button onclick="copyBannerCode('{{ $discountCode }}')"
                    id="banner-code-btn"
                    class="bg-white/20 hover:bg-white/30 px-2.5 sm:px-3 py-1 sm:py-1.5 rounded font-mono font-bold text-xs tracking-wide transition-all cursor-pointer whitespace-nowrap">
                {{ $discountCode }}
            </button>
            <a href="{{ $affiliateLink }}"
               target="_blank"
               rel="nofollow sponsored"
               class="bg-white text-green-600 hover:bg-green-50 px-3 sm:px-4 py-1 sm:py-1.5 rounded-md font-semibold transition-all text-xs whitespace-nowrap">
                BEKIJK MOOVV
            </a>
            @if($reviewSlug)
            <a href="{{ route('testberichte.show', $reviewSlug) }}"
               class="text-white hover:text-green-100 underline text-xs font-semibold transition-colors whitespace-nowrap">
                Lees review
            </a>
            @endif
        </div>
    </div>
</div>

<script>
function copyBannerCode(code) {
    navigator.clipboard.writeText(code).then(() => {
        const btn = document.getElementById('banner-code-btn');
        if (btn) {
            const original = btn.innerHTML;
            btn.innerHTML = 'GEKOPIEERD!';
            btn.classList.add('bg-white/40');
            setTimeout(() => {
                btn.innerHTML = original;
                btn.classList.remove('bg-white/40');
            }, 2000);
        }
    });
}
</script>
@endif
