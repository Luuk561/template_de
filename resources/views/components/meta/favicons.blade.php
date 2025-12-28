@php
    // Use file modification time as cache buster
    $faviconFile = public_path('favicon.svg');
    $cacheBuster = file_exists($faviconFile) ? '?v=' . filemtime($faviconFile) : '?v=' . time();
    $primaryColor = getSetting('primary_color', '#3B82F6');
@endphp
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}{{ $cacheBuster }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon.svg') }}{{ $cacheBuster }}">
<meta name="theme-color" content="{{ $primaryColor }}">
