<?php

namespace App\Console\Commands\Site;

use App\Models\BlogTemplate;
use App\Services\SiteContentGeneratorService;
use App\Services\SiteGeneratorService;
use Illuminate\Console\Command;

class GenerateBlogTemplatesForSite extends Command
{
    protected $signature = 'site:generate-blog-templates {--force : Overwrite existing templates}';
    protected $description = 'Generate 60 blog templates for the current site based on site_niche setting';

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
        $this->info('  BLOG TEMPLATES GENERATOR');
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

        // Check existing templates
        $existingCount = BlogTemplate::where('niche', $niche)->count();

        if ($existingCount > 0 && !$this->option('force')) {
            $this->warn("Found {$existingCount} existing blog templates for '{$niche}'");
            $this->warn('Use --force to overwrite existing templates');
            return 1;
        }

        if ($existingCount > 0) {
            $this->warn("Will overwrite {$existingCount} existing templates");

            if (!$this->confirm('Are you sure you want to continue?', false)) {
                $this->info('Aborted.');
                return 0;
            }
            $this->newLine();
        }

        // Generate templates
        $this->info('Generating 60 blog templates via OpenAI...');
        $this->info('This will take 60-90 seconds...');
        $this->newLine();

        try {
            $count = $this->generator->generateBlogTemplates($niche);

            if ($count === 0) {
                $this->error('Failed to generate blog templates');
                $this->warn('Check your OpenAI API key and try again');
                return 1;
            }

            $this->newLine();
            $this->info('========================================');
            $this->info('  SUCCESS!');
            $this->info('========================================');
            $this->info("Generated {$count} blog templates");
            $this->newLine();

            // Show breakdown by category
            $templates = BlogTemplate::where('niche', $niche)->get();

            // Categorize templates based on title pattern
            $categories = [
                'Top lijsten' => 0,
                'Koopgidsen' => 0,
                'Budget' => 0,
                'Vergelijkingen' => 0,
                'How-to' => 0,
                'Overig' => 0,
            ];

            foreach ($templates as $template) {
                if (str_contains($template->title_template, 'Top {number}')) {
                    $categories['Top lijsten']++;
                } elseif (str_contains($template->title_template, 'Koopgids')) {
                    $categories['Koopgidsen']++;
                } elseif (str_contains($template->title_template, 'onder €')) {
                    $categories['Budget']++;
                } elseif (str_contains($template->title_template, ' vs ')) {
                    $categories['Vergelijkingen']++;
                } elseif (str_contains($template->title_template, 'Hoe ')) {
                    $categories['How-to']++;
                } else {
                    $categories['Overig']++;
                }
            }

            $this->info('Breakdown by category:');
            foreach ($categories as $cat => $count) {
                if ($count > 0) {
                    $this->line("  {$cat}: {$count} templates");
                }
            }
            $this->newLine();

            // Show sample templates
            $samples = $templates->take(5);
            $this->info('Sample templates (instantiated):');
            foreach ($samples as $sample) {
                $instantiated = $sample->instantiate();
                $this->line("  - {$instantiated['title']}");
            }
            $this->newLine();

            $this->info('You can now generate blogs with: php artisan app:generate-blog');
            $this->info('Templates will ensure unique, SEO-optimized blogs every time!');

            return 0;

        } catch (\Exception $e) {
            $this->error('Error generating blog templates: ' . $e->getMessage());
            return 1;
        }
    }
}
