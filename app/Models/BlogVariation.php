<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogVariation extends Model
{
    protected $table = 'blog_variations';

    protected $fillable = [
        'niche',
        'category',
        'value',
    ];
}
