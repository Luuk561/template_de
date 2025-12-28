<?php

namespace App\Console\Commands\Maintenance;

use App\Models\Product;
use Illuminate\Console\Command;

class FixDuplicateProductTitles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:fix-duplicate-titles {--dry-run : Show what would be changed without actually changing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix duplicate product titles by adding unique identifiers (brand, EAN, or ID)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('Running in DRY RUN mode - no changes will be made');
            $this->newLine();
        }

        // Find all duplicate titles
        $duplicateTitles = Product::selectRaw('title, COUNT(*) as count')
            ->groupBy('title')
            ->having('count', '>', 1)
            ->pluck('title');

        if ($duplicateTitles->isEmpty()) {
            $this->info('No duplicate product titles found!');
            return 0;
        }

        $this->info("Found {$duplicateTitles->count()} titles with duplicates");
        $this->newLine();

        $totalUpdated = 0;

        foreach ($duplicateTitles as $duplicateTitle) {
            $products = Product::where('title', $duplicateTitle)->get();

            $this->warn("Title: {$duplicateTitle} ({$products->count()} duplicates)");

            foreach ($products as $product) {
                $originalTitle = $product->title;
                $newTitle = null;

                // Strategy 1: Add brand if available and not already in title
                if ($product->brand && stripos($originalTitle, $product->brand) === false) {
                    $newTitle = $product->brand . ' ' . $originalTitle;
                }
                // Strategy 2: Add last 4 digits of EAN
                elseif ($product->ean && strlen($product->ean) >= 4) {
                    $eanSuffix = substr($product->ean, -4);
                    $newTitle = $originalTitle . ' (EAN: ...' . $eanSuffix . ')';
                }
                // Strategy 3: Add product ID as fallback
                else {
                    $newTitle = $originalTitle . ' (ID: ' . $product->id . ')';
                }

                if ($newTitle && $newTitle !== $originalTitle) {
                    $this->line("  - Product #{$product->id}: {$originalTitle} → {$newTitle}");

                    if (!$dryRun) {
                        $product->title = $newTitle;
                        $product->save();
                        $totalUpdated++;
                    }
                }
            }

            $this->newLine();
        }

        if ($dryRun) {
            $this->info("DRY RUN complete. {$totalUpdated} products would be updated.");
            $this->info("Run without --dry-run to apply changes.");
        } else {
            $this->info("Successfully updated {$totalUpdated} product titles!");
        }

        return 0;
    }
}
