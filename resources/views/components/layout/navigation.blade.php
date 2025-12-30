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
                <a href="/produkte" class="nav-link {{ Request::is('produkte*') ? 'active' : '' }}">Produkte</a>

                @php
                    $informationPages = Cache::remember('nav_info_pages', 3600, function () {
                        return \App\Models\InformationPage::active()->ordered()->get();
                    });
                @endphp
                @if($informationPages->count() > 0)
                    <div class="relative group">
                        <button class="nav-link flex items-center gap-1 {{ Request::is('information*') ? 'active' : '' }}">
                            Information
                            <svg class="w-4 h-4 transition-transform group-hover:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div class="absolute left-0 top-full mt-2 w-56 bg-white rounded-xl shadow-lg border border-gray-100 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 overflow-hidden">
                            @foreach($informationPages as $page)
                                <a href="{{ route('information.show', $page->slug) }}"
                                   class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                    {{ $page->menu_title ?? $page->title }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <a href="/top-5" class="nav-link {{ Request::is('top-5') ? 'active' : '' }}">Top 5</a>
                <a href="/beste-marken" class="nav-link {{ Request::is('beste-marken') ? 'active' : '' }}">Beste Marken</a>
                <a href="/ratgeber" class="nav-link {{ Request::is('ratgeber*') ? 'active' : '' }}">Ratgeber</a>
                <a href="/testberichte" class="nav-link {{ Request::is('testberichte*') ? 'active' : '' }}">Testberichte</a>
            </nav>

            <!-- Search + Mobile Menu -->
            <div class="flex items-center gap-3">
                <!-- Search with Autocomplete -->
                <div class="hidden lg:block relative" x-data="searchAutocomplete()">
                    <form action="{{ route('produkte.index') }}" method="GET" @submit.prevent="handleSubmit">
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input type="text"
                                   name="search"
                                   placeholder="Suchen..."
                                   value="{{ request('search') }}"
                                   x-model="query"
                                   @input.debounce.300ms="fetchResults"
                                   @focus="onFocus"
                                   @blur="hideResults"
                                   @keydown.escape="showResults = false"
                                   @keydown.down.prevent="selectNext"
                                   @keydown.up.prevent="selectPrev"
                                   @keydown.enter.prevent="selectCurrent"
                                   class="pl-9 pr-4 py-2 w-64 bg-gray-50 border rounded-lg text-sm placeholder-gray-500 focus:outline-none focus:bg-white transition-all">

                            <!-- Loading Spinner -->
                            <div x-show="loading" class="absolute right-3 top-1/2 -translate-y-1/2">
                                <svg class="animate-spin h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </div>
                    </form>

                    <!-- Autocomplete Dropdown -->
                    <div x-show="showResults"
                         x-transition
                         class="absolute top-full mt-2 w-80 bg-white rounded-lg shadow-xl border border-gray-200 overflow-hidden z-50">

                        <!-- Results -->
                        <template x-if="results.length > 0">
                            <div>
                                <template x-for="(product, index) in results" :key="product.id">
                                    <a :href="product.url"
                                       @mousedown.prevent="window.location.href = product.url"
                                       @mouseenter="selectedIndex = index"
                                       :class="selectedIndex === index ? 'bg-gray-100' : 'bg-white'"
                                       class="flex items-center gap-2.5 px-3 py-2.5 hover:bg-gray-100 transition-colors border-b border-gray-100 last:border-0">
                                        <!-- Product Image -->
                                        <img :src="product.image_url"
                                             :alt="product.title"
                                             class="w-12 h-12 object-contain flex-shrink-0 bg-gray-50 rounded">

                                        <!-- Product Info -->
                                        <div class="flex-1 min-w-0">
                                            <div class="font-medium text-xs text-gray-900 truncate leading-tight" x-text="product.title"></div>
                                            <div class="flex items-center gap-1.5 mt-1">
                                                <template x-if="product.rating">
                                                    <div class="flex items-center text-xs text-gray-500">
                                                        <svg class="w-3 h-3 text-yellow-400 fill-current" viewBox="0 0 20 20">
                                                            <path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/>
                                                        </svg>
                                                        <span class="ml-0.5" x-text="product.rating.toFixed(1)"></span>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>

                                        <!-- Price -->
                                        <template x-if="product.price">
                                            <div class="text-xs font-bold whitespace-nowrap" style="color: {{ $primaryColor }};" x-text="'€' + product.price.toFixed(2).replace('.', ',')"></div>
                                        </template>
                                    </a>
                                </template>
                            </div>
                        </template>

                        <!-- No Results -->
                        <template x-if="results.length === 0 && query.length >= 2 && !loading">
                            <div class="px-4 py-3 text-sm text-gray-500 text-center">
                                Keine Produkte gefunden für "<span x-text="query"></span>"
                            </div>
                        </template>
                    </div>
                </div>

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

function searchAutocomplete() {
    return {
        query: '{{ request('search') }}',
        results: [],
        showResults: false,
        loading: false,
        selectedIndex: -1,

        async fetchResults() {
            if (this.query.length < 2) {
                this.results = [];
                this.showResults = false;
                return;
            }

            this.loading = true;
            this.selectedIndex = -1;

            try {
                const response = await fetch(`/api/search?q=${encodeURIComponent(this.query)}`);
                this.results = await response.json();
                this.showResults = true;
            } catch (error) {
                console.error('Search error:', error);
                this.results = [];
            } finally {
                this.loading = false;
            }
        },

        onFocus() {
            if (this.query.length >= 2 && this.results.length > 0) {
                this.showResults = true;
            }
        },

        hideResults() {
            // Delay to allow click on result
            setTimeout(() => {
                this.showResults = false;
                this.selectedIndex = -1;
            }, 200);
        },

        selectNext() {
            if (this.results.length === 0) return;
            this.selectedIndex = (this.selectedIndex + 1) % this.results.length;
        },

        selectPrev() {
            if (this.results.length === 0) return;
            this.selectedIndex = this.selectedIndex <= 0 ? this.results.length - 1 : this.selectedIndex - 1;
        },

        selectCurrent() {
            if (this.selectedIndex >= 0 && this.results[this.selectedIndex]) {
                window.location.href = this.results[this.selectedIndex].url;
            } else if (this.results.length > 0) {
                // No selection, go to first result
                window.location.href = this.results[0].url;
            } else {
                // No results, submit form to search page
                this.$el.querySelector('form').submit();
            }
        },

        handleSubmit() {
            this.selectCurrent();
        }
    }
}
</script>
