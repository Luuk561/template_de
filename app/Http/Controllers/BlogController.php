<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;

class BlogController extends Controller
{
    /**
     * Toon overzicht van alle blogposts met paginering.
     */
    public function index()
    {
        $blogPosts = BlogPost::where('status', 'published')
            ->where(function ($query) {
                $query->whereNull('meta_robots')
                      ->orWhere('meta_robots', '!=', 'noindex,nofollow');
            })
            ->orderByDesc('created_at')
            ->paginate(12);

        return view('blogs.index', compact('blogPosts'));
    }

    /**
     * Toon de detailpagina van een enkele blogpost.
     */
    public function show($slug)
    {
        $post = BlogPost::where('slug', $slug)
            ->where('status', 'published')
            ->with('product.images') // ✅ laad ook gerelateerde productafbeeldingen
            ->firstOrFail();

        return view('blogs.show', compact('post'));
    }
}
