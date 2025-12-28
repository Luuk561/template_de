<?php

namespace App\Console\Commands\System;

use Illuminate\Console\Command;
use OpenAI;

class TestOpenAI extends Command
{
    protected $signature = 'app:test-openai';

    protected $description = 'Test de OpenAI API verbinding met een simpele prompt';

    public function handle()
    {
        $apiKey = config('services.openai.key');

        // Maak OpenAI client aan
        $client = OpenAI::client($apiKey);

        $this->info('Verstuur prompt naar OpenAI...');

        $response = $client->chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'Je bent een behulpzame assistent.'],
                ['role' => 'user', 'content' => 'Hallo, test je verbinding!'],
            ],
        ]);

        $this->info('Response van OpenAI:');
        $this->line($response->choices[0]->message->content);

        return 0;
    }
}
