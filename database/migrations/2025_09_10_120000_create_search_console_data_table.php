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
        Schema::create('search_console_data', function (Blueprint $table) {
            $table->id();
            $table->string('site_url')->index(); // URL van de huidige site
            $table->string('query')->index(); // Het zoekwoord
            $table->date('date')->index(); // Datum van de data
            $table->integer('clicks')->default(0);
            $table->integer('impressions')->default(0);
            $table->decimal('ctr', 8, 6)->default(0); // Click-through rate (0.123456)
            $table->decimal('position', 8, 2)->default(0); // Gemiddelde positie
            $table->string('page')->nullable()->index(); // Specifieke pagina URL
            $table->string('country', 2)->default('NL')->index(); // Land
            $table->string('device', 20)->default('desktop')->index(); // desktop, mobile, tablet
            $table->enum('status', ['active', 'processed', 'archived'])->default('active')->index();
            $table->json('metadata')->nullable(); // Extra data voor clustering/AI
            $table->timestamps();

            // Unique constraint om duplicaten te voorkomen
            $table->unique(['site_url', 'query', 'date', 'page'], 'gsc_unique_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_console_data');
    }
};