<?php

namespace App\Services;

/**
 * ReviewGeneratorService
 *
 * Generates product reviews
 */
class ReviewGeneratorService
{
    private OpenAIService $openAI;

    public function __construct(OpenAIService $openAI)
    {
        $this->openAI = $openAI;
    }

    /**
     * Generate a product review
     */
    public function generate($product, string $niche, ?string $uniqueFocus = null): string
    {
        // TODO: Move review generation logic here
        return '';
    }
}
