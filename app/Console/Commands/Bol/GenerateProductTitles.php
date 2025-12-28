<?php

namespace App\Console\Commands\Bol;

use App\Models\Product;
use App\Services\OpenAIService;
use Illuminate\Console\Command;

class GenerateProductTitles extends Command
{
    protected $signature = 'products:generate-titles {--dry-run : Show what would be changed without actually changing it} {--limit= : Limit number of products to process}';
    protected $description = 'Generate clean, SEO-friendly product titles using OpenAI';

    protected $openai;

    public function __construct(OpenAIService $openai)
    {
        parent::__construct();
        $this->openai = $openai;
    }

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit');

        if ($dryRun) {
            $this->info('Running in DRY RUN mode - no changes will be made');
            $this->newLine();
        }

        // Get products that need better titles
        $query = Product::whereNotNull('brand')
            ->whereNotNull('ean');

        if ($limit) {
            $query->limit((int) $limit);
        }

        $products = $query->get();
        $updated = 0;

        $this->info("Processing {$products->count()} products...");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($products->count());

        foreach ($products as $product) {
            try {
                $newTitle = $this->generateTitle($product);

                if ($newTitle && $newTitle !== $product->title) {
                    $this->newLine();
                    $this->warn("Product #{$product->id}:");
                    $this->line("  OLD: {$product->title}");
                    $this->line("  NEW: {$newTitle}");
                    $this->newLine();

                    if (!$dryRun) {
                        $product->title = $newTitle;
                        $product->save();
                        $updated++;
                    }
                }

                $progressBar->advance();

                // Rate limiting - pause between requests
                usleep(100000); // 0.1 second

            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Failed for product #{$product->id}: " . $e->getMessage());
                $this->newLine();
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->newLine();
        $this->newLine();

        if ($dryRun) {
            $this->info("DRY RUN complete. Would have updated {$updated} product titles.");
        } else {
            $this->info("Successfully updated {$updated} product titles.");
        }

        return 0;
    }

    private function generateTitle($product)
    {
        // Get key specifications
        $specs = $product->specifications()
            ->whereIn('name', [
                'Maximale snelheid', 'Max. snelheid', 'Snelheid',
                'Capaciteit', 'Inhoud', 'Liter', 'Volume',
                'Vermogen', 'Watt', 'Power',
                'Kleur', 'Color',
                'Maat', 'Size', 'Afmeting'
            ])
            ->limit(5)
            ->get()
            ->pluck('value', 'name')
            ->toArray();

        $description = strip_tags($product->description ?? $product->source_description ?? '');
        $description = substr($description, 0, 500);

        $prompt = $this->buildPrompt($product->brand, $product->title, $description, $specs);

        // Use OpenAI client directly
        $client = \OpenAI::client(config('services.openai.key'));

        $response = $client->chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'Je bent een expert in het schrijven van korte, krachtige producttitels voor e-commerce. Antwoord ALLEEN met de nieuwe titel, zonder quotes of extra tekst.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.3,
            'max_tokens' => 100
        ]);

        $title = trim($response->choices[0]->message->content);

        // Remove quotes if AI added them
        $title = trim($title, '"\'');

        // Ensure title is not too long (max 60 chars for SEO)
        if (mb_strlen($title) > 60) {
            // Truncate at last space before 60 chars
            $title = mb_substr($title, 0, 60);
            $lastSpace = mb_strrpos($title, ' ');
            if ($lastSpace !== false) {
                $title = mb_substr($title, 0, $lastSpace);
            }
        }

        return $title;
    }

    private function buildPrompt($brand, $currentTitle, $description, $specs)
    {
        $specsText = '';
        if (!empty($specs)) {
            $specsText = "\n\nBelangrijkste specificaties:";
            foreach ($specs as $name => $value) {
                $specsText .= "\n- {$name}: {$value}";
            }
        }

        return <<<PROMPT
Maak een perfecte producttitel voor dit product, zoals je ziet bij professionele webshops (bijv. "Samsung Galaxy Watch7 Smartwatch 40mm Cream").

Merk: {$brand}
Huidige titel: {$currentTitle}
Beschrijving: {$description}{$specsText}

REGELS:
1. Begin ALTIJD met het merk
2. Daarna het producttype (bijv. "Loopband", "Slowcooker", "Smartwatch")
3. Daarna de belangrijkste specs (model, capaciteit, kleur, etc)
4. Maximaal 60 karakters
5. Geen rare hoofdletters - alleen eerste letter van woorden
6. GEEN "(EAN: ...)" toevoegen
7. Natuurlijk leesbaar, niet een opsomming
8. Nederlands, tenzij merknaam/modelnaam Engels is

Voorbeelden van GOEDE titles:
- "Samsung Galaxy Watch7 Smartwatch 40mm Cream"
- "Garmin Forerunner 55 Sporthorloge 42mm Zwart"
- "CrockPot Express Pot Multicooker 5,6L RVS"
- "Infinity Loopband 12km/u Inklapbaar met Helling"

Voorbeelden van SLECHTE titles:
- "Loopband (EAN: 123456)" (geen merk, geen specs)
- "CITYSPORTS APP en 360° tablethouder" (begint niet met producttype)
- "12 Programma's Extra Krachtige 2.0 PK Motor" (geen merk/producttype)

Genereer NU de perfecte titel:
PROMPT;
    }
}
