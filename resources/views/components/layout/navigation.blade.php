@php
    $siteName = getSetting('site_name', 'Site');
    $primaryColor = getSetting('primary_color', '#3b82f6');
@endphp

<header class="fixed top-0 left-0 right-0 z-50 bg-white/80 backdrop-blur-xl border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-6 sm:px-8">
        <div class="flex items-center justify-between h-24">

            <!-- Logo -->
            <a href="/" class="text-xl font-bold hover:opacity-80 transition-opacity" style="color: {{ $primaryColor }};">
                {{ $siteName }}
            </a>

            <!-- Desktop Navigation -->
            <nav class="hidden lg:flex items-center gap-1">
                <a href="/" class="nav-link {{ Request::is('/') ? 'active' : '' }}">Home</a>
                <a href="/producten" class="nav-link {{ Request::is('producten*') ? 'active' : '' }}">Producten</a>

                @php
                    $informationPages = Cache::remember('nav_info_pages', 3600, function () {
                        return \App\Models\InformationPage::active()->ordered()->get();
                    });
                @endphp
                @if($informationPages->count() > 0)
                    <div class="relative group">
                        <button class="nav-link flex items-center gap-1 {{ Request::is('informatie*') ? 'active' : '' }}">
                            Informatie
                            <svg class="w-4 h-4 transition-transform group-hover:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div class="absolute left-0 top-full mt-2 w-56 bg-white rounded-xl shadow-lg border border-gray-100 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 overflow-hidden">
                            @foreach($informationPages as $page)
                                <a href="{{ route('informatie.show', $page->slug) }}"
                                   class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                    {{ $page->menu_title ?? $page->title }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <a href="/top-5" class="nav-link {{ Request::is('top-5') ? 'active' : '' }}">Top 5</a>
                <a href="/beste-merken" class="nav-link {{ Request::is('beste-merken') ? 'active' : '' }}">Beste Merken</a>
                <a href="/blogs" class="nav-link {{ Request::is('blogs*') ? 'active' : '' }}">Blogs</a>
                <a href="/reviews" class="nav-link {{ Request::is('reviews*') ? 'active' : '' }}">Reviews</a>
            </nav>

            <!-- Search + Mobile Menu -->
            <div class="flex items-center gap-3">
                <!-- Search -->
                <form action="{{ route('producten.index') }}" method="GET" class="hidden lg:block">
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text"
                               name="search"
                               placeholder="Zoeken..."
                               value="{{ request('search') }}"
                               class="pl-9 pr-4 py-2 w-64 bg-gray-50 border rounded-lg text-sm placeholder-gray-500 focus:outline-none focus:bg-white transition-all">
                    </div>
                </form>

                <!-- Mobile Menu Button -->
                <button class="lg:hidden w-10 h-10 flex items-center justify-center rounded-lg hover:bg-gray-100 transition-colors"
                        id="hamburgerIcon"
                        onclick="toggleMobileMenu()">
                    <svg class="w-6 h-6" style="color: {{ $primaryColor }};" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
            </div>

        </div>
    </div>
</header>

<style>
    :root {
        --primary-color: {{ $primaryColor }};
        --primary-dark: color-mix(in srgb, {{ $primaryColor }} 20%, #000 80%);
    }

    .nav-link {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 0.75rem;
        font-size: 0.9375rem;
        font-weight: 500;
        color: var(--primary-dark);
        border-radius: 0.5rem;
        transition: all 0.15s ease;
    }

    .nav-link:hover {
        background: #f9fafb;
        color: {{ $primaryColor }};
    }

    .nav-link.active {
        background: {{ $primaryColor }};
        color: white;
        font-weight: 600;
    }

    header input[type="text"] {
        border-color: var(--primary-dark) !important;
    }
</style>

<script>
function toggleMobileMenu() {
    const menu = document.getElementById('hamburgerMenu');
    if (menu) {
        menu.classList.toggle('active');
        document.body.style.overflow = menu.classList.contains('active') ? 'hidden' : '';
    }
}
</script>
