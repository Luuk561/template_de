<?php

namespace App\Console\Commands;

use App\Services\OpenAIService;
use Illuminate\Console\Command;

class GenerateInformationTopics extends Command
{
    protected $signature = 'generate:information-topics {--niche=}';
    protected $description = 'Generate 5-7 decision-stage information page topics for a specific niche';

    public function handle()
    {
        $niche = $this->option('niche') ?? $this->ask('Wat is de niche/product categorie? (bijv. airfryer met dubbele lade, sporthorloge, massage gun)');

        if (empty($niche)) {
            $this->error('Niche is verplicht!');
            return 1;
        }

        $this->info("Generating topics for niche: {$niche}");
        $this->newLine();

        try {
            $openai = app(OpenAIService::class);

            $prompt = $this->buildPrompt($niche);

            $this->info('AI is aan het werk...');
            $response = $openai->generateFromPrompt($prompt, 'gpt-4o-mini');

            $this->newLine();
            $this->line('==============================================');
            $this->info('VOORGESTELDE INFORMATIE PAGINA TOPICS:');
            $this->line('==============================================');
            $this->newLine();
            $this->line($response);
            $this->newLine();

            if ($this->confirm('Wil je deze topics opslaan in de database?', false)) {
                $this->info('Topics zijn gegenereerd. Gebruik deze als input voor het content generatie commando.');
                $this->info('Voeg handmatig toe via: InformationPage::create([...])');
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }

    private function buildPrompt(string $niche): string
    {
        return <<<PROMPT
Je bent een SEO-expert voor Nederlandse affiliate websites die zich richt op {$niche}.

DOEL: Genereer 5-7 informatie pagina onderwerpen die perfect zijn voor mensen in de DECISION-STAGE van het koopproces.

BELANGRIJKE CRITERIA:
1. Focus op koopbeslissingen (niet te breed zoals "algemene tips")
2. Beantwoord praktische vragen die mensen hebben VOORDAT ze kopen
3. Moet natuurlijk linken naar producten (zonder pushy te zijn)
4. Zoekvolume moet goed zijn (mensen moeten dit Googlen)
5. Specifiek voor {$niche} (niet generiek)

GOEDE VOORBEELDEN voor airfryers:
- "Wat kost een airfryer in gebruik?" (energie = koopoverweging)
- "Airfryer voor 2 of 4 personen kiezen" (helpt productchoice)
- "Verschil enkele vs dubbele lade airfryer" (specifiek voor niche)
- "Veelgemaakte fouten bij airfryer kopen" (decision stage)
- "Welke functies heb je echt nodig?" (feature beslissingen)

SLECHTE VOORBEELDEN:
❌ Te breed: "Gezonde recepten" (geen koopintentie)
❌ Te technisch: "Specificaties uitgelegd" (te diep in funnel)
❌ Te algemeen: "Geschiedenis van airfryers" (irrelevant)

FORMAAT per topic:
- menu_title: Korte titel voor dropdown menu (max 40 karakters, compact)
- article_title: Langere, pakkende H1 voor het artikel zelf (max 70 karakters)
- slug: URL-friendly versie
- meta_description: SEO beschrijving (155 karakters)
- reason: Waarom dit topic waardevol is

VOORBEELDEN:
{
  "menu_title": "Energieverbruik",
  "article_title": "Wat kost een airfryer aan stroom? Energieverbruik uitgelegd",
  "slug": "energieverbruik-kosten",
  "meta_description": "...",
  "reason": "..."
}

{
  "menu_title": "Gezinsgrootte kiezen",
  "article_title": "Welke airfryer past bij jouw gezinsgrootte?",
  "slug": "gezinsgrootte-kiezen",
  "meta_description": "...",
  "reason": "..."
}

Genereer nu 5-7 perfecte topics voor: {$niche}

Formaat als JSON array:
[
  {
    "menu_title": "...",
    "article_title": "...",
    "slug": "...",
    "meta_description": "...",
    "reason": "..."
  }
]
PROMPT;
    }
}
