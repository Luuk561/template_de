<title>@yield('title', $metaTitle)</title>
<meta name="description" content="@yield('meta_description', $metaDesc)">
<meta name="robots" content="@yield('meta_robots', app()->environment('production') ? 'index, follow' : 'noindex, nofollow')">
<link rel="canonical" href="{{ $canonicalUrl }}" />

@stack('pagination-meta')
