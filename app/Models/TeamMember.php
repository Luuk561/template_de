<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TeamMember extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'role',
        'bio',
        'quote',
        'focus',
        'tone',
        'photo_url',
    ];

    protected static function boot()
    {
        parent::boot();

        // Auto-generate slug from name if not provided
        static::saving(function ($teamMember) {
            if (empty($teamMember->slug) && !empty($teamMember->name)) {
                $teamMember->slug = Str::slug($teamMember->name);
            }
        });
    }

    /**
     * Team member has many blog posts
     */
    public function blogPosts()
    {
        return $this->hasMany(BlogPost::class);
    }

    /**
     * Team member has many reviews
     */
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Get all content (blogs + reviews) for this team member
     */
    public function allContent()
    {
        return $this->blogPosts()
            ->where('status', 'published')
            ->latest()
            ->get()
            ->concat(
                $this->reviews()
                    ->where('status', 'published')
                    ->latest()
                    ->get()
            )
            ->sortByDesc('created_at');
    }

    /**
     * Get URL to team member profile
     */
    public function url(): string
    {
        return route('team.show', $this->slug);
    }
}
