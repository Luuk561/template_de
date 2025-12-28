<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Review extends Model
{

    protected $fillable = [
        'product_id',
        'team_member_id',
        'title',
        'slug',
        'content',
        'image_url',
        'rating',
        'meta_title',
        'meta_description',
        'excerpt',
        'status',
        'intro',
        'experience',
        'positives',
        'conclusion',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($review) {
            if (empty($review->excerpt) && ! empty($review->content)) {
                $review->excerpt = Str::limit(strip_tags($review->content), 200);
            }

            if (empty($review->status)) {
                $review->status = 'published';
            }
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relatie met team member (author)
     */
    public function teamMember()
    {
        return $this->belongsTo(TeamMember::class);
    }

    /**
     * Get SEO-friendly title for this review
     * Returns the database title which is already unique
     */
    public function getSeoTitleAttribute()
    {
        // Always return the database title, which is already unique
        // The title in the database is already unique (fixed by reviews:fix-duplicates command)
        return $this->title;
    }

    /**
     * Get SEO-friendly meta description
     * Returns meta_description if set, otherwise excerpt
     * Appends product title if needed to ensure uniqueness
     */
    public function getSeoDescriptionAttribute()
    {
        $baseDesc = $this->meta_description ?? $this->excerpt;

        // If product exists and description doesn't already contain product title, append it
        if ($this->product && stripos($baseDesc, $this->product->title) === false) {
            // Truncate to leave room for product reference
            $suffix = ' - ' . \Illuminate\Support\Str::limit($this->product->title, 50, '');
            $maxLength = 155 - strlen($suffix);
            $baseDesc = \Illuminate\Support\Str::limit($baseDesc, $maxLength, '');
            $baseDesc .= $suffix;
        }

        return $baseDesc;
    }
}
