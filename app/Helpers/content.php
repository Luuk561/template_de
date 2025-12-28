<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

if (! function_exists('getContent')) {
    function getContent(string $key, array $replacements = []): string
    {
        $content = Cache::rememberForever("content_block_{$key}", function () use ($key) {
            return DB::table('content_blocks')->where('key', $key)->value('content') ?? '';
        });

        // Als content leeg is én er is een fallback opgegeven, gebruik dan de fallback
        if (empty($content) && isset($replacements['fallback'])) {
            return $replacements['fallback'];
        }

        // Vervang placeholders in de content
        foreach ($replacements as $placeholder => $value) {
            if ($placeholder === 'fallback') {
                continue;
            } // sla 'fallback' over als placeholder
            $content = str_replace('{{ '.$placeholder.' }}', $value, $content);
        }

        // HYBRID MODE SUPPORT: Return plain text as-is for structured content
        // Blade templates will handle the HTML wrapping and styling
        // This prevents double-wrapping (plain text → prose div → template prose div)

        // Only add HTML processing for OLD-STYLE full HTML blocks
        // (those that already have <div>, <section>, or <article> tags)
        $hasHtmlWrapper = str_contains($content, '<div') || str_contains($content, '<section') || str_contains($content, '<article');

        if ($hasHtmlWrapper) {
            // Old HTML mode - return as-is
            return $content;
        }

        // New structured mode - return plain text
        // Let Blade templates handle the HTML structure
        return $content;
    }
}

if (! function_exists('hasStructuredContent')) {
    /**
     * Check if structured content units exist for a block
     *
     * @param string $baseKey e.g. 'homepage.seo1' or 'homepage.faq_1'
     * @return bool True if structured units (.title, .intro, .question, etc) exist
     */
    function hasStructuredContent(string $baseKey): bool
    {
        // Check if .title OR .question exists - if structured mode was used, one of these should exist
        $titleKey = $baseKey . '.title';
        $questionKey = $baseKey . '.question';

        $hasTitle = Cache::rememberForever("content_block_exists_{$titleKey}", function () use ($titleKey) {
            return DB::table('content_blocks')->where('key', $titleKey)->exists();
        });

        $hasQuestion = Cache::rememberForever("content_block_exists_{$questionKey}", function () use ($questionKey) {
            return DB::table('content_blocks')->where('key', $questionKey)->exists();
        });

        return $hasTitle || $hasQuestion;
    }
}
