<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Voeg 'gsc_opportunity' toe aan het type ENUM in blog_posts tabel
        DB::statement("ALTER TABLE blog_posts MODIFY COLUMN type ENUM('general', 'product', 'comparison', 'gsc_opportunity') NOT NULL DEFAULT 'general'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Verwijder 'gsc_opportunity' uit het type ENUM (alleen als er geen records met dit type zijn)
        DB::statement("UPDATE blog_posts SET type = 'general' WHERE type = 'gsc_opportunity'");
        DB::statement("ALTER TABLE blog_posts MODIFY COLUMN type ENUM('general', 'product', 'comparison') NOT NULL DEFAULT 'general'");
    }
};
