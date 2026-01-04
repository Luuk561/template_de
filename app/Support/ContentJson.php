<?php

namespace App\Support;

class ContentJson
{
    /**
     * Decode JSON content with error recovery
     */
    public static function decode(?string $raw): array
    {
        if (!is_string($raw)) return [];
        
        $trim = ltrim($raw);
        if ($trim === '' || $trim[0] !== '{') return [];
        
        // Try direct decode first
        $data = json_decode($trim, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }

        // Simple repair attempts
        $fixed = preg_replace('/,(\s*[}\]])/', '$1', $trim); // trailing commas
        $fixed = preg_replace('/[\x00-\x1F\x7F]/', ' ', $fixed); // control chars
        $fixed = preg_replace('/([^"\\\\])\n/', '$1\\n', $fixed); // unescaped newlines
        
        $data = json_decode($fixed, true);
        return (json_last_error() === JSON_ERROR_NONE && is_array($data)) ? $data : [];
    }

    /**
     * Check if decoded data is V2 format
     */
    public static function isV2(array $data, string $expectedPrefix): bool
    {
        return isset($data['version']) && str_starts_with($data['version'], $expectedPrefix);
    }

    /**
     * Check if decoded data is V3 format
     */
    public static function isV3(array $data, string $expectedPrefix): bool
    {
        return isset($data['version']) && str_starts_with($data['version'], $expectedPrefix);
    }

    /**
     * Get safe string value from JSON data
     */
    public static function getString(array $data, string $key, string $default = ''): string
    {
        return isset($data[$key]) && is_string($data[$key]) ? $data[$key] : $default;
    }

    /**
     * Get safe array value from JSON data
     */
    public static function getArray(array $data, string $key): array
    {
        return isset($data[$key]) && is_array($data[$key]) ? $data[$key] : [];
    }

    /**
     * Map internal URL key to actual route
     */
    public static function mapInternalUrl(string $urlKey): string
    {
        return match($urlKey) {
            'producten.index', 'produkte.index' => route('produkte.index'),
            'blogs.index', 'ratgeber.index' => route('ratgeber.index'),
            'reviews.index', 'testberichte.index' => route('testberichte.index'),
            'top5' => url('/top-5'),
            default => '#'
        };
    }

    /**
     * Create fallback V2 structure when JSON is invalid
     */
    public static function createFallback(string $type): array
    {
        return [
            'version' => $type === 'blog' ? 'blog.v2' : 'review.v2',
            'locale' => 'nl-NL',
            'sections' => [],
            'verdict' => [
                'headline' => '',
                'body' => ''
            ]
        ];
    }
}