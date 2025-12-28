<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Ruwe (ongewijzigde) Bol omschrijving als referentie / debug
            $table->longText('source_description')->nullable()->after('description');
            // Onze unieke, nette HTML (hierop render je front-end)
            $table->longText('ai_description_html')->nullable()->after('source_description');
            // Korte samenvatting voor snippets/SEO
            $table->text('ai_summary')->nullable()->after('ai_description_html');
            // Metadata
            $table->timestamp('rewritten_at')->nullable()->after('ai_summary');
            $table->string('rewrite_model')->nullable()->after('rewritten_at');
            $table->string('rewrite_version')->default('v1')->after('rewrite_model');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'source_description',
                'ai_description_html',
                'ai_summary',
                'rewritten_at',
                'rewrite_model',
                'rewrite_version',
            ]);
        });
    }
};
