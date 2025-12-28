<?php

namespace App\Console\Commands\Maintenance;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FixDuplicateSlugs extends Command
{
    protected $signature = 'maintenance:fix-duplicate-slugs {--dry-run : Only show what would be fixed} {--delete : Delete duplicates instead of renaming}';

    protected $description = 'Fix duplicate product slugs by deleting or renaming duplicates';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $delete = $this->option('delete');

        if ($dryRun) {
            $this->info('DRY RUN - No changes will be made');
            $this->newLine();
        }

        $this->info('Scanning for duplicate slugs...');

        // Find duplicate slugs
        $duplicates = DB::table('products')
            ->select('slug', DB::raw('COUNT(*) as count'))
            ->groupBy('slug')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info('No duplicate slugs found!');
            return 0;
        }

        $this->warn("Found {$duplicates->count()} duplicate slug(s)");
        $this->newLine();

        $totalFixed = 0;

        foreach ($duplicates as $duplicate) {
            $slug = $duplicate->slug;
            $count = $duplicate->count;

            $this->line("Fixing '{$slug}' ({$count} products):");

            // Get all products with this slug, ordered by ID (oldest first)
            $products = DB::table('products')
                ->where('slug', $slug)
                ->orderBy('id')
                ->get();

            // Keep the first one unchanged, delete/rename the rest
            foreach ($products->skip(1) as $index => $product) {
                if ($delete) {
                    $this->line("  ID {$product->id}: DELETING '{$slug}'");

                    if (!$dryRun) {
                        // Delete related records first
                        DB::table('product_specifications')->where('product_id', $product->id)->delete();
                        DB::table('blog_post_product')->where('product_id', $product->id)->delete();

                        // Delete the product
                        DB::table('products')->where('id', $product->id)->delete();
                    }
                } else {
                    $newSlug = $slug . '-' . ($index + 1);

                    // Ensure the new slug doesn't already exist
                    $counter = $index + 1;
                    while (DB::table('products')->where('slug', $newSlug)->exists()) {
                        $counter++;
                        $newSlug = $slug . '-' . $counter;
                    }

                    $this->line("  ID {$product->id}: '{$slug}' -> '{$newSlug}'");

                    if (!$dryRun) {
                        DB::table('products')
                            ->where('id', $product->id)
                            ->update(['slug' => $newSlug]);
                    }
                }

                $totalFixed++;
            }

            $this->newLine();
        }

        if ($dryRun) {
            $action = $delete ? 'delete' : 'fix';
            $this->info("Would {$action} {$totalFixed} duplicate slug(s)");
            $this->comment('Run without --dry-run to apply changes');
        } else {
            $action = $delete ? 'Deleted' : 'Fixed';
            $this->info("{$action} {$totalFixed} duplicate slug(s)!");
        }

        return 0;
    }
}
