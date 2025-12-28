<!DOCTYPE html>
<html lang="nl">
<head>
    <x-layout.head />
</head>

<body class="text-gray-800 text-base">
    @php
        \Carbon\Carbon::setLocale('nl');
        $huidigeMaand = \Carbon\Carbon::now()->translatedFormat('F');
    @endphp

    {{-- Discount Banner (boven navigatie, alleen zichtbaar als enabled in settings) --}}
    <x-banner.discount-top />

    <x-layout.scroll-line />

    <x-layout.navigation />

    <x-layout.mobile-menu />

    <main class="w-full pb-10" style="padding-top: max(6rem, calc(8rem + env(safe-area-inset-top, 0px))); padding-left: env(safe-area-inset-left, 0px); padding-right: env(safe-area-inset-right, 0px);">
        @hasSection('breadcrumbs')
            <div class="mb-6">
                @yield('breadcrumbs')
            </div>
        @endif

        @yield('content')
    </main>

    <x-layout.footer />

    <script src="{{ asset('js/app-layout.min.js') }}"></script>

    @yield('scripts')

    @include('components.banners.black-friday', [
        'bfActive' => $bfActive,
        'bfUntil' => $bfUntil
    ])

    @yield('structured-data')
    @stack('modals')

    <x-analytics.affiliate-tracking />

    {{-- Discount Banner replaced popup (configurable per site via settings) --}}

    @stack('scripts')
</body>
</html>
