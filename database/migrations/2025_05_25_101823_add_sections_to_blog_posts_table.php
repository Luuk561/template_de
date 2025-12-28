<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->text('intro')->nullable()->after('content');
            $table->longText('main_content')->nullable()->after('intro');
            $table->longText('benefits')->nullable()->after('main_content');
            $table->longText('usage_tips')->nullable()->after('benefits');
            $table->text('closing')->nullable()->after('usage_tips');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn([
                'intro',
                'main_content',
                'benefits',
                'usage_tips',
                'closing',
            ]);
        });
    }
};
