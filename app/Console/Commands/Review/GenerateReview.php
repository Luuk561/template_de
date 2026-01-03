<?php

namespace App\Console\Commands\Review;

use App\Models\Product;
use App\Models\Review;
use App\Models\TeamMember;
use App\Services\OpenAIService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GenerateReview extends Command
{
    protected $signature = 'generate:review {product_id?}';

    protected $description = 'Genereer een review voor een product via OpenAI (gestructureerd in blokken)';

    protected OpenAIService $openAI;

    public function __construct(OpenAIService $openAI)
    {
        parent::__construct();
        $this->openAI = $openAI;
    }

    public function handle()
    {
        Log::info('▶️ Review-generatie gestart op '.now());

        $productId = $this->argument('product_id');

        if ($productId) {
            $product = Product::find($productId);
            if (! $product) {
                $this->error("❌ Product met ID {$productId} niet gevonden.");
                Log::error("❌ Product met ID {$productId} niet gevonden.");

                return;
            }
        } else {
            // Auto-select: Pick most popular product without review
            $product = Product::whereDoesntHave('review')
                ->where('rating_average', '>=', 4.0) // Only good products
                ->whereNotNull('price')
                ->orderByDesc('rating_count') // Most reviews = most popular
                ->orderByDesc('rating_average')
                ->first();

            if (! $product) {
                // Fallback: any product without review
                $product = Product::whereDoesntHave('review')->first();
            }

            if (! $product) {
                $this->warn('❌ Geen producten zonder review gevonden.');
                Log::warning('❌ Geen producten zonder review gevonden op '.now());

                return;
            }

            $this->info("Auto-selected popular product: {$product->title} (rating: {$product->rating_average}, reviews: {$product->rating_count})");
        }

        $this->info("🧠 Genereer review voor: {$product->title}");
        Log::info("🎯 Product geselecteerd voor review: {$product->title}");

        $rating = $product->rating_average ?? rand(47, 49) / 10;

        // Use new generateProductReview method for v3 JSON structure
        $content = $this->openAI->generateProductReview(
            $product->title,
            $product->description ?? 'Premium product',
            $product->brand ?? 'Unknown'
        );

        // Validate generated content
        if (empty($content) || strlen($content) < 50) {
            $this->error('❌ Lege of ongeldige reviewinhoud ontvangen.');
            Log::error('❌ OpenAI gaf lege of onbruikbare inhoud terug', [
                'product_id' => $product->id,
                'product_title' => $product->title,
                'content_length' => strlen($content)
            ]);

            return;
        }

        // Validate JSON structure
        $jsonContent = json_decode($content, true);
        if (!$jsonContent || !isset($jsonContent['intro']) || empty($jsonContent['sections'])) {
            $this->error('❌ OpenAI genereerde ongeldige review JSON.');
            Log::error('❌ Review JSON validatie gefaald', [
                'product_id' => $product->id,
                'json_error' => json_last_error_msg(),
                'content_preview' => substr($content, 0, 200)
            ]);

            return;
        }

        $slug = 'review-'.Str::slug(Str::limit($product->title, 60)).'-'.Str::random(4);
        $siteName = config('app.name', 'Website');

        $locale = app()->getLocale();
        $isGerman = $locale === 'de';

        // SEO-optimized title variations (juridisch verantwoord + overtuigend)
        if ($isGerman) {
            $titleVariations = [
                "{$product->title} Test - Ausführlicher Testbericht",
                "{$product->title} Test & Erfahrungen",
                "{$product->title} - Ehrlicher Test",
                "Test {$product->title} - Vor- und Nachteile",
                "{$product->title} Getestet - Unser Testbericht",
            ];
            $metaTitleTemplate = "{$product->title} Test - Testbericht & Erfahrungen";
            $metaDescriptionTemplate = "Ehrlicher Test des {$product->title}. Entdecke die Vor- und Nachteile aus unserem Praxistest und lies unsere Erfahrungen.";
        } else {
            $titleVariations = [
                "{$product->title} Review - Uitgebreide Test",
                "{$product->title} Review & Ervaringen",
                "{$product->title} - Eerlijke Review",
                "Review {$product->title} - Voor- en Nadelen",
                "{$product->title} Getest - Onze Review",
            ];
            $metaTitleTemplate = "{$product->title} Review - Test & Ervaringen";
            $metaDescriptionTemplate = "Eerlijke review van de {$product->title}. Ontdek de voor- en nadelen uit onze praktijktest en lees onze ervaringen.";
        }

        $seoTitle = Str::limit($titleVariations[array_rand($titleVariations)], 120, '');
        $metaTitle = Str::limit($metaTitleTemplate, 60, '');
        $metaDescription = Str::limit($metaDescriptionTemplate, 155, '');

        try {
            Review::create([
                'product_id' => $product->id,
                'team_member_id' => $this->getRandomTeamMemberId(),
                'title' => $seoTitle,
                'slug' => $slug,
                'content' => $content, // This is now JSON v3 format
                'intro' => null, // Leave null for v3 - content is in JSON
                'experience' => null,
                'positives' => null,
                'conclusion' => null,
                'image_url' => $product->image_url,
                'rating' => $rating,
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
                'excerpt' => Str::limit("Review van {$product->title}", 200),
                'status' => 'published',
            ]);

            $this->info('✅ Review opgeslagen!');
            Log::info("✅ Review succesvol opgeslagen voor product: {$product->title}");
        } catch (\Exception $e) {
            $this->error('❌ Fout bij opslaan review: '.$e->getMessage());
            Log::error('❌ Fout bij opslaan review: '.$e->getMessage());
        }
    }

    protected static function matchFirstTag(string $html, string $tag): ?string
    {
        if (preg_match("/<{$tag}[^>]*>(.*?)<\/{$tag}>/si", $html, $matches)) {
            return trim($matches[0]);
        }

        return null;
    }

    protected static function matchLastTag(string $html, string $tag): ?string
    {
        if (preg_match_all("/<{$tag}[^>]*>(.*?)<\/{$tag}>/si", $html, $matches)) {
            return trim(end($matches[0]));
        }

        return null;
    }

    protected static function matchNthSection(string $html, int $index): ?string
    {
        if (preg_match_all('/<section[^>]*>.*?<\/section>/si', $html, $matches)) {
            return $matches[0][$index - 1] ?? null;
        }

        return null;
    }

    /**
     * Get a random team member ID for review authorship
     * Returns null if no team members exist yet
     */
    private function getRandomTeamMemberId(): ?int
    {
        $teamMember = TeamMember::inRandomOrder()->first();
        return $teamMember?->id;
    }
}
