<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            // Verander content van text naar longText
            $table->longText('content')->change();

            // Nieuwe kolommen toevoegen
            $table->enum('type', ['product', 'general'])->default('general')->after('content');
            $table->string('excerpt')->nullable()->after('type');
            $table->string('status')->default('published')->after('excerpt');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            // Kolommen verwijderen
            $table->dropColumn(['type', 'excerpt', 'status']);

            // content terug naar text
            $table->text('content')->change();
        });
    }
};
