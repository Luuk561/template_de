<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class SearchConsoleData extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_url',
        'query',
        'date',
        'clicks',
        'impressions',
        'ctr',
        'position',
        'page',
        'country',
        'device',
        'status',
        'metadata',
    ];

    protected $casts = [
        'date' => 'date',
        'clicks' => 'integer',
        'impressions' => 'integer',
        'ctr' => 'decimal:6',
        'position' => 'decimal:2',
        'metadata' => 'json',
    ];

    /**
     * Maak SearchConsoleData record aan op basis van Google Search Console API response
     */
    public static function createFromGscData($siteUrl, $date, $query, $gscRow)
    {
        // Extract data from GSC response
        $clicks = $gscRow->getClicks() ?? 0;
        $impressions = $gscRow->getImpressions() ?? 0;
        $ctr = $gscRow->getCtr() ?? 0;
        $position = $gscRow->getPosition() ?? 0;

        // Voor nu geen page-specific data (kan later worden toegevoegd)
        $page = null;

        return self::updateOrCreate([
            'site_url' => $siteUrl,
            'query' => $query,
            'date' => $date,
            'page' => $page,
        ], [
            'clicks' => $clicks,
            'impressions' => $impressions,
            'ctr' => $ctr,
            'position' => $position,
            'country' => 'NL', // Default, kan later dynamisch worden
            'device' => 'desktop', // Default, kan later worden uitgebreid
            'status' => 'active',
        ]);
    }

    /**
     * Scopes voor filtering
     */
    public function scopeForSite($query, $siteUrl)
    {
        return $query->where('site_url', $siteUrl);
    }

    public function scopeForDateRange($query, $startDate, $endDate = null)
    {
        if (!$endDate) {
            $endDate = Carbon::today();
        }
        
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeWithMinimumImpressions($query, $minImpressions = 10)
    {
        return $query->where('impressions', '>=', $minImpressions);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Helper methods voor AI content generation
     */
    public function isHighPotential()
    {
        // High impressions but low clicks = opportunity
        return $this->impressions >= 50 && $this->clicks < 5 && $this->position > 10;
    }

    public function isHighPerforming()
    {
        // Good position with decent traffic
        return $this->position <= 5 && $this->clicks >= 5;
    }

    public function getOpportunityScore()
    {
        // Simple scoring algorithm for content prioritization
        $impressionScore = min($this->impressions / 100, 10); // Max 10 points
        $positionScore = max(0, (20 - $this->position) / 2); // Better position = higher score
        $ctrScore = $this->ctr * 100; // CTR as percentage

        return round($impressionScore + $positionScore + $ctrScore, 2);
    }
}