<div class="close-button xl:hidden" id="closeIcon" style="display: none;">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
        <line x1="18" y1="6" x2="6" y2="18"></line>
        <line x1="6" y1="6" x2="18" y2="18"></line>
    </svg>
</div>

<div class="hamburger-menu" id="hamburgerMenu">
    <div class="menu-search">
        <form action="{{ route('produkte.index') }}" method="GET">
            <input type="text" name="search" placeholder="Produkte suchen..." value="{{ request('search') }}">
        </form>
    </div>

    <nav class="menu-nav">
        <ul>
            <li>
                <a href="/">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    <span class="nav-text">Home</span>
                </a>
            </li>
            <li>
                <a href="/produkte">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                    <span class="nav-text">Produkte</span>
                </a>
            </li>
            @php
                $informationPages = \App\Models\InformationPage::active()->ordered()->get();
            @endphp
            @if($informationPages->count() > 0)
                <li>
                    <button onclick="toggleInfoSubmenu(this)" class="w-full flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition-colors" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.15); border-radius: 16px; color: #fff; font-size: 18px; font-weight: 600; text-decoration: none; backdrop-filter: blur(10px);">
                        <div class="flex items-center gap-4">
                            <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 24px; height: 24px; opacity: 0.9;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="nav-text">Information</span>
                        </div>
                        <svg class="w-5 h-5 transition-transform info-submenu-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div class="info-submenu" style="max-height: 0; overflow: hidden; transition: max-height 0.3s ease; margin-top: 8px;">
                        @foreach($informationPages as $page)
                            <a href="{{ route('information.show', $page->slug) }}" class="block py-3 px-6 text-sm transition-colors" style="color: rgba(255,255,255,0.9); background: rgba(255,255,255,0.05); border-radius: 12px; margin-bottom: 6px; padding-left: 3rem;">
                                {{ $page->menu_title ?? $page->title }}
                            </a>
                        @endforeach
                    </div>
                </li>

                <script>
                function toggleInfoSubmenu(button) {
                    const submenu = button.nextElementSibling;
                    const arrow = button.querySelector('.info-submenu-arrow');

                    if (submenu.style.maxHeight === '0px' || !submenu.style.maxHeight) {
                        submenu.style.maxHeight = submenu.scrollHeight + 'px';
                        if (arrow) arrow.style.transform = 'rotate(180deg)';
                    } else {
                        submenu.style.maxHeight = '0';
                        if (arrow) arrow.style.transform = 'rotate(0deg)';
                    }
                }
                </script>
            @endif
            <li>
                <a href="/top-5">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                    </svg>
                    <span class="nav-text">Top 5</span>
                </a>
            </li>
            <li>
                <a href="/beste-marken">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                    </svg>
                    <span class="nav-text">Beste Marken</span>
                </a>
            </li>
            <li>
                <a href="/ratgeber">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                    <span class="nav-text">Ratgeber</span>
                </a>
            </li>
            <li>
                <a href="/testberichte">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                    </svg>
                    <span class="nav-text">Testberichte</span>
                </a>
            </li>
        </ul>
    </nav>
</div>
