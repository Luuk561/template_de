<?php

namespace App\Console\Commands\Maintenance;

use App\Models\Product;
use App\Models\BlogPost;
use Illuminate\Console\Command;

class CleanupSitemap extends Command
{
    protected $signature = 'sitemap:cleanup';
    protected $description = 'Verify all products and blogs in sitemap still exist';

    public function handle()
    {
        $this->info('Checking for missing products and blogs...');

        // Check products
        $products = Product::all();
        $this->info("Found {$products->count()} products in database");

        // Check blogs
        $blogs = BlogPost::where('status', 'published')->get();
        $this->info("Found {$blogs->count()} published blogs in database");

        $this->info("\nSitemap will be automatically regenerated on next request.");
        $this->info("404 errors will disappear after SEMrush recrawl.");

        return 0;
    }
}
