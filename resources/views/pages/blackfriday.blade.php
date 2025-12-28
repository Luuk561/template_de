@extends('layouts.app')

@php
    \Carbon\Carbon::setLocale('nl');
    $siteName = getSetting('site_name', config('app.name'));
    $year     = \Carbon\Carbon::now('Europe/Amsterdam')->format('Y');
    $title    = "Black Friday {$year} — Deals & Korting | {$siteName}";
    $desc     = "Ontdek alle Black Friday aanbiedingen van {$siteName}. Vergelijk deals en bespaar. " . ($bfUntil ? 'Actie t/m '.\Carbon\Carbon::parse($bfUntil,'Europe/Amsterdam')->translatedFormat('d F Y').'.' : '');
@endphp

@section('title', $title)
@section('meta_description', $desc)

@push('head')
<style>
/* Black Friday Modern Design */
body.bf-page {
    background: linear-gradient(135deg, #000000 0%, #1a1a1a 50%, #0f0f0f 100%);
    color: #ffffff;
    min-height: 100vh;
    padding-bottom: 80px;
}

.bf-hero {
    background: linear-gradient(135deg, #000000 0%, #1a1a1a 100%);
    position: relative;
    overflow: hidden;
}

.bf-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent, #FFD700, transparent);
}

.bf-title {
    color: #FFD700;
    text-shadow: 0 0 20px rgba(255, 215, 0, 0.3);
    font-weight: 900;
}

.bf-countdown {
    background: rgba(0, 0, 0, 0.8);
    border: 2px solid #FFD700;
    backdrop-filter: blur(10px);
    box-shadow: 0 8px 32px rgba(255, 215, 0, 0.2);
}

.bf-product-grid {
    background: white;
    padding: 4rem 0;
}

.bf-product-card {
    background: #ffffff;
    border: none;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    height: 100%;
}

.bf-product-card.top-deal {
    box-shadow: 0 4px 12px rgba(255, 215, 0, 0.15);
}

.bf-product-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12);
}

.bf-image-container {
    background: white;
    padding: 1rem;
    margin-bottom: 1rem;
}

.bf-badge {
    background: linear-gradient(135deg, #FFD700, #FFA500);
    color: #000000;
    font-weight: 800;
    text-shadow: none;
}

.bf-discount-badge {
    background: linear-gradient(135deg, #dc3545, #c82333);
    color: #ffffff;
}

.bf-price {
    color: #FFD700;
    font-weight: 900;
}

.bf-original-price {
    color: #6c757d;
}

.bf-savings-badge {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: #ffffff;
    font-weight: 700;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    display: inline-block;
}

.bf-btn-primary {
    background: linear-gradient(135deg, #FFD700, #FFA500);
    color: #000000;
    font-weight: 700;
    border: none;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(255, 215, 0, 0.2);
}

.bf-btn-primary:hover {
    background: linear-gradient(135deg, #FFA500, #FFD700);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255, 215, 0, 0.3);
    color: #000000;
}

.bf-btn-secondary {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    color: #495057;
    transition: all 0.3s ease;
}

.bf-btn-secondary:hover {
    background: #f9fafb;
    border-color: #FFD700;
    color: #000000;
    transform: translateY(-2px);
}

.bf-product-title {
    color: #212529;
    font-weight: 600;
}

.bf-product-title:hover {
    color: #B8860B;
}

.bf-section-header {
    color: #000000;
    text-align: center;
    margin-bottom: 3rem;
}

.bf-section-subtitle {
    color: #6c757d;
    font-size: 1.1rem;
    max-width: 600px;
    margin: 0 auto;
}

.bf-no-deals {
    background: #ffffff;
    border-radius: 16px;
    padding: 3rem;
    text-align: center;
    color: #6c757d;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.bf-pagination {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    padding: 1.5rem;
}

.bf-sticky-mobile-cta {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(to top, rgba(0, 0, 0, 0.95) 0%, rgba(0, 0, 0, 0.9) 100%);
    backdrop-filter: blur(20px);
    border-top: 1px solid #FFD700;
    padding: 0.75rem 1rem;
    z-index: 9999;
    box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.2);
    transform: translateY(0);
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s ease, transform 0.3s ease;
}

.bf-sticky-mobile-cta.visible {
    opacity: 1;
    pointer-events: auto;
}

@media (max-width: 768px) {
    .bf-title {
        font-size: 2.5rem;
    }

    .bf-product-card {
        margin-bottom: 1.5rem;
    }

    .bf-hero {
        padding-top: 6rem;
        padding-bottom: 3rem;
    }
}

@media (min-width: 1024px) {
    .bf-sticky-mobile-cta {
        display: none;
    }
}
</style>
@endpush

@section('content')
<div class="bf-page">
    <!-- Hero Section - Compact -->
    <section class="bf-hero pt-20 md:pt-24 pb-6 md:pb-8 px-4 relative">
        <div class="max-w-6xl mx-auto text-center">
            <!-- Main Title -->
            <h1 class="bf-title text-3xl md:text-4xl font-black leading-tight mb-3">
                Black Friday {{ $year }}
            </h1>

            <!-- Countdown Timer - Compact -->
            @if($bfEndIso)
            <div class="inline-flex items-center gap-3 px-4 py-2 rounded-xl bg-black/50 border border-yellow-400/50 mb-4">
                <div class="w-1.5 h-1.5 bg-yellow-400 rounded-full animate-pulse"></div>
                <span class="text-yellow-400 text-xs font-semibold">Eindigt over</span>
                <div id="bf-page-countdown"
                     data-end="{{ $bfEndIso }}"
                     class="font-mono text-sm font-bold text-white tabular-nums">
                    —
                </div>
            </div>
            @else
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-black/50 border border-yellow-400/50 mb-4">
                <div class="w-1.5 h-1.5 bg-yellow-400 rounded-full animate-pulse"></div>
                <span class="text-yellow-400 text-xs font-semibold tracking-wider uppercase">Nu live</span>
            </div>
            @endif

            <!-- Subtitle - Compact -->
            <p class="text-sm text-gray-400 max-w-xl mx-auto">
                De beste deals, scherp geprijsd
            </p>
        </div>
    </section>

    <!-- Products Section -->
    <section id="deals" class="bf-product-grid">
        <div class="max-w-7xl mx-auto px-4">
            <!-- Section Header -->
            <div class="bf-section-header">
                <h2 class="text-3xl md:text-5xl font-black mb-4">
                    Top <span style="color: #FFD700;">Deals</span>
                </h2>
                <p class="bf-section-subtitle">
                    Kortingen die écht het verschil maken. Vergelijk prijzen, ontdek premium producten en betaal nooit te veel.
                </p>
            </div>

            @if($producten->count())
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
                    @foreach($producten as $product)
                        @php
                            $bolUrl = $product->url ?? null;
                            $affiliateLink = $bolUrl
                                ? getBolAffiliateLink($bolUrl, $product->title)
                                : null;
                            $hasDiscount = $product->strikethrough_price && $product->price < $product->strikethrough_price;
                            $discountPct = $hasDiscount
                                ? round(100 - ($product->price / $product->strikethrough_price * 100))
                                : null;
                            $savings = $hasDiscount ? ($product->strikethrough_price - $product->price) : 0;
                        @endphp

                        <div class="bf-product-card rounded-2xl p-6 flex flex-col h-full @if($loop->index < 3) top-deal @endif">
                            <!-- Badges -->
                            @if($hasDiscount)
                                <div class="flex justify-between items-start mb-4">
                                    <div class="flex flex-col gap-2">
                                        <span class="bf-badge px-3 py-1 rounded-full text-xs font-bold">
                                            BLACK FRIDAY
                                        </span>
                                        @if($loop->index < 3)
                                            <span class="px-2 py-1 rounded-full text-xs font-bold bg-green-600 text-white">
                                                Top Deal
                                            </span>
                                        @endif
                                    </div>
                                    @if($discountPct > 0)
                                        <span class="bf-discount-badge px-2 py-1 rounded-full text-xs font-bold">
                                            -{{ $discountPct }}%
                                        </span>
                                    @endif
                                </div>
                            @endif

                            <!-- Product Image -->
                            <div class="bf-image-container">
                                <a href="{{ route('producten.show', $product->slug) }}" 
                                   class="block w-full h-40 flex items-center justify-center">
                                    <img src="{{ $product->image_url ?? 'https://via.placeholder.com/300x300?text=Geen+Afbeelding' }}"
                                         alt="{{ $product->title }}" 
                                         class="max-h-full max-w-full object-contain" 
                                         loading="lazy">
                                </a>
                            </div>

                            <!-- Product Title -->
                            <a href="{{ route('producten.show', $product->slug) }}"
                               class="bf-product-title text-sm font-semibold line-clamp-2 mb-3 hover:underline transition-colors">
                                {{ $product->title }}
                            </a>

                            <!-- Rating Stars -->
                            @if($product->rating_average)
                                <div class="flex items-center gap-2 mb-4">
                                    <div class="flex items-center">
                                        @for($i = 1; $i <= 5; $i++)
                                            @if($i <= $product->rating_average)
                                                <svg class="w-4 h-4 text-yellow-400 fill-current" viewBox="0 0 20 20">
                                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                                </svg>
                                            @elseif($i - 0.5 <= $product->rating_average)
                                                <svg class="w-4 h-4 text-yellow-400" viewBox="0 0 20 20">
                                                    <defs>
                                                        <linearGradient id="half-star-{{ $product->id }}-{{ $i }}">
                                                            <stop offset="50%" stop-color="currentColor"/>
                                                            <stop offset="50%" stop-color="transparent"/>
                                                        </linearGradient>
                                                    </defs>
                                                    <path fill="url(#half-star-{{ $product->id }}-{{ $i }})" d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                                    <path fill="none" stroke="#d1d5db" stroke-width="1" d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                                </svg>
                                            @else
                                                <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 20 20">
                                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                                </svg>
                                            @endif
                                        @endfor
                                    </div>
                                    <span class="text-xs text-gray-600 font-medium">{{ number_format($product->rating_average, 1) }}</span>
                                    @if($product->rating_count)
                                        <span class="text-xs text-gray-500">({{ $product->rating_count }})</span>
                                    @endif
                                </div>
                            @endif

                            <!-- Pricing -->
                            <div class="mb-6">
                                @if($hasDiscount && $savings > 0)
                                    <div class="bf-savings-badge text-sm mb-3">
                                        Bespaar €{{ number_format($savings, 2, ',', '.') }}
                                    </div>
                                @endif
                                <div class="flex items-baseline gap-2 mb-1">
                                    <div class="bf-price text-2xl md:text-3xl font-black">
                                        €{{ number_format($product->price ?? 0, 2, ',', '.') }}
                                    </div>
                                    @if($product->strikethrough_price)
                                        <div class="bf-original-price text-base line-through">
                                            €{{ number_format($product->strikethrough_price, 2, ',', '.') }}
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="mt-auto space-y-3">
                                @if($affiliateLink)
                                    <a href="{{ $affiliateLink }}"
                                       target="_blank"
                                       rel="nofollow sponsored noopener"
                                       class="bf-btn-primary w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-sm font-bold transition-all">
                                        <span>Bekijk Deal</span>
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m0-7H3"/>
                                        </svg>
                                    </a>
                                @else
                                    <div class="w-full inline-flex items-center justify-center px-4 py-3 rounded-xl text-sm font-semibold text-gray-400 bg-gray-100 cursor-not-allowed">
                                        Link niet beschikbaar
                                    </div>
                                @endif

                                <a href="{{ route('producten.show', $product->slug) }}"
                                   class="bf-btn-secondary w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-sm font-semibold transition-all">
                                    <span>Meer Details</span>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="mt-16 flex justify-center">
                    <div class="bf-pagination">
                        {{ $producten->withQueryString()->links() }}
                    </div>
                </div>
            @else
                <div class="bf-no-deals">
                    <div class="text-6xl mb-6">🔍</div>
                    <h3 class="text-2xl font-bold mb-4">Geen deals gevonden</h3>
                    <p class="text-lg">Op dit moment zijn er nog geen Black Friday deals actief. Kom snel terug – onze aanbiedingen worden dagelijks aangevuld.</p>
                </div>
            @endif
        </div>
    </section>

    <!-- Bottom CTA Section -->
    <section class="py-16 px-4 bg-black">
        <div class="max-w-4xl mx-auto text-center">
            <h3 class="text-3xl md:text-4xl font-black text-white mb-6">
                Mis geen enkele <span class="text-yellow-400">deal</span>
            </h3>
            <p class="text-xl text-gray-400 mb-8 max-w-2xl mx-auto">
                Ontdek nu de laagste prijzen van het jaar. Met onze slimme filters vind je razendsnel het product dat écht bij jou past. Wacht niet te lang, want de beste deals verdwijnen als eerste.
            </p>
            <a href="{{ route('producten.index') }}" 
               class="bf-btn-primary inline-flex items-center gap-3 px-8 py-4 rounded-xl text-lg font-bold transition-all duration-300">
                <span>Bekijk Alle Producten</span>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                </svg>
            </a>
        </div>
    </section>

    <!-- Sticky Mobile CTA -->
    <div class="bf-sticky-mobile-cta" id="stickyMobileCta">
        <div class="max-w-7xl mx-auto flex items-center justify-between gap-3">
            <div class="flex-1 min-w-0">
                <div class="text-yellow-400 font-bold text-xs">
                    @if($bfEndIso)
                        <span id="sticky-countdown">Eindigt binnenkort</span>
                    @else
                        Black Friday Deals
                    @endif
                </div>
                <div class="text-white text-xs opacity-80">
                    {{ $producten->total() }} deals
                </div>
            </div>
            <a href="#deals" class="bf-btn-primary px-4 py-2 rounded-lg text-xs font-bold whitespace-nowrap">
                Bekijk Deals
            </a>
        </div>
    </div>
</div>
@endsection

@push('head')
<script>
// Enhanced countdown with smooth animations
(function(){
  function pad(n){ return String(n).padStart(2,'0'); }
  function tick(){
    var el = document.getElementById('bf-page-countdown');
    if(!el) return;
    var endAttr = el.getAttribute('data-end'); 
    if(!endAttr){ el.textContent='—'; return; }
    var end = Date.parse(endAttr); 
    if(isNaN(end)){ el.textContent='—'; return; }
    var d = Math.max(0, end - Date.now());
    if (d <= 0) { 
      el.textContent = 'Einde actie'; 
      el.style.color = '#ff4444';
      return; 
    }
    var days=Math.floor(d/86400000),
        hh=Math.floor((d%86400000)/3600000),
        mm=Math.floor((d%3600000)/60000),
        ss=Math.floor((d%60000)/1000);
    
    var newText = (days>0 ? days+'d ' : '') + pad(hh)+':'+pad(mm)+':'+pad(ss);
    el.textContent = newText;
  }
  document.addEventListener('DOMContentLoaded', function(){
    tick(); 
    setInterval(tick, 1000);
  });
})();

// Smooth scroll for anchor links
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Sticky mobile CTA
    const stickyCta = document.getElementById('stickyMobileCta');
    const dealsSection = document.getElementById('deals');

    if (stickyCta && dealsSection) {
        // Function to check and update visibility
        function updateStickyVisibility() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            const dealsTop = dealsSection.offsetTop;
            const dealsBottom = dealsTop + dealsSection.offsetHeight;
            const windowHeight = window.innerHeight;

            // Show sticky CTA only when scrolling through deals (after hero)
            if (scrollTop > (dealsTop - 100) && scrollTop < (dealsBottom - windowHeight - 200)) {
                stickyCta.classList.add('visible');
            } else {
                stickyCta.classList.remove('visible');
            }
        }

        // Check on scroll
        window.addEventListener('scroll', updateStickyVisibility);

        // Initial check after short delay
        setTimeout(updateStickyVisibility, 500);

        // Update sticky countdown if present
        @if($bfEndIso)
        const stickyCountdown = document.getElementById('sticky-countdown');
        if (stickyCountdown) {
            function updateStickyCountdown() {
                const end = Date.parse('{{ $bfEndIso }}');
                const now = Date.now();
                const diff = Math.max(0, end - now);

                if (diff <= 0) {
                    stickyCountdown.textContent = 'Actie beëindigd';
                    return;
                }

                const hours = Math.floor(diff / 3600000);
                const minutes = Math.floor((diff % 3600000) / 60000);

                if (hours > 24) {
                    const days = Math.floor(hours / 24);
                    stickyCountdown.textContent = 'Nog ' + days + ' dag' + (days !== 1 ? 'en' : '');
                } else if (hours > 0) {
                    stickyCountdown.textContent = 'Nog ' + hours + 'u ' + minutes + 'm';
                } else {
                    stickyCountdown.textContent = 'Laatste ' + minutes + ' minuten!';
                }
            }

            updateStickyCountdown();
            setInterval(updateStickyCountdown, 60000); // Update every minute
        }
        @endif
    }
});
</script>
@endpush