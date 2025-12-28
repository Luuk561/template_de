<?php

namespace App\Console\Commands\Site;

use App\Models\BlogVariation;
use App\Services\SiteContentGeneratorService;
use App\Services\SiteGeneratorService;
use Illuminate\Console\Command;

class GenerateBlogVariationsForSite extends Command
{
    protected $signature = 'site:generate-blog-variations {--force : Overwrite existing variations}';
    protected $description = 'Generate blog variations for the current site based on site_niche setting';

    protected $contentGenerator;
    protected $generator;

    public function __construct(SiteContentGeneratorService $contentGenerator, SiteGeneratorService $generator)
    {
        parent::__construct();
        $this->contentGenerator = $contentGenerator;
        $this->generator = $generator;
    }

    public function handle()
    {
        $this->info('========================================');
        $this->info('  BLOG VARIATIONS GENERATOR');
        $this->info('========================================');
        $this->newLine();

        // Get site niche from settings
        $niche = getSetting('site_niche');

        if (!$niche) {
            $this->error('Error: site_niche setting not found!');
            $this->warn('Please set site_niche in your settings table first.');
            return 1;
        }

        $this->info("Site niche: {$niche}");
        $this->newLine();

        // Check existing variations
        $existingCount = BlogVariation::where('niche', $niche)->count();

        if ($existingCount > 0 && !$this->option('force')) {
            $this->warn("Found {$existingCount} existing blog variations for '{$niche}'");
            $this->warn('Use --force to overwrite existing variations');
            return 1;
        }

        if ($existingCount > 0) {
            $this->warn("Will overwrite {$existingCount} existing variations");

            if (!$this->confirm('Are you sure you want to continue?', false)) {
                $this->info('Aborted.');
                return 0;
            }
            $this->newLine();
        }

        // Generate variations
        $this->info('Generating blog variations via OpenAI (30-60 seconds)...');
        $this->newLine();

        try {
            $count = $this->generator->generateBlogVariations($niche);

            if ($count === 0) {
                $this->error('Failed to generate blog variations');
                $this->warn('Check your OpenAI API key and try again');
                return 1;
            }

            $this->newLine();
            $this->info('========================================');
            $this->info('  SUCCESS!');
            $this->info('========================================');
            $this->info("Generated {$count} blog variations");
            $this->newLine();

            // Show breakdown by category
            $categories = BlogVariation::where('niche', $niche)
                ->selectRaw('category, COUNT(*) as count')
                ->groupBy('category')
                ->get();

            $this->info('Breakdown by category:');
            foreach ($categories as $cat) {
                $this->line("  {$cat->category}: {$cat->count} variations");
            }
            $this->newLine();

            $this->info('You can now generate blogs with: php artisan app:generate-blog');

            return 0;

        } catch (\Exception $e) {
            $this->error('Error generating blog variations: ' . $e->getMessage());
            return 1;
        }
    }
}
