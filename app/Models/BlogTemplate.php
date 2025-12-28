<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogTemplate extends Model
{
    protected $fillable = [
        'niche',
        'title_template',
        'slug_template',
        'seo_focus_keyword',
        'content_outline',
        'target_word_count',
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
     * Pick a random unused template or least recently used
     * Now uses 90-day rotation for maximum variety (70 templates = ~2 years of unique content)
     */
    public static function pickTemplate(string $niche)
    {
        $cutoffDate = now()->subDays(90); // Extended from 60 to 90 days

        // Try to find unused or old templates first
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
     * Generate concrete values from template
     */
    public function instantiate(): array
    {
        $instantiated = [
            'title' => $this->title_template,
            'slug' => $this->slug_template,
            'seo_keyword' => $this->seo_focus_keyword,
        ];

        // FIRST: Replace year with current year (before variables loop!)
        $instantiated['title'] = str_replace('{year}', now()->year, $instantiated['title']);
        $instantiated['slug'] = str_replace('{year}', now()->year, $instantiated['slug']);
        $instantiated['seo_keyword'] = str_replace('{year}', now()->year, $instantiated['seo_keyword']);

        // THEN: Replace other variables with random picks (skip 'year' if present in variables)
        foreach ($this->variables as $varName => $options) {
            if ($varName === 'year') {
                continue; // Skip - already handled above with current year
            }

            $value = is_array($options) ? $options[array_rand($options)] : $options;

            $instantiated['title'] = str_replace('{' . $varName . '}', $value, $instantiated['title']);
            $instantiated['slug'] = str_replace('{' . $varName . '}', $value, $instantiated['slug']);
            $instantiated['seo_keyword'] = str_replace('{' . $varName . '}', $value, $instantiated['seo_keyword']);
        }

        return $instantiated;
    }

    /**
     * Mark template as used
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }
}
