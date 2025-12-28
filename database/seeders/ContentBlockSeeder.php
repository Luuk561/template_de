<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContentBlockSeeder extends Seeder
{
    public function run(): void
    {
        $blocks = [
            [
                'key' => 'homepage.hero',
                'content' => '<h1 class="text-4xl sm:text-5xl font-extrabold mb-4">Dé vergelijking van airfryers met dubbele lade</h1><p class="text-lg sm:text-xl">Bijgewerkt in {{ maand }} {{ jaar }} – ontdek de beste keuzes voor jouw keuken.</p>',
            ],
            [
                'key' => 'homepage.intro',
                'content' => '<h2 class="text-2xl font-bold mb-4">Waarom steeds meer mensen kiezen voor een dubbele airfryer</h2><p>Met een dubbele airfryer bereid je twee gerechten tegelijk – ideaal voor drukke dagen of grotere huishoudens. Geen wachttijden, geen smaakvermenging. Efficiënt én veelzijdig koken.</p>',
            ],
            [
                'key' => 'homepage.productgrid_title',
                'content' => 'Topkeuzes van dit moment',
            ],
            [
                'key' => 'homepage.info',
                'content' => '<h2 class="text-2xl font-bold mb-4">Wat maakt een airfryer met dubbele lade uniek?</h2><p>Dubbele lades betekenen dubbele flexibiliteit. Kies zelf temperatuur en tijd per lade, of synchroniseer beide zijden. Handig als je frietjes wilt combineren met kip, vis of groenten zonder smaakoverdracht.</p>',
            ],
            [
                'key' => 'homepage.reviews_cta',
                'content' => '<div class="max-w-xl text-center sm:text-left"><h2 class="text-2xl sm:text-3xl font-extrabold mb-4">Gebruikers delen hun ervaringen</h2><p class="text-base sm:text-lg mb-4">Benieuwd naar accuduur, gebruiksgemak of reiniging? Lees hoe anderen hun airfryer met dubbele lade écht ervaren.</p><a href="/reviews" class="inline-block bg-yellow-400 hover:bg-yellow-500 text-gray-900 font-semibold py-3 px-8 rounded-full transition duration-300 shadow-lg">Bekijk reviews</a></div>',
            ],
            [
                'key' => 'homepage.seo1',
                'content' => '<h2 class="text-2xl font-bold mb-4">Waar let je op bij aankoop?</h2><ul class="list-disc list-inside space-y-2"><li>Inhoud per lade (meestal 3–5 liter)</li><li>Instelbare zones voor tijd en temperatuur</li><li>Voorgeprogrammeerde standen</li><li>Anti-aanbaklagen en reinigbaarheid</li><li>Prijs-kwaliteitverhouding en reviews</li></ul>',
            ],
            [
                'key' => 'homepage.products_cta',
                'content' => '<div class="max-w-xl text-center sm:text-left"><h2 class="text-2xl sm:text-3xl font-extrabold mb-4">Bekijk het aanbod</h2><p class="text-base sm:text-lg mb-4">Vergelijk alle modellen op functies, inhoud, prijs en beoordeling. Of bekijk direct onze <a href="/top-5" class="underline hover:text-orange-600">top 5 beste dubbele airfryers</a>.</p><a href="/producten" class="inline-block bg-yellow-400 hover:bg-yellow-500 text-gray-900 font-semibold py-3 px-8 rounded-full transition duration-300 shadow-lg">Bekijk producten</a></div>',
            ],
            [
                'key' => 'homepage.seo2',
                'content' => '<h2 class="text-2xl font-bold mb-4">Waarom airfryermetdubbelelade.nl jouw startpunt is</h2><p>Wij nemen het zoekwerk uit handen. Geen gesponsorde producten, maar eerlijke vergelijkingen op basis van gebruikerservaringen, functionaliteit en prijs. Dankzij onze duidelijke filters en overzichtspaginas weet jij in no-time welke airfryer met dubbele lade bij je past.</p><p>In onze <a href="/top-5" class="underline hover:text-orange-600">top 5 van {{ maand }} {{ jaar }}</a> staan de populairste modellen, getest op prestaties en gebruiksgemak. Daarnaast vind je <a href="/reviews" class="underline hover:text-orange-600">echte gebruikersreviews</a> die je helpen een weloverwogen keuze te maken.</p><p>Nieuw in dit onderwerp? Lees dan ook onze <a href="/blogs" class="underline hover:text-orange-600">blogs vol tips en uitleg</a>. Of <a href="/producten" class="underline hover:text-orange-600">bekijk het volledige aanbod</a> en filter op wat jij belangrijk vindt.</p>',
            ],
        ];

        foreach ($blocks as $block) {
            DB::table('content_blocks')->updateOrInsert(
                ['key' => $block['key']],
                [
                    'content' => $block['content'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
