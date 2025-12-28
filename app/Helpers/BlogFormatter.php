<?php

namespace App\Helpers;

class BlogFormatter
{
    /**
     * Optioneel: algemene formatter (bijv. HTML opschonen)
     */
    public static function format(string $content): string
    {
        return trim($content);
    }

    /**
     * Haal specifieke contentsectie uit HTML op basis van naam
     */
    public static function extractSection(string $html, string $section): ?string
    {
        $section = strtolower($section);

        return match ($section) {
            'intro' => self::matchFirstTag($html, 'p'),
            'main' => self::matchNthSection($html, 1),              // ⚠️ eerste <section>
            'benefits' => self::matchNthSection($html, 2),              // ⚠️ tweede <section>
            'tips' => self::matchNthSection($html, 3),              // ⚠️ derde <section>
            'closing' => self::matchLastTag($html, 'p'),
            default => null,
        };
    }

    /**
     * Pak eerste <p>...</p> blok
     */
    protected static function matchFirstTag(string $html, string $tag): ?string
    {
        if (preg_match("/<{$tag}[^>]*>(.*?)<\/{$tag}>/si", $html, $matches)) {
            return trim($matches[0]);
        }

        return null;
    }

    /**
     * Pak laatste <p>...</p> blok
     */
    protected static function matchLastTag(string $html, string $tag): ?string
    {
        if (preg_match_all("/<{$tag}[^>]*>(.*?)<\/{$tag}>/si", $html, $matches)) {
            return trim(end($matches[0]));
        }

        return null;
    }

    /**
     * Pak <section> op basis van <h2>Titel</h2>
     */
    protected static function matchSection(string $html, string $title): ?string
    {
        $pattern = '/<section[^>]*>\s*<h2[^>]*>\s*'.preg_quote($title, '/').'\s*<\/h2>(.*?)<\/section>/si';
        if (preg_match($pattern, $html, $matches)) {
            return "<section><h2>{$title}</h2>".trim($matches[1]).'</section>';
        }

        return null;
    }

    /**
     * Pak de nth <section> (1 = eerste, 2 = tweede, ...)
     */
    public static function matchNthSection(string $html, int $index): ?string
    {
        if (preg_match_all('/<section[^>]*>.*?<\/section>/si', $html, $matches)) {
            return $matches[0][$index - 1] ?? null;
        }

        return null;
    }
}
