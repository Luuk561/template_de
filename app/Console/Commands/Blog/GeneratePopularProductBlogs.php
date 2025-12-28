<?php

namespace App\Console\Commands\Blog;

use App\Models\BlogPost;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class GeneratePopularProductBlogs extends Command
{
    protected $signature = 'app:generate-popular-product-blogs {count=2}';

    protected $description = 'Genereer blogs voor de populairste producten die nog geen blog hebben';

    public function handle()
    {
        $count = (int) $this->argument('count');
        $this->info("🔍 Haal {$count} populairste producten op zonder blog...");

        $products = Product::whereNotNull('rating_average')
            ->whereDoesntHave('blogPosts')
            ->orderByDesc('rating_average')
            ->limit($count)
            ->get();

        if ($products->isEmpty()) {
            $this->info('🎉 Geen producten gevonden zonder blog.');

            return;
        }

        $generated = 0;
        $failed = 0;
        $total = $products->count();

        foreach ($products as $index => $product) {
            $this->info("📝 [" . ($index + 1) . "/{$total}] Blog genereren voor: {$product->title} (ID {$product->id})...");

            try {
                // Call the NEW product blog command (not general blog command!)
                Artisan::call('app:generate-product-blog', [
                    'product_id' => $product->id,
                ]);

                // Check output voor success/failure
                $output = trim(Artisan::output());
                $this->line($output);

                if (str_contains($output, 'successfully') || str_contains($output, 'created')) {
                    $generated++;
                } else {
                    $failed++;
                }

                // Multi-site rate limiting:
                // - 3-5 seconden tussen calls voor server-vriendelijkheid
                // - Voorkomt dat 20+ sites tegelijk OpenAI API hammeren
                $delay = rand(3, 5);
                $this->line("⏳ Wachten {$delay}s voor volgende blog (server-vriendelijk)...");
                sleep($delay);

                // Geheugen cleanup na elke iteratie
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }

            } catch (\Exception $e) {
                $failed++;
                $this->error("❌ Fout bij genereren blog voor {$product->title}: " . $e->getMessage());
                Log::error("❌ GeneratePopularProductBlogs fout", [
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                // Bij fout, langere pauze voordat verder gaan
                sleep(5);
            }
        }

        $this->newLine();
        $this->info('🚀 Klaar met genereren.');
        $this->info("✅ Gegenereerd: {$generated} blogs");
        if ($failed > 0) {
            $this->warn("❌ Gefaald: {$failed} blogs");
        }
    }
}
