<?php

namespace App\Console\Commands\Review;

use App\Models\Review;
use App\Models\TeamMember;
use App\Services\OpenAIService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateCustomProductReview extends Command
{
    protected $signature = 'generate:custom-review
                            {--content= : Product content (description, specs, features)}
                            {--product-name= : Product name}
                            {--affiliate= : Affiliate link URL}
                            {--discount-code= : Optional discount code}
                            {--discount-percentage=10 : Discount percentage (default: 10)}
                            {--discount-text= : Optional custom discount text}
                            {--rating= : Product rating (1-5, default: 4.5)}
                            {--brand= : Brand name}
                            {--image= : Product image URL}';

    protected $description = 'Genereer een custom product review (bijv. Moovv) met eigen affiliate link en kortingscode';

    protected OpenAIService $openAI;

    public function __construct(OpenAIService $openAI)
    {
        parent::__construct();
        $this->openAI = $openAI;
    }

    public function handle()
    {
        $content = $this->option('content');
        $productName = $this->option('product-name');
        $affiliateLink = $this->option('affiliate');
        $discountCode = $this->option('discount-code');
        $discountPercentage = (int) $this->option('discount-percentage');
        $discountText = $this->option('discount-text');
        $rating = (float) ($this->option('rating') ?: 4.5);
        $brand = $this->option('brand') ?: 'Moovv';
        $imageUrl = $this->option('image');

        // Validation
        if (!$content || !$productName || !$affiliateLink) {
            $this->error('Verplichte parameters ontbreken!');
            $this->info('Gebruik: php artisan generate:custom-review --content="..." --product-name="..." --affiliate="..."');
            return 1;
        }

        // Validate rating
        if ($rating < 1 || $rating > 5) {
            $this->error('Rating moet tussen 1 en 5 zijn');
            return 1;
        }

        $this->info("Genereren van review voor: {$productName}");
        $this->info('Content lengte: ' . strlen($content) . ' karakters');
        $this->info("Rating: {$rating}/5");
        if ($discountCode) {
            $this->info("Kortingscode: {$discountCode} ({$discountPercentage}%)");
        }

        // Get site settings
        $niche = trim(getSetting('site_niche', 'loopbanden'));
        $toneOfVoice = trim(getSetting('tone_of_voice', 'professioneel en toegankelijk'));
        $targetAudience = trim(getSetting('target_audience', 'Nederlandse consumenten'));
        $siteName = trim(getSetting('site_name', 'loopbandentest.nl'));

        // Build custom discount block if provided
        $discountBlock = '';
        if ($discountCode) {
            if ($discountText) {
                $discountBlock = $discountText;
            } else {
                // Dynamic default text based on site name and niche
                $productType = str_contains(strtolower($niche), 'loopband') ? 'loopband' : 'product';
                $discountBlock = "{$discountPercentage}% korting voor lezers van {$siteName}\nBen je enthousiast geworden over dit {$productType}? We mogen {$discountPercentage}% korting geven. Gebruik hiervoor de code {$discountCode} bij het afrekenen.";
            }
        }

        // Generate review via OpenAI using custom method with neutral tone
        $this->info('OpenAI generatie gestart...');
        $jsonResponse = $this->openAI->generateCustomProductReview(
            $productName,
            $content,
            $brand,
            $niche,
            'gpt-4o-mini'
        );

        // Clean JSON response (remove markdown code blocks if present)
        $jsonResponse = trim($jsonResponse);
        $jsonResponse = preg_replace('/^```json\s*/', '', $jsonResponse);
        $jsonResponse = preg_replace('/\s*```$/', '', $jsonResponse);
        $jsonResponse = trim($jsonResponse);

        // Fix common JSON encoding issues
        $testDecode = json_decode($jsonResponse, true);
        if (json_last_error() === JSON_ERROR_CTRL_CHAR) {
            $jsonResponse = preg_replace_callback('/"([^"]*)"/', function($matches) {
                return '"' . str_replace(["\r\n", "\r", "\n", "\t"], ['\\n', '\\n', '\\n', '\\t'], $matches[1]) . '"';
            }, $jsonResponse);
        }

        // Remove other control chars
        $jsonResponse = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $jsonResponse);
        $jsonResponse = mb_convert_encoding($jsonResponse, 'UTF-8', 'UTF-8');

        // Validate JSON
        $decoded = json_decode($jsonResponse, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($decoded)) {
            $this->error('Generated content is not valid JSON');
            $this->error('JSON Error: ' . json_last_error_msg());

            $debugFile = storage_path('logs/failed_custom_review_' . time() . '.txt');
            file_put_contents($debugFile, $jsonResponse);
            $this->warn('Full response saved to: ' . $debugFile);
            return 1;
        }

        if (empty($decoded['intro']) || empty($decoded['sections'])) {
            $this->error('JSON missing required fields (intro or sections)');
            return 1;
        }

        // Generate SEO title
        $titleVariations = [
            "{$productName} Review - Uitgebreide Test",
            "{$productName} Review & Ervaringen",
            "{$productName} - Eerlijke Review",
            "Review {$productName} - Voor- en Nadelen",
            "{$productName} Getest - Onze Review",
        ];
        $seoTitle = Str::limit($titleVariations[array_rand($titleVariations)], 120, '');

        $this->info("Titel: {$seoTitle}");

        // Check for duplicate titles
        if (Review::where('title', $seoTitle)->exists()) {
            $this->warn("Titel '{$seoTitle}' bestaat al.");
            $seoTitle .= ' ' . date('Y');
        }

        // Generate slug
        $baseSlug = 'review-' . Str::slug(Str::limit($productName, 60));
        $slug = $baseSlug . '-' . Str::random(4);

        while (Review::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . Str::random(4);
        }

        // Generate meta tags
        $metaTitle = Str::limit("{$productName} Review - Test & Ervaringen", 60, '');
        $metaDescription = Str::limit("Eerlijke review van de {$productName}. Ontdek de voor- en nadelen uit onze praktijktest en lees onze ervaringen.", 155, '');

        // Store custom affiliate data in JSON
        $decoded['custom_affiliate'] = [
            'link' => $affiliateLink,
            'product_name' => $productName,
        ];

        if ($discountCode) {
            $decoded['custom_affiliate']['discount_code'] = $discountCode;
            $decoded['custom_affiliate']['discount_percentage'] = $discountPercentage;
            $decoded['custom_affiliate']['discount_block'] = $discountBlock;
        }

        // Save review
        $review = Review::create([
            'product_id' => null, // No bol.com product (custom affiliate instead)
            'team_member_id' => $this->getRandomTeamMemberId(),
            'title' => $seoTitle,
            'slug' => $slug,
            'content' => json_encode($decoded, JSON_UNESCAPED_UNICODE),
            'intro' => null, // v3 format uses JSON
            'experience' => null,
            'positives' => null,
            'conclusion' => null,
            'image_url' => $imageUrl,
            'rating' => $rating,
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'excerpt' => Str::limit("Review van {$productName}", 200),
            'status' => 'published',
        ]);

        $this->info('');
        $this->info('Review succesvol aangemaakt!');
        $this->info("ID: {$review->id}");
        $this->info("Slug: {$review->slug}");
        $this->info("Rating: {$rating}/5");
        $this->info("URL: " . route('reviews.show', $review->slug));
        $this->info("Woorden: " . str_word_count(strip_tags($jsonResponse)));

        return 0;
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
