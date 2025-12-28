<?php

namespace App\Http\Controllers;

use App\Models\Review;

class ReviewController extends Controller
{
    /**
     * Toon het overzicht van alle gepubliceerde reviews.
     */
    public function index()
    {
        $reviews = Review::where('status', 'published')
            ->where(function ($query) {
                $query->whereNull('meta_robots')
                      ->orWhere('meta_robots', '!=', 'noindex,nofollow');
            })
            ->with('product')
            ->orderByDesc('created_at')
            ->paginate(12);

        return view('reviews.index', compact('reviews'));
    }

    /**
     * Toon een reviewpagina op basis van de slug.
     */
    public function show(string $slug)
    {
        $review = Review::where('slug', $slug)
            ->with(['product.images']) // ✅ inclusief productafbeeldingen
            ->firstOrFail();

        // ✅ Gebruik tweede afbeelding indien beschikbaar, anders fallback
        $secondaryImage = $review->product?->images->get(1)?->url ?? $review->product?->image_url;

        return view('reviews.show', compact('review', 'secondaryImage'));
    }
}
