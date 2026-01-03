@php
    use Carbon\Carbon;

    // Allow overrides from parent - handle both passed variables and fallbacks
    $flag  = isset($bfActive) ? $bfActive : null;
    $until = isset($bfUntil) ? $bfUntil : null;

    // If no until date passed, get from config
    if (!$until && $flag) {
        $until = config('blackfriday.until');
    }

    // Preview toggles via URL (config-gestStunded): ?<qKey>=on / off
    $qKey    = config('blackfriday.preview.query_key', 'bf');
    $preview = strtolower((string) request($qKey));
    $truthy  = config('blackfriday.preview.truthy', ['1','on','true']);
    $falsy   = config('blackfriday.preview.falsy',  ['0','off','false']);

    $forceOn  = in_array($preview, $truthy, true);
    $forceOff = in_array($preview, $falsy,  true);

    if (is_null($flag)) {
        $bfFlag  = (bool) config('blackfriday.active');
        $bfStart = config('blackfriday.start');   // bv. '2026-11-23'
        $bfEnd   = config('blackfriday.until');   // bv. '2026-12-01'

        $today    = Carbon::today('Europe/Berlin');
        $inWindow = ($bfStart && $bfEnd)
            ? $today->between(
                Carbon::parse($bfStart, 'Europe/Berlin')->startOfDay(),
                Carbon::parse($bfEnd,   'Europe/Berlin')->endOfDay()
              )
            : false;

        $flag  = $forceOff ? false : ($forceOn || $bfFlag || $inWindow);
        $until = $bfEnd ?: $until;
    }

    // Countdown-eind (config-based; DE 23:59:59). Geen random fallback.
    $endIso = null;
    if ($flag && $until) {
        $endIso = Carbon::parse($until, 'Europe/Berlin')->endOfDay()->format('Y-m-d\TH:i:sP');
    }
@endphp

@verbatim
<style>
  /* Top Banner Bar - Clean Style */
  #bf-top-banner {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 100;
    background: #000000;
    border-bottom: 1px solid rgba(255, 215, 0, 0.15);
    transform: translateY(-100%);
    transition: transform 0.5s cubic-bezier(0.28, 0.11, 0.32, 1);
  }

  #bf-top-banner.show {
    transform: translateY(0);
  }

  #bf-top-banner .banner-inner {
    max-width: 1440px;
    margin: 0 auto;
    padding: 0.875rem 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
  }

  #bf-top-banner .banner-content {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  #bf-top-banner .banner-title {
    font-size: 0.9375rem;
    font-weight: 400;
    color: rgba(255, 255, 255, 0.85);
    letter-spacing: -0.01em;
    line-height: 1.4;
  }

  #bf-top-banner .banner-cta {
    color: #FFD700;
    font-size: 0.9375rem;
    font-weight: 600;
    text-decoration: none;
    letter-spacing: -0.01em;
    transition: opacity 0.2s ease;
    white-space: nowrap;
  }

  #bf-top-banner .banner-cta:hover {
    opacity: 0.7;
  }

  /* Adjust body padding when banner is shown */
  body.bf-banner-active {
    padding-top: 50px;
  }

  body.bf-banner-active header {
    top: 50px;
  }

  /* Mobile responsive */
  @media (max-width: 768px) {
    #bf-top-banner .banner-inner {
      padding: 0.625rem 1rem;
      gap: 0.75rem;
      flex-wrap: wrap;
      justify-content: center;
      text-align: center;
    }

    #bf-top-banner .banner-title {
      font-size: 0.8125rem;
    }

    #bf-top-banner .banner-cta {
      font-size: 0.8125rem;
    }

    body.bf-banner-active {
      padding-top: 40px;
    }

    body.bf-banner-active header {
      top: 40px;
    }
  }

  /* Suppress on specific routes */
  body.bf-hide-all #bf-top-banner {
    display: none !important;
  }
</style>
@endverbatim

<script>
(function(){
  if (window.__bfBannerOnce) return; window.__bfBannerOnce = true;

  var BF_Q = @js(config('blackfriday.preview.query_key') ?? 'bf');
  var BF_T = @js(config('blackfriday.preview.truthy') ?? ['1','on','true']);
  var BF_F = @js(config('blackfriday.preview.falsy') ?? ['0','off','false']);

  var STORAGE_KEY = 'bf_banner_closed';
  var STORAGE_KEY_SHOWN = 'bf_banner_shown';
  var ROUTE_SUPPRESS_ALL = ['/blackfriday'];

  var BF_END_ISO = @js($endIso);
  var BF_ACTIVE = !!BF_END_ISO && @json((bool)$flag);

  // Utility Functions
  function pad(n){ return String(n).padStart(2,'0'); }
  function formatDiff(ms){
    var d  = Math.floor(ms / 86400000);
    var hh = Math.floor((ms % 86400000) / 3600000);
    var mm = Math.floor((ms % 3600000) / 60000);
    var ss = Math.floor((ms % 60000) / 1000);
    return (d>0 ? d+'d ' : '') + pad(hh) + ':' + pad(mm) + ':' + pad(ss);
  }

  // Top Banner
  var bannerEl, bannerTimeEl, bannerTick, bannerEnd;

  function ensureBanner(){
    if (!BF_ACTIVE) return;
    var path = (location.pathname.replace(/\/+$/,'') || '/');
    if (ROUTE_SUPPRESS_ALL.indexOf(path) !== -1) return;

    bannerEl = document.getElementById('bf-top-banner');
    if (!bannerEl){
      var until = BF_END_ISO ? new Date(BF_END_ISO).toLocaleDateString('de-DE', {day: 'numeric', month: 'long', year: 'numeric'}) : '';
      var parsed = Date.parse(BF_END_ISO || '');
      var initialTime = (!isNaN(parsed)) ? formatDiff(Math.max(0, parsed - Date.now())) : '00:00:00';

      var siteName = @js(getSetting('site_name', 'ons'));

      bannerEl = document.createElement('div');
      bannerEl.id = 'bf-top-banner';
      bannerEl.innerHTML =
        '<div class="banner-inner">' +
          '<div class="banner-content">' +
            '<span class="banner-title">Black Friday bij ' + siteName + '!</span>' +
          '</div>' +
          '<a href="/blackfriday" class="banner-cta">Alle ansehen deals →</a>' +
        '</div>';
      document.body.insertBefore(bannerEl, document.body.firstChild);
    }

    bannerTimeEl = bannerEl.querySelector('.countdown-time');

    if (!bannerEnd){
      var parsed = Date.parse(BF_END_ISO || '');
      if (!isNaN(parsed)) bannerEnd = parsed;
    }

    if (!bannerTick && bannerEnd){
      bannerTick = setInterval(function(){
        if (bannerTimeEl) bannerTimeEl.textContent = formatDiff(Math.max(0, bannerEnd - Date.now()));
      }, 1000);
    }
  }

  function showBanner(){
    if (!bannerEl) return;
    bannerEl.classList.add('show');
    document.body.classList.add('bf-banner-active');
  }

  function shouldAutoOpen(){
    if (!BF_ACTIVE) return false;
    var path = (location.pathname.replace(/\/+$/,'') || '/');
    if (ROUTE_SUPPRESS_ALL.indexOf(path) !== -1) return false;

    var params = new URLSearchParams(location.search);
    var pv = (params.get(BF_Q) || '').toLowerCase();
    var forceOff = BF_F.includes(pv);
    if (forceOff) return false;

    return true;
  }

  // Initialization
  function init(){
    var path = (location.pathname.replace(/\/+$/,'') || '/');
    if (ROUTE_SUPPRESS_ALL.indexOf(path) !== -1 || !BF_ACTIVE){
      document.body.classList.add('bf-hide-all');
      return;
    }

    ensureBanner();

    if (shouldAutoOpen()){
      // Check if banner was already shown in this session
      var wasShown = sessionStorage.getItem(STORAGE_KEY_SHOWN) === 'true';

      if (wasShown) {
        // Skip animation, show immediately
        showBanner();
      } else {
        // First time: animate in and mark as shown
        setTimeout(function(){
          showBanner();
          sessionStorage.setItem(STORAGE_KEY_SHOWN, 'true');
        }, 500);
      }
    }
  }

  (document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', init)
    : init());
})();
</script>
