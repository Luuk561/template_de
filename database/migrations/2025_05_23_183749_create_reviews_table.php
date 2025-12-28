<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content');
            $table->string('image_url')->nullable();
            $table->unsignedTinyInteger('rating');
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->text('excerpt')->nullable();
            $table->string('status')->default('published'); // "draft", "published", "hidden"
            $table->timestamps();
        });
    }
};
