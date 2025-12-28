<?php

namespace App\Console\Commands\Review;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class GenerateSingleReview extends Command
{
    protected $signature = 'generate:single-review';

    protected $description = 'Genereer elke keer precies één review voor een product dat nog geen review heeft';

    public function handle()
    {
        $product = Product::doesntHave('review')->first();

        if (! $product) {
            $this->info('Alle producten hebben al een review ✅');

            return;
        }

        $this->info("Genereer review voor: {$product->title}");

        Artisan::call('generate:review', [
            'product_id' => $product->id,
        ]);

        $this->info('Review gegenereerd ✅');
    }
}
