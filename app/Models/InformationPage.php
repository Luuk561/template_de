<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InformationPage extends Model
{
    protected $fillable = [
        'title',
        'menu_title',
        'slug',
        'content',
        'excerpt',
        'meta_title',
        'meta_description',
        'order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc');
    }
}
