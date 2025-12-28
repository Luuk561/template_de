<?php

namespace App\Console\Commands\Site;

use App\Models\ContentBlock;
use App\Services\SiteContentGeneratorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateContentBlocks extends Command
{
    protected $signature = 'site:generate-content-blocks
                            {--keys= : Comma-separated list of specific keys to regenerate (e.g., homepage.hero,blogs.seo)}
                            {--force : Overwrite existing content blocks without asking}
                            {--preview : Show preview without saving to database}
                            {--niche= : Override niche from settings (optional)}
                            {--unique-focus= : Optional unique focus/USP}
                            {--format=hybrid : Output format: "hybrid" (text-only, default) or "html" (full HTML, legacy)}';

    protected $description = 'Generate or regenerate content blocks for the current site (backwards compatible with HTML mode)';

    protected SiteContentGeneratorService $generator;

    public function __construct(SiteContentGeneratorService $generator)
    {
        parent::__construct();
        $this->generator = $generator;
    }

    public function handle()
    {
        $this->info('========================================');
        $this->info('  CONTENT BLOCKS GENERATOR');
        $this->info('========================================');
        $this->newLine();

        // Get site settings
        $siteName = getSetting('site_name');
        $niche = $this->option('niche') ?: getSetting('site_niche', 'producten');
        $uniqueFocus = $this->option('unique-focus') ?: getSetting('unique_focus');

        if (!$siteName || !$niche) {
            $this->error('Site settings not found. Run site:generate first.');
            return Command::FAILURE;
        }

        $format = $this->option('format') ?: 'hybrid';

        $this->info("Site: {$siteName}");
        $this->info("Niche: {$niche}");
        if ($uniqueFocus) {
            $this->info("Unique Focus: {$uniqueFocus}");
        }
        $this->info("Format: {$format} " . ($format === 'html' ? '(full HTML, legacy)' : '(text-only, hybrid approach)'));
        $this->newLine();

        // Parse specific keys if provided
        $specificKeys = $this->option('keys')
            ? array_map('trim', explode(',', $this->option('keys')))
            : [];

        if ($specificKeys) {
            $this->info('Regenerating specific keys: ' . implode(', ', $specificKeys));
            $this->newLine();
        }

        // Check for existing content blocks
        if (!$this->option('preview')) {
            $existingCount = ContentBlock::count();
            if ($existingCount > 0 && !$this->option('force')) {
                $this->warn("Found {$existingCount} existing content blocks.");
                if (!$this->confirm('Do you want to overwrite them?', false)) {
                    $this->warn('Aborted by user.');
                    return Command::FAILURE;
                }
            }
        }

        // Generate content blocks
        $this->info('Generating content blocks via OpenAI...');
        $this->newLine();

        try {
            $blocks = $this->generator->generateContentBlocks($niche, $siteName, $uniqueFocus, $format);

            // Filter to specific keys if requested
            if ($specificKeys) {
                $blocks = array_filter($blocks, function($key) use ($specificKeys) {
                    return in_array($key, $specificKeys);
                }, ARRAY_FILTER_USE_KEY);

                if (empty($blocks)) {
                    $this->error('No matching content blocks found for keys: ' . implode(', ', $specificKeys));
                    return Command::FAILURE;
                }
            }

            // Preview mode
            if ($this->option('preview')) {
                $this->showPreview($blocks);
                return Command::SUCCESS;
            }

            // Save to database
            $this->saveContentBlocks($blocks, $specificKeys);

            $this->newLine();
            $this->info('✓ Content blocks generated successfully!');
            $this->newLine();

            // Show summary
            $this->showSummary($blocks);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to generate content blocks: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    protected function showPreview(array $blocks): void
    {
        $this->info('PREVIEW MODE - Content blocks will NOT be saved');
        $this->newLine();

        foreach ($blocks as $key => $content) {
            $this->line('');
            $this->line(str_repeat('=', 80));
            $this->info("KEY: {$key}");
            $this->line(str_repeat('-', 80));

            // Truncate long content for preview
            $preview = strlen($content) > 500
                ? substr($content, 0, 500) . '...'
                : $content;

            $this->line($preview);
            $this->line('');
            $this->comment("Length: " . strlen($content) . " characters");
            $this->line(str_repeat('=', 80));
        }

        $this->newLine();
        $this->info("Total blocks generated: " . count($blocks));
    }

    protected function saveContentBlocks(array $blocks, array $specificKeys = []): void
    {
        $saved = 0;
        $updated = 0;

        DB::transaction(function() use ($blocks, &$saved, &$updated, $specificKeys) {
            foreach ($blocks as $key => $content) {
                $existing = ContentBlock::where('key', $key)->first();

                if ($existing) {
                    $existing->update(['content' => $content]);
                    $updated++;
                    $this->line("✓ Updated: {$key}");
                } else {
                    ContentBlock::create([
                        'key' => $key,
                        'content' => $content,
                    ]);
                    $saved++;
                    $this->line("✓ Created: {$key}");
                }
            }
        });

        $this->newLine();
        if ($saved > 0) {
            $this->info("Created: {$saved} new blocks");
        }
        if ($updated > 0) {
            $this->info("Updated: {$updated} existing blocks");
        }
    }

    protected function showSummary(array $blocks): void
    {
        $this->info('Content Blocks Summary:');
        $this->line(str_repeat('-', 80));

        $grouped = $this->groupBlocksByPrefix($blocks);

        foreach ($grouped as $prefix => $keys) {
            $this->line('');
            $this->comment(strtoupper($prefix) . ' (' . count($keys) . ' blocks)');
            foreach ($keys as $key) {
                $length = strlen($blocks[$key]);
                $this->line("  - {$key} ({$length} chars)");
            }
        }

        $this->newLine();
        $this->info('Next steps:');
        $this->line('1. Check your website to verify the content');
        $this->line('2. Run: php artisan cache:clear');
        $this->line('3. Visit your site and review the changes');
        $this->newLine();
        $this->comment('Tip: Use --preview flag to see content before saving');
        $this->comment('Tip: Use --keys=homepage.hero to regenerate specific blocks');
    }

    protected function groupBlocksByPrefix(array $blocks): array
    {
        $grouped = [];

        foreach (array_keys($blocks) as $key) {
            $prefix = explode('.', $key)[0];
            $grouped[$prefix][] = $key;
        }

        return $grouped;
    }
}
