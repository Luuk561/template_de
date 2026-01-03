<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'ean',
        'title',
        'slug',
        'category',
        'category_segment',
        'category_chunk',
        'description',
        'source_description',
        'ai_description_html',
        'ai_summary',
        'rewritten_at',
        'rewrite_model',
        'rewrite_version',
        'url',
        'price',
        'strikethrough_price',
        'delivery_time',
        'rating_average',
        'rating_count',
        'brand',
        'image_url',
        'images_json',
        'meta_title',
        'meta_description',
        'is_available',
        'unavailable_since',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'unavailable_since' => 'datetime',
        'images_json' => 'array',
        'pros' => 'array',
        'cons' => 'array',
    ];

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    public function blogPosts()
    {
        return $this->hasMany(BlogPost::class)->where('status', 'published');
    }

    public function specifications()
    {
        return $this->hasMany(ProductSpecification::class);
    }

    /**
     * Get SEO-friendly title for this product
     * Returns the database title which is already unique (contains EAN suffix if needed)
     */
    public function getSeoTitleAttribute()
    {
        // Always return the database title, which is already unique
        // The title in the database is already unique (fixed by products:fix-duplicate-titles command)
        return $this->title;
    }

    /**
     * Get SEO-friendly meta description
     * Returns meta_description, ai_summary, or truncated description
     * Appends EAN if needed to ensure uniqueness
     */
    public function getSeoDescriptionAttribute()
    {
        $baseDesc = $this->meta_description
                   ?? $this->ai_summary
                   ?? \Illuminate\Support\Str::limit(strip_tags($this->source_description ?: $this->description), 155);

        // Ensure we don't exceed 155 character limit for SEO
        return \Illuminate\Support\Str::limit($baseDesc, 155);
    }

    /**
     * Get formatted price with Euro symbol
     */
    public function getFormattedPriceAttribute(): string
    {
        return formatPrice($this->price);
    }

    /**
     * Check if product has a discount
     */
    public function getHasDiscountAttribute(): bool
    {
        return isset($this->strikethrough_price)
            && $this->strikethrough_price > 0
            && $this->strikethrough_price > $this->price;
    }

    /**
     * Calculate savings amount in euros
     */
    public function getSavingsAttribute(): ?float
    {
        if (!$this->has_discount) {
            return null;
        }

        return round($this->strikethrough_price - $this->price, 2);
    }

    /**
     * Get formatted savings with Euro symbol
     */
    public function getFormattedSavingsAttribute(): ?string
    {
        $savings = $this->savings;

        return $savings ? formatPrice($savings) : null;
    }
}
