<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Add indexes for commonly filtered/sorted columns
            $table->index('brand', 'idx_products_brand');
            $table->index('price', 'idx_products_price');
            $table->index('rating_average', 'idx_products_rating_average');
            $table->index('popularity_score', 'idx_products_popularity_score');
            $table->index('created_at', 'idx_products_created_at');

            // Composite index for common filter combinations
            $table->index(['price', 'rating_average'], 'idx_products_price_rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Drop indexes in reverse order
            $table->dropIndex('idx_products_price_rating');
            $table->dropIndex('idx_products_created_at');
            $table->dropIndex('idx_products_popularity_score');
            $table->dropIndex('idx_products_rating_average');
            $table->dropIndex('idx_products_price');
            $table->dropIndex('idx_products_brand');
        });
    }
};
