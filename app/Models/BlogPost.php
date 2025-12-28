<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    protected $fillable = [
        'product_id',
        'team_member_id',
        'title',
        'slug',
        'content',
        'excerpt',
        'type',
        'status',
        'meta_title',
        'meta_description',
        'meta_robots',
        'intro',
        'main_content',
        'benefits',
        'usage_tips',
        'closing',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($blogPost) {
            if (empty($blogPost->excerpt) && ! empty($blogPost->content)) {
                $plainText = strip_tags($blogPost->content);
                $blogPost->excerpt = Str::limit($plainText, 200);
            }

            if (empty($blogPost->status)) {
                $blogPost->status = 'draft';
            }
        });
    }

    /**
     * Relatie met gekoppeld product (optioneel)
     */
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
     * Get SEO-friendly title for this blog post
     * Returns the database title which is already unique
     */
    public function getSeoTitleAttribute()
    {
        // Always return the database title, which is already unique
        // The title in the database is already unique (fixed by blogs:fix-duplicates command)
        return $this->title;
    }

    /**
     * Get SEO-friendly meta description
     * Returns meta_description if set, otherwise excerpt (which is already unique)
     */
    public function getSeoDescriptionAttribute()
    {
        // Simply return meta_description if set, otherwise excerpt
        // The meta_description in the database is already unique (fixed by blogs:fix-duplicates command)
        return $this->meta_description ?? $this->excerpt;
    }
}
