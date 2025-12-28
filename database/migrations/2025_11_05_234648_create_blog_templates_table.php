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
        Schema::create('blog_templates', function (Blueprint $table) {
            $table->id();
            $table->string('niche')->index();
            $table->string('title_template');
            $table->string('slug_template');
            $table->string('seo_focus_keyword');
            $table->json('content_outline'); // H2/H3 structure
            $table->integer('target_word_count')->default(1500);
            $table->string('cta_type'); // comparison_table, top_product, buying_guide
            $table->json('variables'); // {number: [3,5,10], use_case: ["beginners"]}
            $table->integer('min_days_between_reuse')->default(60);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blog_templates');
    }
};
