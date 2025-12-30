<footer class="bg-white border-t text-sm text-gray-600">
    <div class="max-w-screen-xl mx-auto px-6 py-16 grid grid-cols-1 md:grid-cols-3 gap-12">
        <div>
            <div class="font-bold text-gray-900 mb-4 text-base">Über {{ $siteName }}</div>
            <p class="text-gray-600 leading-relaxed">
                {{ $siteName }} hilft Ihnen bei der Auswahl der besten Produkte auf Basis unabhängiger Tests, Testberichte und Vergleiche.
            </p>
        </div>

        <div>
            <div class="font-bold text-gray-900 mb-4 text-base">Navigation</div>
            <ul class="space-y-2">
                <li><a href="/" class="hover:underline">Home</a></li>
                <li><a href="/produkte" class="hover:underline">Produkte</a></li>
                <li><a href="/top-5" class="hover:underline">Top 5</a></li>
                <li><a href="/beste-marken" class="hover:underline">Beste Marken</a></li>
                <li><a href="/ratgeber" class="hover:underline">Ratgeber</a></li>
                <li><a href="/testberichte" class="hover:underline">Testberichte</a></li>
            </ul>
        </div>

        <div>
            <div class="font-bold text-gray-900 mb-4 text-base">Information</div>
            <ul class="space-y-2">
                <li><a href="/team" class="hover:underline">Unser Team</a></li>
                <li><a href="/impressum" class="hover:underline">Impressum</a></li>
                <li><a href="/datenschutz" class="hover:underline">Datenschutzerklärung</a></li>
                <li><a href="/haftungsausschluss" class="hover:underline">Haftungsausschluss</a></li>
                <li><a href="/kontakt" class="hover:underline">Kontakt</a></li>
                <li><a href="/sitemap.xml" class="hover:underline">Sitemap</a></li>
            </ul>
        </div>
    </div>

    <div class="border-t px-6 py-6 text-center text-xs text-gray-500">
        <p class="mb-2">&copy; {{ date('Y') }} {{ $siteName }}. Alle Rechte vorbehalten.</p>
        <p class="text-gray-400">Als Amazon-Partner verdienen wir an qualifizierten Verkäufen.</p>
    </div>
</footer>
