<?php

namespace App\Services;

use App\Models\Product;
use App\Models\BlogPost;
use App\Models\Review;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class InternalLinkingService
{
    /**
     * Vind relevante interne links voor een keyword/topic
     */
    public function findRelevantLinks(string $keyword, string $niche): array
    {
        $links = [];
        
        // 1. Zoek gerelateerde producten
        $products = $this->findRelatedProducts($keyword, $niche);
        foreach ($products->take(3) as $product) {
            $links[] = [
                'type' => 'product',
                'url' => route('producten.show', $product->slug),
                'anchor_text' => $product->title,
                'context' => 'product_mention',
            ];
        }

        // 2. Zoek gerelateerde blogs
        $blogs = $this->findRelatedBlogs($keyword);
        foreach ($blogs->take(2) as $blog) {
            $links[] = [
                'type' => 'blog',
                'url' => route('blogs.show', $blog->slug),
                'anchor_text' => Str::limit($blog->title, 50),
                'context' => 'related_content',
            ];
        }

        // 3. Zoek gerelateerde reviews
        $reviews = $this->findRelatedReviews($keyword);
        foreach ($reviews->take(2) as $review) {
            $links[] = [
                'type' => 'review',
                'url' => route('reviews.show', $review->slug),
                'anchor_text' => "Review: " . Str::limit($review->title, 40),
                'context' => 'expert_review',
            ];
        }

        // Hub-links (/producten, /top-5) worden NIET meer gegenereerd
        // Deze komen alleen via hardcoded CTA-knoppen in templates
        // Inline links mogen alleen naar content (product detail, blog, review)

        return $links; // Alleen product/blog/review links, geen hubs
    }

    /**
     * Genereer natural anchor texts voor links binnen content
     */
    public function generateNaturalAnchors(string $keyword, array $links): array
    {
        $naturalAnchors = [];
        
        foreach ($links as $link) {
            switch ($link['context']) {
                case 'product_mention':
                    $naturalAnchors[] = [
                        'url' => $link['url'],
                        'phrases' => [
                            "bekijk de {$link['anchor_text']}",
                            "meer over de {$link['anchor_text']}",
                            "specificaties van de {$link['anchor_text']}",
                        ],
                    ];
                    break;

                case 'expert_review':
                    $naturalAnchors[] = [
                        'url' => $link['url'],
                        'phrases' => [
                            "lees onze uitgebreide review",
                            "bekijk wat experts ervan vinden",
                            "diepgaande analyse en testresultaten",
                        ],
                    ];
                    break;

                // Hub-links (category_overview, expert_selection) worden niet meer gegenereerd
                // Deze contexts komen niet meer voor sinds hub-links verwijderd zijn
            }
        }

        return $naturalAnchors;
    }

    /**
     * Creëer context-aware link suggestions voor AI prompts
     */
    public function createLinkContext(string $keyword, string $niche): string
    {
        $hasProductIntent = $this->hasProductIntent($keyword);
        $links = $this->findRelevantLinks($keyword, $niche);

        if (empty($links)) {
            return $hasProductIntent
                ? "Focus op product aanbevelingen. Vermeld producten, maar plaats GEEN links in lopende tekst."
                : "Schrijf informatief. Plaats GEEN links in lopende tekst.";
        }

        $context = $hasProductIntent
            ? "BELANGRIJK: Dit keyword heeft product-intent. Gebruik de onderstaande producten actief in je content:\n\n"
            : "Beschikbare gerelateerde content (NIET linken in tekst, alleen ter referentie):\n";

        foreach ($links as $link) {
            // Extract URL key from route URL for AI to use
            $urlKey = '';
            if (str_contains($link['url'], '/producten/')) {
                $urlKey = 'producten/' . basename($link['url']);
            } elseif (str_contains($link['url'], '/blogs/')) {
                $urlKey = 'blogs/' . basename($link['url']);
            } elseif (str_contains($link['url'], '/reviews/')) {
                $urlKey = 'reviews/' . basename($link['url']);
            }

            switch ($link['type']) {
                case 'product':
                    if ($hasProductIntent) {
                        $context .= "- BESPREEK (NIET LINKEN): \"{$link['anchor_text']}\" - schrijf hier concreet over\n";
                    } else {
                        $context .= "- Product: \"{$link['anchor_text']}\" (alleen ter referentie)\n";
                    }
                    break;
                case 'review':
                    $context .= "- Review: \"{$link['anchor_text']}\" (alleen ter referentie)\n";
                    break;
                case 'blog':
                    $context .= "- Gerelateerd artikel: \"{$link['anchor_text']}\" (alleen ter referentie)\n";
                    break;
            }
        }

        $context .= "\n🚫 BELANGRIJK: Plaats GEEN links in lopende tekst.\n";
        $context .= "Links komen alleen in afsluitende CTA-secties (die worden automatisch toegevoegd).\n";

        return $context;
    }

    /**
     * Bepaal of een keyword product-intent heeft
     */
    private function hasProductIntent(string $keyword): bool
    {
        $productTriggers = [
            'beste', 'top', 'aanbeveling', 'review', 'test', 
            'kopen', 'koop', 'aanbieding', 'vergelijk'
        ];

        $lowerKeyword = strtolower($keyword);
        
        foreach ($productTriggers as $trigger) {
            if (str_contains($lowerKeyword, $trigger)) {
                return true;
            }
        }
        
        return false;
    }

    // === PRIVATE HELPER METHODS ===

    private function findRelatedProducts(string $keyword, string $niche): Collection
    {
        $searchTerms = $this->extractSearchTerms($keyword);
        
        return Product::where(function ($query) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    $query->orWhere('title', 'like', "%{$term}%")
                          ->orWhere('description', 'like', "%{$term}%")
                          ->orWhere('brand', 'like', "%{$term}%");
                }
            })
            ->where('rating_average', '>=', 4.0) // Alleen goed beoordeelde producten
            ->orderByDesc('rating_average')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();
    }

    private function findRelatedBlogs(string $keyword): Collection
    {
        $searchTerms = $this->extractSearchTerms($keyword);
        
        return BlogPost::where('status', 'published')
            ->where('created_at', '<', now()->subMinutes(5)) // Avoid self-referencing recent GSC blogs
            ->where(function ($query) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    $query->orWhere('title', 'like', "%{$term}%")
                          ->orWhere('excerpt', 'like', "%{$term}%");
                }
            })
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();
    }

    private function findRelatedReviews(string $keyword): Collection
    {
        $searchTerms = $this->extractSearchTerms($keyword);
        
        return Review::where('status', 'published')
            ->where(function ($query) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    $query->orWhere('title', 'like', "%{$term}%")
                          ->orWhere('excerpt', 'like', "%{$term}%");
                }
            })
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();
    }

    private function extractSearchTerms(string $keyword): array
    {
        // Extraheer betekenisvolle termen uit keyword
        $terms = explode(' ', strtolower($keyword));
        
        // Filter stopwoorden en korte woorden
        $stopWords = ['de', 'het', 'een', 'voor', 'van', 'met', 'op', 'in', 'aan', 'bij', 'beste', 'goede'];
        
        return array_filter($terms, function ($term) use ($stopWords) {
            return strlen($term) >= 3 && !in_array($term, $stopWords);
        });
    }
}