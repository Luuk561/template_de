<?php

namespace App\Console\Commands\Site;

use App\Models\ContentBlock;
use App\Services\ContentBlocksGeneratorServiceV2;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateContentBlocksV2 extends Command
{
    protected $signature = 'site:generate-content-blocks-v2
                            {--keys= : Comma-separated list of specific keys to regenerate}
                            {--force : Overwrite existing content blocks without asking}
                            {--preview : Show preview without saving to database}
                            {--niche= : Override niche from settings}
                            {--unique-focus= : Optional unique focus/USP}';

    protected $description = 'V2: Generate content blocks with 1 OpenAI call per block (better control)';

    protected ContentBlocksGeneratorServiceV2 $generator;

    public function __construct(ContentBlocksGeneratorServiceV2 $generator)
    {
        parent::__construct();
        $this->generator = $generator;
    }

    public function handle()
    {
        $this->info('========================================');
        $this->info('  CONTENT BLOCKS GENERATOR V2');
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

        $this->info("Site: {$siteName}");
        $this->info("Niche: {$niche}");
        if ($uniqueFocus) {
            $this->info("Unique Focus: {$uniqueFocus}");
        }
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
        $this->info('Generating content blocks via OpenAI (1 call per block)...');
        $this->newLine();

        try {
            $blocks = $this->generator->generateContentBlocks(
                $niche,
                $siteName,
                $uniqueFocus,
                function($current, $total, $blockKey) {
                    $this->line("  [{$current}/{$total}] Generating {$blockKey}...");
                },
                $specificKeys  // Pass specific keys to generator to filter BEFORE generating
            );

            if (empty($blocks)) {
                $this->error('No matching content blocks found for keys: ' . implode(', ', $specificKeys));
                return Command::FAILURE;
            }

            // Preview mode
            if ($this->option('preview')) {
                $this->showPreview($blocks);
                return Command::SUCCESS;
            }

            // Save to database
            $created = 0;
            $updated = 0;

            foreach ($blocks as $key => $content) {
                $existing = ContentBlock::where('key', $key)->first();

                if ($existing) {
                    $existing->update(['content' => $content]);
                    $updated++;
                    $this->line("✓ Updated: {$key}");
                } else {
                    ContentBlock::create(['key' => $key, 'content' => $content]);
                    $created++;
                    $this->line("✓ Created: {$key}");
                }
            }

            $this->newLine();
            if ($created > 0) {
                $this->info("Created: {$created} new blocks");
            }
            if ($updated > 0) {
                $this->info("Updated: {$updated} existing blocks");
            }

            $this->newLine();
            $this->info('✓ Content blocks generated successfully!');

            // Summary
            $this->showSummary($blocks);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error generating content blocks: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function showPreview(array $blocks): void
    {
        $this->info('Preview Mode - Content will NOT be saved');
        $this->newLine();

        foreach ($blocks as $key => $content) {
            $this->line("KEY: {$key}");
            $this->line("CONTENT: " . substr($content, 0, 100) . (strlen($content) > 100 ? '...' : ''));
            $this->newLine();
        }
    }

    private function showSummary(array $blocks): void
    {
        $this->newLine();
        $this->info('Content Blocks Summary:');
        $this->line(str_repeat('-', 80));

        $grouped = [];
        foreach ($blocks as $key => $content) {
            $parts = explode('.', $key);
            $group = $parts[0];
            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }
            $grouped[$group][$key] = $content;
        }

        foreach ($grouped as $group => $items) {
            $this->newLine();
            $this->line(strtoupper($group) . ' (' . count($items) . ' blocks)');
            foreach ($items as $key => $content) {
                $length = strlen($content);
                $this->line("  - {$key} ({$length} chars)");
            }
        }

        $this->newLine();
        $this->info('Next steps:');
        $this->line('1. Check your website to verify the content');
        $this->line('2. Run: php artisan cache:clear');
        $this->line('3. Visit your site and review the changes');
        $this->newLine();
        $this->info('Tip: Use --preview flag to see content before saving');
        $this->info('Tip: Use --keys=homepage.hero to regenerate specific blocks');
    }
}
