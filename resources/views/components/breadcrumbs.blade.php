<nav class="text-sm text-gray-500 mb-6 text-center" aria-label="Breadcrumb">
    <ol class="list-reset inline-flex flex-wrap justify-center items-center gap-1">
        <li><a href="{{ url('/') }}" class="hover:underline">Home</a></li>

        @foreach ($items as $label => $url)
            <li class="mx-1">›</li>
            @if ($loop->last)
                <li class="text-gray-800 font-medium">{{ $label }}</li>
            @else
                <li><a href="{{ $url }}" class="hover:underline">{{ $label }}</a></li>
            @endif
        @endforeach
    </ol>
</nav>

{{-- BreadcrumbList Schema for SEO --}}
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    "itemListElement": [
        {
            "@type": "ListItem",
            "position": 1,
            "name": "Home",
            "item": "{{ url('/') }}"
        }@foreach ($items as $label => $url),
        {
            "@type": "ListItem",
            "position": {{ $loop->iteration + 1 }},
            "name": "{{ $label }}"@if($url),
            "item": "{{ $url }}"
            @endif

        }@endforeach

    ]
}
</script>
