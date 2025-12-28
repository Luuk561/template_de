<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('url')->nullable()->after('description');
            $table->float('strikethrough_price')->nullable()->after('price');
            $table->string('category')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['url', 'strikethrough_price', 'category']);
        });
    }
};
