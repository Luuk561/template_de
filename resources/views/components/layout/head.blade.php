<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}">

@yield('head')

<x-meta.seo-tags />
<x-meta.favicons />
<x-meta.social-tags />
<x-meta.fonts />

@vite(['resources/css/app.css', 'resources/js/app.js'])

<link rel="stylesheet" href="{{ asset('css/app-layout.min.css') }}">

<style>
    /* Dynamic styles that need PHP variables */
    html, body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    }

    ::selection {
        background: {{ $primaryColor }};
        color: white;
    }

    .nav-links a {
        color: #1f2937;
        font-weight: 500;
        font-size: 0.9375rem;
        position: relative;
        padding: 0.5rem 1rem;
        border-radius: 0.75rem;
        transition: all 0.2s ease;
        border: 1px solid transparent;
    }

    .nav-links a::after {
        display: none;
    }

    .nav-links a:hover {
        background: rgba(0, 0, 0, 0.02);
        border-color: rgba(0, 0, 0, 0.06);
    }

    .nav-links a.active {
        background: {{ $primaryColor }};
        color: white;
        font-weight: 600;
        border-color: {{ $primaryColor }};
    }

    .info-dropdown-container button {
        color: #1f2937;
        padding: 0.5rem 1rem;
        border-radius: 0.75rem;
        transition: all 0.2s ease;
        background: none;
        border: 1px solid transparent;
        cursor: pointer;
        font-weight: 500;
        font-size: 0.9375rem;
    }

    .info-dropdown-container button:hover {
        background: rgba(0, 0, 0, 0.02);
        border-color: rgba(0, 0, 0, 0.06);
    }

    .info-dropdown-container button.active {
        background: {{ $primaryColor }};
        color: white;
        font-weight: 600;
        border-color: {{ $primaryColor }};
    }

    .info-dropdown-container button.active svg {
        stroke: white;
    }

    .hamburger-button {
        background: white !important;
        border: 1px solid rgba(0, 0, 0, 0.06) !important;
    }

    .hamburger-button:hover {
        border-color: rgba(0, 0, 0, 0.12) !important;
    }

    .hamburger-button div {
        background-color: {{ $primaryColor }} !important;
    }

    .close-button {
        background: white !important;
        border: 1px solid rgba(0, 0, 0, 0.06) !important;
    }

    .close-button:hover {
        border-color: rgba(0, 0, 0, 0.12) !important;
    }

    .close-button svg {
        stroke: {{ $primaryColor }};
    }

    .hamburger-menu {
        background-color: {{ $primaryColor }};
    }

    .menu-search input {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    }

    #scrollLine {
        background-color: {{ $primaryColor }};
    }

    /* Information Article Styling - Apple-style minimal */
    .article-content h2 {
        font-size: 1.875rem;
        font-weight: 700;
        color: #111827;
        margin-top: 4rem;
        margin-bottom: 1.5rem;
        scroll-margin-top: 6rem;
        line-height: 1.2;
    }

    .article-content h3 {
        font-size: 1.5rem;
        font-weight: 600;
        color: #111827;
        margin-top: 2.5rem;
        margin-bottom: 1rem;
        line-height: 1.3;
    }

    .article-content p {
        color: #374151;
        line-height: 1.75;
        margin-top: 1.5rem;
        margin-bottom: 1.5rem;
        font-size: 1.125rem;
    }

    .article-content ul, .article-content ol {
        margin-top: 1.5rem;
        margin-bottom: 1.5rem;
        padding-left: 1.5rem;
    }

    .article-content li {
        color: #374151;
        margin-top: 0.5rem;
        margin-bottom: 0.5rem;
        line-height: 1.75;
        font-size: 1.125rem;
    }

    .article-content ul li {
        list-style-type: disc;
    }

    .article-content ol li {
        list-style-type: decimal;
    }

    .article-content strong {
        color: #111827;
        font-weight: 600;
    }

    .article-content blockquote {
        border-left: 4px solid #d1d5db;
        padding-left: 1.5rem;
        font-style: italic;
        color: #6b7280;
        margin-top: 2rem;
        margin-bottom: 2rem;
        font-size: 1.25rem;
        line-height: 1.75;
    }

    .article-content a {
        color: #2563eb;
        text-decoration: none;
    }

    .article-content a:hover {
        text-decoration: underline;
    }

    .article-content img {
        border-radius: 0.75rem;
        box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
    }

    .article-content table {
        width: 100%;
        margin-top: 2rem;
        margin-bottom: 2rem;
        border-collapse: collapse;
    }

    .article-content thead {
        border-bottom: 2px solid #e5e7eb;
    }

    .article-content th {
        padding: 1rem 1.5rem;
        text-align: left;
        font-size: 0.875rem;
        font-weight: 600;
        color: #111827;
    }

    .article-content td {
        padding: 1rem 1.5rem;
        color: #374151;
        border-bottom: 1px solid #f3f4f6;
    }

    /* Highlight boxes - Apple-style minimaal */
    .article-content .not-prose {
        background: linear-gradient(135deg, #fafafa 0%, #ffffff 100%);
        border: 1px solid #e5e7eb;
        border-radius: 1rem;
        padding: 2rem;
        margin: 3rem 0;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .article-content .not-prose h3 {
        font-size: 1.125rem;
        font-weight: 600;
        color: #111827;
        margin: 0 0 0.75rem 0;
    }

    .article-content .not-prose p {
        color: #4b5563;
        margin: 0;
        font-size: 1rem;
        line-height: 1.6;
    }

    /* Links in content sections (alle pagina's) */
    section a:not(.cta-button):not(.cta-button-secondary):not(.product-card):not([class*="nav-"]) {
        color: {{ $primaryColor }};
        text-decoration: underline;
        font-weight: 600;
        transition: all 0.2s ease;
    }

    section a:not(.cta-button):not(.cta-button-secondary):not(.product-card):not([class*="nav-"]):hover {
        color: color-mix(in srgb, {{ $primaryColor }} 80%, #000 20%);
        text-decoration-thickness: 2px;
    }
</style>

<x-analytics.tracking />

@stack('head')
