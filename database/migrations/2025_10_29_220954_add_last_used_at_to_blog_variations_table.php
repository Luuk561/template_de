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
        Schema::table('blog_variations', function (Blueprint $table) {
            $table->timestamp('last_used_at')->nullable()->after('value');
            $table->index('last_used_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blog_variations', function (Blueprint $table) {
            $table->dropIndex(['last_used_at']);
            $table->dropColumn('last_used_at');
        });
    }
};
