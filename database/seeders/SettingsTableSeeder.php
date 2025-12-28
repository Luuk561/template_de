<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsTableSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'site_name', 'value' => 'AirfryermetDubbeleLade.nl'],
            ['key' => 'primary_color', 'value' => '#FF6600'],
            ['key' => 'font_family', 'value' => 'Montserrat'],
            ['key' => 'favicon_url', 'value' => '/images/favicons/airfryer.png'],
            ['key' => 'homepage_cta', 'value' => 'Ontdek de beste airfryers met dubbele lade'],

            // ✅ Nieuw toegevoegd voor generate-blog
            ['key' => 'site_niche', 'value' => 'airfryers met dubbele lade'],
            ['key' => 'tone_of_voice', 'value' => 'vriendelijk, eerlijk en overtuigend'],
            ['key' => 'target_audience', 'value' => 'consumenten die gezonde maaltijden willen bereiden met gemak'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(['key' => $setting['key']], ['value' => $setting['value']]);
        }
    }
}
