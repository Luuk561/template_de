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
            // Add amazon_affiliate_link column (without 'after' to avoid dependency on asin column)
            if (!Schema::hasColumn('products', 'amazon_affiliate_link')) {
                $table->text('amazon_affiliate_link')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('amazon_affiliate_link');
        });
    }
};
