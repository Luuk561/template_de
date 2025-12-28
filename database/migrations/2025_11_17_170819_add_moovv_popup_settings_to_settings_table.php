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
        // Insert default popup settings
        DB::table('settings')->insert([
            ['key' => 'popup_enabled', 'value' => '0', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'popup_title', 'value' => 'Exclusief: 20% korting op Moovv loopbanden!', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'popup_description', 'value' => 'Gebruik onze exclusieve kortingscode en bespaar direct op jouw nieuwe loopband.', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'popup_discount_code', 'value' => 'LOOPBANDENTEST', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'popup_discount_percentage', 'value' => '20', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'popup_affiliate_link', 'value' => 'https://moovvmore.nl/LOOPBANDENTEST', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'popup_review_slug', 'value' => '', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'popup_delay_seconds', 'value' => '5', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'popup_enabled',
            'popup_title',
            'popup_description',
            'popup_discount_code',
            'popup_discount_percentage',
            'popup_affiliate_link',
            'popup_review_slug',
            'popup_delay_seconds',
        ])->delete();
    }
};
