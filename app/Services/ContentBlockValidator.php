<?php

namespace App\Services;

class ContentBlockValidator
{
    /**
     * Validate generated content against block definition rules
     */
    public function validate(array $content, array $blockDef): ValidationResult
    {
        $errors = [];
        $warnings = [];

        // 1. Check forbidden words/patterns
        $forbiddenPatterns = $this->getForbiddenPatterns();
        foreach ($forbiddenPatterns as $pattern) {
            if ($this->containsPattern($content, $pattern)) {
                $errors[] = "Verboden woord gevonden: '{$pattern}'";
            }
        }

        // 2. Check cijfer-spam (max numerieke claims)
        $cijferCount = $this->countNumericClaims($content);
        $maxCijfers = $blockDef['max_cijfers'] ?? 2;
        if ($cijferCount > $maxCijfers) {
            $errors[] = "Te veel cijfers: {$cijferCount} gevonden (max {$maxCijfers} toegestaan)";
        }

        // 3. Check menukaart-taal
        if ($this->containsMenukaartTaal($content)) {
            $errors[] = "Menukaart-taal gevonden (\"of blogs\", \"of reviews\", \"of top-5\")";
        }

        // 4. Check CTA regel
        $ctaRule = $blockDef['cta_rule'] ?? 'forbidden';
        $ctaTarget = $blockDef['cta_target'] ?? 'none';

        if ($ctaRule === 'required') {
            if (!$this->hasCTA($content, $ctaTarget)) {
                $errors[] = "Verplichte CTA naar {$ctaTarget} ontbreekt";
            }
        } elseif ($ctaRule === 'forbidden') {
            if ($this->hasCTA($content, 'any')) {
                $warnings[] = "CTA gevonden terwijl dit verboden is in dit block";
            }
        }

        // 5. Check length constraints (warnings only)
        if (isset($blockDef['lengths'])) {
            foreach ($blockDef['lengths'] as $unit => $range) {
                if (isset($content[$unit]) && !$this->isWithinRange($content[$unit], $range)) {
                    $warnings[] = "{$unit} voldoet niet aan lengte constraint: {$range}";
                }
            }
        }

        // 6. Check for missing source attribution in claims
        if ($this->hasUnattributedClaims($content)) {
            $errors[] = "Claims met cijfers zonder bronvermelding gevonden (ontbreekt: 'volgens fabrikant', 'gebruikers melden', etc.)";
        }

        return new ValidationResult($errors, $warnings);
    }

    /**
     * Get list of forbidden patterns that should never appear
     */
    private function getForbiddenPatterns(): array
    {
        return [
            // Test claims (E-E-A-T violations)
            'wij testen',
            'onze tests',
            'testcriteria',
            'laboratorium',
            'meetresultaten',
            'grondig getest',
            'uitgebreid getest',

            // Corporate jargon - ALLEEN de ergste combinaties
            'onafhankelijke vergelijking',
            'volledig onafhankelijk',
            'weloverwogen keuze',
            'betrouwbare inzichten',
            'gedetailleerde informatie',

            // Verkoop-framing - alleen extreme gevallen (rest is suggestie in prompt)
            'slimmer kiezen',
            'binnen enkele klikken',

            // Menukaart-taal
            'bekijk ook',
            'of blogs',
            'of reviews',
            'of top-5',
            'of top 5',
            'of ontdek',
            'of lees',
        ];
    }

    /**
     * Check if content contains a forbidden pattern
     */
    private function containsPattern(array $content, string $pattern): bool
    {
        $text = strtolower(implode(' ', $content));

        // For single words, check word boundaries to avoid false positives
        if (!str_contains($pattern, ' ')) {
            return preg_match('/\b' . preg_quote(strtolower($pattern), '/') . '\b/u', $text) === 1;
        }

        return str_contains($text, strtolower($pattern));
    }

    /**
     * Count numeric claims in content (percentages, kWh, etc.)
     */
    private function countNumericClaims(array $content): int
    {
        $text = implode(' ', $content);

        // Match: "X%", "X-Y%", "X kWh", "X watt", "X liter", "X cm", etc.
        preg_match_all('/\d+(?:-\d+)?(?:%|kWh|watt|liter|cm|euro|€)/', $text, $matches);

        return count($matches[0]);
    }

    /**
     * Check for menukaart-taal (multiple page options presented)
     */
    private function containsMenukaartTaal(array $content): bool
    {
        $text = strtolower(implode(' ', $content));

        $menuPatterns = [
            'of blogs',
            'of reviews',
            'of top-5',
            'of top 5',
            'bekijk ook onze blogs',
            'ga naar reviews',
            'kies uit top',
        ];

        foreach ($menuPatterns as $pattern) {
            if (str_contains($text, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if content has a CTA to target
     */
    private function hasCTA(array $content, string $target): bool
    {
        $text = implode(' ', $content);

        if ($target === 'any') {
            // Check for any CTA patterns
            $ctaPatterns = ['bekijk', 'ontdek', 'ga naar', 'klik hier', 'lees meer'];
            foreach ($ctaPatterns as $pattern) {
                if (stripos($text, $pattern) !== false) {
                    return true;
                }
            }
            return false;
        }

        // Check if target is mentioned in content
        return str_contains(strtolower($text), strtolower($target));
    }

    /**
     * Check if text length is within specified range
     */
    private function isWithinRange(string $text, string $range): bool
    {
        $length = strlen($text);

        // Parse range like "40-60 tekens" or "50-70 woorden"
        if (preg_match('/(\d+)-(\d+)\s*(tekens|woorden)/', $range, $matches)) {
            $min = (int)$matches[1];
            $max = (int)$matches[2];
            $unit = $matches[3];

            if ($unit === 'woorden') {
                $wordCount = str_word_count($text);
                return $wordCount >= $min && $wordCount <= $max;
            } else {
                // tekens (characters)
                return $length >= $min && $length <= $max;
            }
        }

        return true; // Can't parse range, assume valid
    }

    /**
     * Check for claims with numbers but no source attribution
     */
    private function hasUnattributedClaims(array $content): bool
    {
        $text = implode(' ', $content);

        // Find sentences with numbers
        preg_match_all('/[^.!?]*\d+(?:-\d+)?(?:%|kWh|watt|liter|cm|euro|€)[^.!?]*[.!?]/', $text, $matches);

        // If no numeric claims found, no problem
        if (empty($matches[0])) {
            return false;
        }

        $sourcePatterns = [
            'volgens fabrikant',
            'fabrikanten claimen',
            'gebruikers melden',
            'specs tonen',
            'specificaties',
            'gemiddeld',      // Implies calculation/aggregation
            'meestal',        // Implies observation
            'vaak',           // Implies observation
            'varieert',       // Adds nuance, acceptable
        ];

        $unattributedCount = 0;

        foreach ($matches[0] as $sentence) {
            $hasSource = false;
            foreach ($sourcePatterns as $pattern) {
                if (stripos($sentence, $pattern) !== false) {
                    $hasSource = true;
                    break;
                }
            }

            // If sentence has number but no source, count it
            if (!$hasSource) {
                $unattributedCount++;
            }
        }

        // Allow 1 unattributed claim (for FAQ answers with basic facts)
        // But flag if there are 2+ unattributed claims
        return $unattributedCount >= 2;
    }
}

/**
 * Simple DTO for validation results
 */
class ValidationResult
{
    public bool $isValid;
    public array $errors;
    public array $warnings;

    public function __construct(array $errors, array $warnings)
    {
        $this->errors = $errors;
        $this->warnings = $warnings;
        $this->isValid = empty($errors);
    }
}
