<footer class="bg-white border-t text-sm text-gray-600">
    <div class="max-w-screen-xl mx-auto px-6 py-16 grid grid-cols-1 md:grid-cols-3 gap-12">
        <div>
            <div class="font-bold text-gray-900 mb-4 text-base">Over {{ $siteName }}</div>
            <p class="text-gray-600 leading-relaxed">
                {{ $siteName }} helpt je bij het kiezen van de beste producten op basis van onafhankelijke tests, reviews en vergelijkingen.
            </p>
        </div>

        <div>
            <div class="font-bold text-gray-900 mb-4 text-base">Navigatie</div>
            <ul class="space-y-2">
                <li><a href="/" class="hover:underline">Home</a></li>
                <li><a href="/producten" class="hover:underline">Producten</a></li>
                <li><a href="/top-5" class="hover:underline">Top 5</a></li>
                <li><a href="/beste-merken" class="hover:underline">Beste Merken</a></li>
                <li><a href="/blogs" class="hover:underline">Blogs</a></li>
                <li><a href="/reviews" class="hover:underline">Reviews</a></li>
                <li><a href="/meer-ontdekken" class="hover:underline">Meer ontdekken</a></li>
            </ul>
        </div>

        <div>
            <div class="font-bold text-gray-900 mb-4 text-base">Informatie</div>
            <ul class="space-y-2">
                <li><a href="/team" class="hover:underline">Ons Team</a></li>
                <li><a href="/privacy" class="hover:underline">Privacybeleid</a></li>
                <li><a href="/disclaimer" class="hover:underline">Disclaimer</a></li>
                <li><a href="/contact" class="hover:underline">Contact</a></li>
                <li><a href="/sitemap.xml" class="hover:underline">Sitemap</a></li>
            </ul>
        </div>
    </div>

    <div class="border-t px-6 py-6 text-center text-xs text-gray-500">
        &copy; {{ date('Y') }} {{ $siteName }}. Alle rechten voorbehouden.
    </div>
</footer>
