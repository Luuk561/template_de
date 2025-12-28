<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property array $content_outline
 * @property array $variables
 */
class ProductBlogTemplate extends Model
{
    protected $fillable = [
        'niche',
        'title_template',
        'slug_template',
        'seo_focus_keyword',
        'content_outline',
        'target_word_count',
        'tone',
        'scenario_focus',
        'cta_type',
        'variables',
        'min_days_between_reuse',
        'last_used_at',
    ];

    protected $casts = [
        'content_outline' => 'array',
        'variables' => 'array',
        'last_used_at' => 'datetime',
    ];

    /**
     * Pick a template for the given niche, preferring unused or old templates
     */
    public static function pickTemplate(string $niche): ?self
    {
        $cutoffDate = now()->subDays(60);

        // Try unused or old templates first (prioritize diversity)
        $template = self::where('niche', $niche)
            ->where(function($query) use ($cutoffDate) {
                $query->whereNull('last_used_at')
                      ->orWhere('last_used_at', '<', $cutoffDate);
            })
            ->orderBy('last_used_at', 'asc')
            ->orderByRaw('RAND()')
            ->first();

        // Fallback: allow recent templates if no old ones available
        if (!$template) {
            $template = self::where('niche', $niche)
                ->orderBy('last_used_at', 'asc')
                ->orderByRaw('RAND()')
                ->first();
        }

        return $template;
    }

    /**
     * Instantiate template with random variable picks
     * Returns array with instantiated title, slug, seo_keyword
     */
    public function instantiate(string $productName): array
    {
        $instantiated = [
            'title' => $this->title_template,
            'slug' => $this->slug_template,
            'seo_keyword' => $this->seo_focus_keyword,
        ];

        // Replace {product} with actual product name
        $instantiated['title'] = str_replace('{product}', $productName, $instantiated['title']);
        $instantiated['slug'] = str_replace('{product}', \Illuminate\Support\Str::slug($productName), $instantiated['slug']);
        $instantiated['seo_keyword'] = str_replace('{product}', $productName, $instantiated['seo_keyword']);

        // Replace other variables with random picks from options
        foreach ($this->variables as $varName => $options) {
            if (is_array($options) && !empty($options)) {
                $value = $options[array_rand($options)];
            } else {
                $value = $options;
            }

            $instantiated['title'] = str_replace('{' . $varName . '}', $value, $instantiated['title']);
            $instantiated['slug'] = str_replace('{' . $varName . '}', \Illuminate\Support\Str::slug($value), $instantiated['slug']);
            $instantiated['seo_keyword'] = str_replace('{' . $varName . '}', $value, $instantiated['seo_keyword']);
        }

        // Add year
        $instantiated['title'] = str_replace('{year}', now()->year, $instantiated['title']);
        $instantiated['slug'] = str_replace('{year}', now()->year, $instantiated['slug']);
        $instantiated['seo_keyword'] = str_replace('{year}', now()->year, $instantiated['seo_keyword']);

        return $instantiated;
    }

    /**
     * Mark this template as used
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }
}
