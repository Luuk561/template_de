<?php

namespace App\Console\Commands;

use App\Models\InformationPage;
use App\Services\OpenAIService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateInformationContent extends Command
{
    protected $signature = 'generate:information-content
                            {--menu-title= : Korte titel voor dropdown menu}
                            {--article-title= : Langere titel voor H1 in artikel}
                            {--niche= : De niche/product categorie}
                            {--save : Opslaan in database}';

    protected $description = 'Generate high-quality content for information pages';

    public function handle()
    {
        $menuTitle = $this->option('menu-title') ?? $this->ask('Korte titel voor menu (bijv. "Energieverbruik")?');
        $articleTitle = $this->option('article-title') ?? $this->ask('Lange titel voor artikel (bijv. "Wat kost een airfryer aan stroom?")?');
        $niche = $this->option('niche') ?? $this->ask('Wat is de niche/product categorie?');

        if (empty($menuTitle) || empty($articleTitle) || empty($niche)) {
            $this->error('Menu titel, artikel titel en niche zijn verplicht!');
            return 1;
        }

        $this->info("Menu titel: {$menuTitle}");
        $this->info("Artikel titel: {$articleTitle}");
        $this->info("Niche: {$niche}");
        $this->newLine();

        try {
            $openai = app(OpenAIService::class);

            // Generate content
            $this->info('Genereren van content (dit kan even duren)...');
            $contentPrompt = $this->buildContentPrompt($articleTitle, $niche);
            $content = $openai->generateFromPrompt($contentPrompt, 'gpt-4o-mini');

            // Clean up markdown artifacts from content
            $content = preg_replace('/^```(?:html)?\s*/m', '', $content);
            $content = preg_replace('/\s*```$/m', '', $content);
            $content = trim($content);

            // Generate excerpt
            $this->info('Genereren van excerpt...');
            $excerptPrompt = $this->buildExcerptPrompt($articleTitle, $content);
            $excerpt = $openai->generateFromPrompt($excerptPrompt, 'gpt-4o-mini');
            $excerpt = trim($excerpt);

            // Generate meta description
            $this->info('Genereren van meta description...');
            $metaPrompt = $this->buildMetaPrompt($articleTitle, $excerpt);
            $metaDescription = $openai->generateFromPrompt($metaPrompt, 'gpt-4o-mini');
            $metaDescription = trim($metaDescription);

            $this->newLine();
            $this->line('==============================================');
            $this->info('GEGENEREERDE CONTENT:');
            $this->line('==============================================');
            $this->newLine();

            $this->info('MENU TITEL:');
            $this->line($menuTitle);
            $this->newLine();

            $this->info('ARTIKEL TITEL (H1):');
            $this->line($articleTitle);
            $this->newLine();

            $this->info('EXCERPT:');
            $this->line($excerpt);
            $this->newLine();

            $this->info('META DESCRIPTION (' . strlen($metaDescription) . ' karakters):');
            $this->line($metaDescription);
            $this->newLine();

            $this->info('CONTENT (eerste 500 karakters):');
            $this->line(Str::limit(strip_tags($content), 500));
            $this->newLine();

            if ($this->option('save') || $this->confirm('Wil je deze pagina opslaan in de database?', false)) {
                $slug = Str::slug($menuTitle);

                // Check if slug already exists
                if (InformationPage::where('slug', $slug)->exists()) {
                    $slug = $slug . '-' . time();
                    $this->warn("Slug bestaat al, nieuwe slug: {$slug}");
                }

                $order = InformationPage::max('order') + 1;

                InformationPage::create([
                    'title' => $articleTitle,
                    'menu_title' => $menuTitle,
                    'slug' => $slug,
                    'content' => $content,
                    'excerpt' => $excerpt,
                    'meta_title' => Str::limit($articleTitle, 60),
                    'meta_description' => $metaDescription,
                    'order' => $order,
                    'is_active' => true,
                ]);

                $this->info("Informatie pagina opgeslagen!");
                $this->info("Menu: {$menuTitle}");
                $this->info("Slug: {$slug}");
                $this->info("URL: /informatie/{$slug}");
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }

    private function buildContentPrompt(string $title, string $niche): string
    {
        $siteName = getSetting('site_name', 'onze site');

        return <<<PROMPT
Je bent een expert contentschrijver voor een Nederlandse affiliate website over {$niche}.

OPDRACHT: Schrijf een uitgebreid, waardevol artikel voor de pagina: "{$title}"

BELANGRIJKE EISEN:
1. 800-1500 woorden (diepgaand maar scanbaar)
2. Focus op decision-stage kopers (mensen die onderzoeken welk product ze moeten kopen)
3. Praktisch en actionable (geen fluff)
4. Nederlandse taal, informeel maar professioneel
5. SEO-geoptimaliseerd maar natuurlijk leesbaar
6.Link subtiel naar producten (zonder pushy te zijn)

STRUCTUUR:
- Begin met een korte intro (waarom is dit belangrijk?)
- Gebruik H2 en H3 koppen voor structuur
- Voeg bullets/lists toe waar relevant
- Gebruik concrete voorbeelden en cijfers
- Eindig met een praktische conclusie/samenvatting

HTML FORMATTING & VISUAL ELEMENTS:
Maak het artikel VISUEEL AANTREKKELIJK met deze elementen (gebruik er MINIMAAL 2-3):

1. HIGHLIGHT BOX voor belangrijke info:
<div class="bg-blue-50 border-l-4 border-blue-500 p-6 my-8 rounded-r-lg">
  <h3 class="text-xl font-bold text-blue-900 mb-2">Belangrijk om te weten</h3>
  <p class="text-blue-800">Belangrijke informatie...</p>
</div>

2. TIP/ADVIES BOX:
<div class="bg-green-50 border-l-4 border-green-500 p-6 my-8 rounded-r-lg">
  <div class="flex items-start">
    <svg class="w-6 h-6 text-green-600 mr-3 flex-shrink-0 mt-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
    <div>
      <h3 class="text-lg font-bold text-green-900 mb-2">Pro tip</h3>
      <p class="text-green-800">Praktisch advies...</p>
    </div>
  </div>
</div>

3. CHECKLIST met groene checkmarks:
<ul class="space-y-3 my-8">
  <li class="flex items-start">
    <svg class="w-6 h-6 text-green-500 mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
    <span class="text-gray-700">Checklist item...</span>
  </li>
</ul>

4. VERGELIJKINGS TABEL:
<div class="overflow-x-auto my-8">
  <table class="min-w-full bg-white border border-gray-200 rounded-lg overflow-hidden">
    <thead class="bg-gray-50">
      <tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Waarde</th></tr>
    </thead>
    <tbody class="divide-y divide-gray-200">
      <tr><td class="px-6 py-4">Rij</td><td class="px-6 py-4">Data</td></tr>
    </tbody>
  </table>
</div>

Standaard elementen:
- <h2> en <h3> voor koppen
- <p> voor paragrafen
- <ul>/<ol> voor lijsten
- <strong> voor nadruk
- GEEN emoji's, GEEN h1

TONE OF VOICE:
- Vriendelijk en behulpzaam
- Eerlijk en transparant
- Praktisch gericht
- Geen overdreven marketing taal

CONTEXT:
Dit artikel staat op {$siteName}, een vergelijkingssite voor {$niche}.
Lezers zijn geïnteresseerd in het kopen van {$niche} en zoeken informatie om een goede keuze te maken.

Schrijf nu het artikel voor: "{$title}"
PROMPT;
    }

    private function buildExcerptPrompt(string $title, string $content): string
    {
        return <<<PROMPT
Schrijf een korte, pakkende samenvatting van maximaal 200 karakters voor het volgende artikel.

TITEL: {$title}

CONTENT:
{$content}

EISEN:
- Max 200 karakters
- Pakkend en informatief
- Geeft kernboodschap weer
- Nederlandse taal
- Geen emoji's
- Eindigt met punt

Alleen de excerpt tekst, geen extra uitleg.
PROMPT;
    }

    private function buildMetaPrompt(string $title, string $excerpt): string
    {
        return <<<PROMPT
Schrijf een SEO-geoptimaliseerde meta description voor deze pagina.

TITEL: {$title}
EXCERPT: {$excerpt}

EISEN:
- Exact 150-155 karakters
- Bevat de belangrijkste zoekwoorden
- Pakkend en actionable
- Nederlandse taal
- Geen emoji's
- Eindigt met punt of call-to-action

Alleen de meta description, geen extra uitleg.
PROMPT;
    }
}
