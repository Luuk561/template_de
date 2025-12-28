<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Content blocks
        $this->call(ContentBlockSeeder::class);

        // Instellingen (optioneel, als je deze gebruikt)
        $this->call(SettingsTableSeeder::class);

        // Blog variaties (voor onderwerpdiversiteit per niche)
        $this->call(BlogVariationsTableSeeder::class);

        // Testgebruiker (voorkomt dubbele invoer)
        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'remember_token' => Str::random(10),
            ]
        );
    }
}
