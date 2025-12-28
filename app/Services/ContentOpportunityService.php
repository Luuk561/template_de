<?php

namespace App\Services;

use App\Models\SearchConsoleData;
use App\Models\BlogPost;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ContentOpportunityService
{
    /**
     * Vind high-potential keywords die content opportunities zijn
     * 
     * Criteria voor content opportunities:
     * - Hoge impressions (veel gezocht)
     * - Lage CTR (slechte content match)
     * - Positie 8-20 (op pagina 1 maar slecht)
     * - Nog geen specifieke content voor
     */
    public function findContentOpportunities(string $siteUrl, int $minImpressions = 50, int $maxDays = 30): Collection
    {
        $opportunities = SearchConsoleData::forSite($siteUrl)
            ->forDateRange(Carbon::now()->subDays($maxDays), Carbon::now())
            ->where('impressions', '>=', $minImpressions)
            ->where('position', '>', 7) // Niet al top positie
            ->where('position', '<=', 100) // Ruimere range voor nieuwe sites
            ->where('ctr', '<', 0.05) // Lage CTR = content mismatch
            ->active()
            ->get()
            ->groupBy('query')
            ->map(function ($keywordData, $keyword) {
                // $keyword is de groupBy key, $keywordData is de collection
                if (!is_string($keyword) || empty($keyword)) {
                    return null; // Skip invalid keywords
                }
                
                $totalImpressions = $keywordData->sum('impressions');
                $totalClicks = $keywordData->sum('clicks');
                $avgPosition = $keywordData->avg('position');
                $avgCtr = $totalImpressions > 0 ? ($totalClicks / $totalImpressions) : 0;
                
                return [
                    'keyword' => $keyword,
                    'total_impressions' => $totalImpressions,
                    'total_clicks' => $totalClicks,
                    'avg_position' => round($avgPosition, 1),
                    'avg_ctr' => round($avgCtr, 4),
                    'opportunity_score' => $this->calculateOpportunityScore($totalImpressions, $avgCtr, $avgPosition),
                    'content_exists' => $this->hasExistingContent($keyword),
                    'suggested_theme' => $this->suggestContentTheme($keyword),
                    'keyword_type' => $this->classifyKeywordType($keyword),
                ];
            })
            ->reject(fn($opportunity) => is_null($opportunity) || $opportunity['content_exists']) // Skip null en existing content
            ->sortByDesc('opportunity_score')
            ->values();

        return $opportunities;
    }

    /**
     * Groepeer keywords in natuurlijke content thema's
     * zodat we geen 50 blogs maken over bijna hetzelfde onderwerp
     */
    public function clusterKeywordsIntoThemes(Collection $opportunities, int $maxThemes = 10): Collection
    {
        $themes = collect();
        $processed = collect();

        foreach ($opportunities as $opportunity) {
            $keyword = $opportunity['keyword'];
            
            if ($processed->contains($keyword)) {
                continue;
            }

            // Vind gerelateerde keywords voor dit thema
            $relatedKeywords = $this->findRelatedKeywords($keyword, $opportunities);
            
            // Maak een thema aan
            $theme = [
                'primary_keyword' => $keyword,
                'theme_name' => $this->generateThemeName($keyword, $relatedKeywords),
                'related_keywords' => $relatedKeywords->pluck('keyword')->take(8)->toArray(),
                'total_impressions' => $relatedKeywords->sum('total_impressions'),
                'avg_opportunity_score' => $relatedKeywords->avg('opportunity_score'),
                'content_type' => $this->determineContentType($keyword, $relatedKeywords),
                'suggested_angle' => $this->suggestContentAngle($keyword, $relatedKeywords),
            ];

            $themes->push($theme);
            $processed = $processed->merge($relatedKeywords->pluck('keyword'));

            if ($themes->count() >= $maxThemes) {
                break;
            }
        }

        return $themes->sortByDesc('total_impressions')->values();
    }

    /**
     * Genereer AI prompts voor natuurlijke content gebaseerd op keyword thema's
     */
    public function generateContentPrompts(Collection $themes): Collection
    {
        return $themes->map(function ($theme) {
            $niche = getSetting('site_niche', 'Premium Products');
            $targetAudience = getSetting('target_audience', 'Nederlandse consumenten');
            
            // Maak een natuurlijke blog prompt
            $prompt = $this->createBlogPrompt($theme, $niche, $targetAudience);
            
            return [
                'theme' => $theme,
                'blog_title' => $this->suggestBlogTitle($theme),
                'content_prompt' => $prompt,
                'target_keywords' => $theme['related_keywords'],
                'content_type' => $theme['content_type'],
                'priority_score' => $theme['avg_opportunity_score'],
            ];
        });
    }

    // === PRIVATE HELPER METHODS ===

    private function calculateOpportunityScore(int $impressions, float $ctr, float $position): float
    {
        // Meer impressions = meer potentie
        $impressionScore = min(log($impressions) * 2, 20);
        
        // Lagere CTR = grotere opportunity (mensen zoeken maar klikken niet)
        $ctrScore = max(0, (0.05 - $ctr) * 100);
        
        // Positie 8-15 is sweet spot (zichtbaar maar slecht)
        $positionScore = $position >= 8 && $position <= 15 ? 10 : 5;
        
        return round($impressionScore + $ctrScore + $positionScore, 2);
    }

    private function hasExistingContent(string $keyword): bool
    {
        // Check of we al een blog hebben die bijna identiek is aan dit keyword
        // Focus op exacte keyword match of zeer specifieke overlap
        $cleanKeyword = strtolower(trim($keyword));
        
        // Skip als keyword te kort of algemeen is
        if (strlen($cleanKeyword) < 5) {
            return false;
        }
        
        $blogs = BlogPost::where('status', 'published')->get();
        
        foreach ($blogs as $blog) {
            $cleanTitle = strtolower(trim($blog->title));
            
            // Check 1: Exacte keyword match in titel
            if (str_contains($cleanTitle, $cleanKeyword)) {
                // Maar alleen als het een zeer specifieke match is
                $titleLength = strlen($cleanTitle);
                $keywordLength = strlen($cleanKeyword);
                
                // Als keyword >50% van titel uitmaakt = te specifiek overlap
                if (($keywordLength / $titleLength) > 0.5) {
                    return true;
                }
            }
            
            // Check 2: Zeer specifieke long-tail matches
            // Bijvoorbeeld: "beste dubbele airfryer 2024" vs "beste dubbele airfryer"
            $titleWords = explode(' ', $cleanTitle);
            $keywordWords = explode(' ', $cleanKeyword);
            
            if (count($keywordWords) >= 3) {
                $consecutiveMatches = $this->findConsecutiveWordMatches($titleWords, $keywordWords);
                // Als 3+ woorden achter elkaar matchen = waarschijnlijk zelfde intent
                if ($consecutiveMatches >= 3) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    private function findConsecutiveWordMatches(array $titleWords, array $keywordWords): int
    {
        $maxConsecutive = 0;
        
        for ($i = 0; $i <= count($titleWords) - count($keywordWords); $i++) {
            $consecutive = 0;
            
            for ($j = 0; $j < count($keywordWords); $j++) {
                if (isset($titleWords[$i + $j]) && $titleWords[$i + $j] === $keywordWords[$j]) {
                    $consecutive++;
                } else {
                    break;
                }
            }
            
            $maxConsecutive = max($maxConsecutive, $consecutive);
        }
        
        return $maxConsecutive;
    }

    private function suggestContentTheme(string $keyword): string
    {
        $themes = [
            'vergelijk' => 'product_comparison',
            'beste' => 'top_list',
            'test' => 'review_roundup', 
            'koop' => 'buying_guide',
            'verschil' => 'comparison_guide',
            'tips' => 'tips_guide',
            'gebruik' => 'usage_guide',
            'prijs' => 'price_guide',
        ];

        foreach ($themes as $trigger => $theme) {
            if (str_contains(strtolower($keyword), $trigger)) {
                return $theme;
            }
        }

        return 'informational';
    }

    private function classifyKeywordType(string $keyword): string
    {
        $lowerKeyword = strtolower($keyword);

        // Specifieke content types voor betere intent matching
        if (str_contains($lowerKeyword, 'beste') && !str_contains($lowerKeyword, 'vergelijk')) {
            return 'buying_guide_with_examples'; // "beste crosstrainer" → koopgids met voorbeelden
        }
        
        if (str_contains($lowerKeyword, 'review') || str_contains($lowerKeyword, 'test')) {
            return 'review_roundup'; // "crosstrainer review" → review overzicht
        }
        
        if (str_contains($lowerKeyword, 'vergelijk') || str_contains($lowerKeyword, 'versus') || str_contains($lowerKeyword, 'vs')) {
            return 'comparison_guide'; // "crosstrainer vergelijken" → vergelijkingsgids
        }

        // Fallback naar originele classificatie
        $commercial = ['kopen', 'koop', 'aanbieding', 'prijs', 'goedkoop'];
        $informational = ['wat is', 'hoe', 'waarom', 'tips', 'gids'];

        foreach ($commercial as $trigger) {
            if (str_contains($lowerKeyword, $trigger)) return 'commercial';
        }

        foreach ($informational as $trigger) {
            if (str_contains($lowerKeyword, $trigger)) return 'informational';
        }

        return 'general';
    }

    private function findRelatedKeywords(string $primaryKeyword, Collection $opportunities): Collection
    {
        $primaryWords = collect(explode(' ', strtolower($primaryKeyword)));
        
        return $opportunities->filter(function ($opportunity) use ($primaryWords, $primaryKeyword) {
            if ($opportunity['keyword'] === $primaryKeyword) return true;
            
            $keywordWords = collect(explode(' ', strtolower($opportunity['keyword'])));
            
            // Keywords zijn gerelateerd als ze 60%+ woorden delen
            $sharedWords = $primaryWords->intersect($keywordWords)->count();
            $totalUniqueWords = $primaryWords->merge($keywordWords)->unique()->count();
            
            $similarity = $totalUniqueWords > 0 ? ($sharedWords / $totalUniqueWords) : 0;
            
            return $similarity >= 0.4; // 40% overlap
        });
    }

    private function generateThemeName(string $primaryKeyword, Collection $relatedKeywords): string
    {
        // Vind meest voorkomende woorden in de keyword groep
        $allWords = $relatedKeywords->pluck('keyword')
            ->map(fn($k) => explode(' ', strtolower($k)))
            ->flatten()
            ->countBy()
            ->sortDesc();

        $commonWords = $allWords->take(3)->keys()->reject(function ($word) {
            return in_array($word, ['de', 'het', 'een', 'voor', 'van', 'en', 'met']);
        });

        return Str::title($commonWords->take(2)->implode(' ')) . ' Gids';
    }

    private function determineContentType(string $primaryKeyword, Collection $relatedKeywords): string
    {
        $types = $relatedKeywords->pluck('keyword_type')->countBy();
        return $types->sortDesc()->keys()->first() ?? 'informational';
    }

    private function suggestContentAngle(string $primaryKeyword, Collection $relatedKeywords): string
    {
        $keyword = strtolower($primaryKeyword);
        
        // Nieuwe, intent-matching angles
        if (str_contains($keyword, 'beste') && !str_contains($keyword, 'vergelijk')) {
            return 'Complete koopgids met concrete voorbeelden en expert aanbevelingen';
        }
        
        if (str_contains($keyword, 'review') || str_contains($keyword, 'test')) {
            return 'Expert review overzicht met praktijkervaringen';
        }
        
        if (str_contains($keyword, 'vergelijk') || str_contains($keyword, 'versus')) {
            return 'Vergelijkingsgids met duidelijke verschillen en aanbevelingen';
        }
        
        // Fallback naar originele angles
        if (str_contains($keyword, 'koop')) return 'Praktische koopgids met tips';
        if (str_contains($keyword, 'verschil')) return 'Heldere uitleg van verschillen';
        
        return 'Informatieve gids met praktische tips';
    }

    private function createBlogPrompt(array $theme, string $niche, string $targetAudience): string
    {
        $keywords = implode(', ', array_slice($theme['related_keywords'], 0, 5));
        
        return "Schrijf een natuurlijke, waardevolle blog over '{$theme['theme_name']}' " .
               "gericht op {$targetAudience} binnen de {$niche} niche. " .
               "Integreer deze zoekwoorden op natuurlijke wijze: {$keywords}. " .
               "Focus op {$theme['suggested_angle']}. " .
               "Geen keyword stuffing - prioriteit ligt op waardevolle, leesbare content " .
               "die de vragen van zoekers beantwoordt.";
    }

    private function suggestBlogTitle(array $theme): string
    {
        $angle = $theme['suggested_angle'];
        $themeName = $theme['theme_name'];
        
        if (str_contains($angle, 'Top lijst')) {
            return "Beste {$themeName} van " . now()->year . ": Complete Vergelijking";
        }
        
        if (str_contains($angle, 'vergelijking')) {
            return "{$themeName}: Alle Verschillen Op Een Rij";
        }
        
        if (str_contains($angle, 'koopgids')) {
            return "Slimme {$themeName} Kopen: Complete Gids";
        }
        
        return "Alles Over {$themeName}: Complete Gids";
    }
}