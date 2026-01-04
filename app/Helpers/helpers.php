<?php

use App\Models\ContentBlock;
use App\Models\PageImage;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

if (! function_exists('getSetting')) {
    function getSetting(string $key, $default = null): ?string
    {
        return Cache::remember("setting_{$key}", 3600, function () use ($key) {
            return Setting::where('key', $key)->value('value');
        }) ?? $default;
    }
}

if (! function_exists('getImage')) {
    function getImage(string $key, string $type = 'header'): string
    {
        return PageImage::where('key', $key)
            ->where('type', $type)
            ->value('path') ?? '/images/headers/fallback.jpg';
    }
}


if (! function_exists('getBolAffiliateLink')) {
    /**
     * TEMPORARY: Bol.com affiliate link for demo site
     * Will be replaced by getAmazonDeAffiliateLink() after Amazon approval
     */
    function getBolAffiliateLink(string $productUrl, string $productTitle): string
    {
        $siteId = config('bol.site_id', 'fallback_id');

        return 'https://partner.bol.com/click/click?p=2&t=url&s='.$siteId.'&f=TXL&url='.urlencode($productUrl).'&name='.urlencode($productTitle);
    }
}

if (! function_exists('getAmazonDeAffiliateLink')) {
    /**
     * Generate Amazon.de PartnerNet affiliate link
     *
     * @param string $asin Amazon Standard Identification Number
     * @param string $productTitle Product title for tracking
     * @return string Affiliate link for Amazon.de
     */
    function getAmazonDeAffiliateLink(string $asin, string $productTitle = ''): string
    {
        $amazonTag = config('amazon.de.associate_tag', 'your-tag-21');

        // Amazon.de product URL with affiliate tag
        // Format: https://www.amazon.de/dp/{ASIN}?tag={your-tag}
        return sprintf(
            'https://www.amazon.de/dp/%s?tag=%s',
            $asin,
            $amazonTag
        );
    }
}

if (! function_exists('getProductAffiliateLink')) {
    /**
     * Get the affiliate link for a product
     * Prioritizes the amazon_affiliate_link from database, falls back to Bol.com
     *
     * @param \App\Models\Product $product
     * @return string Affiliate link
     */
    function getProductAffiliateLink(\App\Models\Product $product): string
    {
        // If product has Amazon affiliate link in database, use that
        if (!empty($product->amazon_affiliate_link)) {
            return $product->amazon_affiliate_link;
        }

        // Fallback to Bol.com (temporary for demo)
        if (!empty($product->product_url)) {
            return getBolAffiliateLink($product->product_url, $product->title);
        }

        // Last resort: generate Amazon link from ASIN
        if (!empty($product->asin)) {
            return getAmazonDeAffiliateLink($product->asin, $product->title);
        }

        // No link available
        return '#';
    }
}

if (! function_exists('formatPrice')) {
    /**
     * Format price in German style: € 1.234,56 (with space after €)
     *
     * @param float|null $price
     * @return string Formatted price with German number format
     */
    function formatPrice(?float $price): string
    {
        return '€ ' . number_format($price ?? 0, 2, ',', '.');
    }
}

if (! function_exists('linkProductMentions')) {
    /**
     * Automatically link product mentions in blog text to product pages
     *
     * @param string $text The text to parse for product mentions
     * @return string HTML with product links
     */
    function linkProductMentions(string $text): string
    {
        if (empty($text)) {
            return $text;
        }

        // Cache products for 1 hour to avoid repeated queries
        $products = Cache::remember('all_products_for_linking', 3600, function () {
            return \App\Models\Product::select('id', 'title', 'slug', 'brand', 'price', 'rating_average', 'image_url')
                ->whereNotNull('slug')
                ->get();
        });

        // Build array of search patterns (longest first to avoid partial matches)
        $replacements = [];

        foreach ($products as $product) {
            // Match exact product title or brand + model patterns
            // Examples: "Ninja Foodi AF500EUWH", "Philips Airfryer 5000 Serie"

            $patterns = [];

            // Full title match (only if > 15 chars to avoid generic matches)
            if (strlen($product->title) > 15) {
                $patterns[] = [
                    'search' => $product->title,
                    'product' => $product,
                    'length' => strlen($product->title)
                ];
            }

            // Brand + model number pattern (e.g., "Ninja Foodi AF500EUWH")
            if ($product->brand && preg_match('/^.+?\s+([A-Z0-9]{5,})/i', $product->title, $matches)) {
                $shortName = $product->brand . ' ' . $matches[1];
                $patterns[] = [
                    'search' => $shortName,
                    'product' => $product,
                    'length' => strlen($shortName)
                ];
            }

            foreach ($patterns as $pattern) {
                $replacements[] = $pattern;
            }
        }

        // Sort by length descending to match longest patterns first (avoid partial matches)
        usort($replacements, function($a, $b) {
            return $b['length'] - $a['length'];
        });

        // Apply replacements (simple str_replace, first occurrence only)
        foreach ($replacements as $replacement) {
            // Check if pattern exists in text (case insensitive)
            $pos = stripos($text, $replacement['search']);

            if ($pos !== false) {
                $product = $replacement['product'];

                // Extract the actual matched text (preserve case)
                $matched = substr($text, $pos, strlen($replacement['search']));

                // Build data attributes for hover preview
                $dataAttrs = sprintf(
                    'data-product-preview=\'%s\'',
                    htmlspecialchars(json_encode([
                        'title' => $product->title,
                        'price' => $product->price,
                        'rating' => $product->rating_average,
                        'image' => $product->image_url,
                        'slug' => $product->slug
                    ]), ENT_QUOTES, 'UTF-8')
                );

                // Replace only first occurrence with preview-enabled link
                $link = sprintf(
                    '<a href="%s" class="product-link text-blue-600 hover:text-blue-800 underline font-medium relative" %s>%s</a>',
                    route('produkte.show', $product->slug),
                    $dataAttrs,
                    $matched
                );

                $text = substr_replace($text, $link, $pos, strlen($replacement['search']));

                // Only link once per text to avoid over-linking
                break;
            }
        }

        return $text;
    }
}

