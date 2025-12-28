<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_variations', function (Blueprint $table) {
            $table->id();
            $table->string('niche')->index(); // bijv. 'airfryers'
            $table->string('category');       // bijv. 'doelgroep', 'probleem', 'receptstijl'
            $table->string('value');          // bijv. 'gezinnen met jonge kinderen'
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_variations');
    }
};
