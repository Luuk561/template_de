<?php

namespace App\Console\Commands\Site;

use App\Services\SiteGeneratorService;
use App\Services\ForgeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class GenerateSite extends Command
{
    protected $signature = 'site:generate
                            {--niche= : Site niche (z.B., "Heißluftfritteusen mit doppeltem Korb")}
                            {--unique-focus= : Optional unique focus/USP (z.B., "ideal für Familien mit großem Kochbedarf")}
                            {--domain= : Site domain (z.B., "airfryer-test.de")}
                            {--primary-color= : Primary color hex (default: #7c3aed)}
                            {--bol-site-id= : Bol.com affiliate site ID (temporary for demo)}
                            {--bol-category-id= : Bol.com category ID for product fetching (temporary)}
                            {--deploy-to-forge : Automatically deploy site to Laravel Forge}
                            {--force : Overwrite existing content (DANGEROUS!)}
                            {--skip-team : Skip team generation}
                            {--skip-favicon : Skip favicon generation}
                            {--skip-seed-blogs : Skip seed blog post generation}
                            {--fetch-products : Fetch ~50 products from Bol.com after setup}';

    protected $description = 'Generiert komplette Deutsche Affiliate-Site: Einstellungen, Content-Blöcke, Team, Favicon und Seed-Content';

    protected SiteGeneratorService $generator;
    protected ?ForgeService $forgeService = null;

    /**
     * Store image filenames for final summary
     */
    protected array $imageFilenames = [];

    /**
     * Store Forge deployment result
     */
    protected ?array $forgeDeployment = null;

    public function __construct(SiteGeneratorService $generator)
    {
        parent::__construct();
        $this->generator = $generator;
    }

    public function handle()
    {
        $this->info('========================================');
        $this->info('  DEUTSCHE AFFILIATE SITE GENERATOR');
        $this->info('========================================');
        $this->newLine();

        // STEP 1: Validate inputs
        $input = $this->validateAndCollectInput();
        if (!$input) {
            return Command::FAILURE;
        }

        // STEP 1b: If deploying to Forge, check that now
        $deployToForge = $this->option('deploy-to-forge');
        if ($deployToForge) {
            if (!$this->validateForgeConfiguration()) {
                return Command::FAILURE;
            }
        }

        // STEP 2: Safety checks (backwards compatibility) - SKIP if deploying to Forge
        if (!$deployToForge && !$this->performSafetyChecks()) {
            return Command::FAILURE;
        }

        // STEP 3: Show generation plan
        $this->showGenerationPlan($input);

        if (!$this->confirm('Continue with site generation?', true)) {
            $this->warn('Aborted by user.');
            return Command::FAILURE;
        }

        $this->newLine();

        // STEP 3b: Deploy to Forge FIRST if requested
        if ($deployToForge) {
            $this->deployToForge($input);
            if (!$this->forgeDeployment) {
                $this->error('Forge deployment failed. Aborting.');
                return Command::FAILURE;
            }

            $this->newLine();
            $this->info('✓ Forge deployment successful!');
            $this->newLine();
            $this->info('Next steps:');
            $this->line('1. SSH into the server');
            $this->line('2. cd /home/forge/' . $input['domain']);
            $this->line('3. Run: php artisan site:generate --niche="' . $input['niche'] . '" --domain="' . $input['domain'] . '" --primary-color="' . $input['primary_color'] . '"');
            if (!empty($input['bol_site_id'])) {
                $this->line('   --bol-site-id="' . $input['bol_site_id'] . '"');
            }
            if (!empty($input['bol_category_id'])) {
                $this->line('   --bol-category-id="' . $input['bol_category_id'] . '"');
            }
            if ($this->option('fetch-products')) {
                $this->line('   --fetch-products');
            }
            $this->newLine();

            return Command::SUCCESS;
        }

        $this->info('Starting site generation...');
        $this->newLine();

        // STEP 4: Generate settings
        $this->generateSettings($input);

        // STEP 5: Generate content blocks
        $this->generateContentBlocks($input);

        // STEP 6: Generate team (unless skipped)
        if (!$this->option('skip-team')) {
            $this->generateTeam();
        }

        // STEP 7: Generate favicon (unless skipped)
        if (!$this->option('skip-favicon')) {
            $this->generateFavicon($input);
        }

        // STEP 8: Generate blog variations (backwards compatibility)
        $this->generateBlogVariations($input);

        // STEP 9: Generate blog templates (new system)
        $this->generateBlogTemplates($input);

        // STEP 10: Generate product blog templates (product-focused blogs)
        $this->generateProductBlogTemplates($input);

        // STEP 11: Generate information pages
        $this->generateInformationPages($input);

        // STEP 12: Generate seed blog posts (unless skipped)
        if (!$this->option('skip-seed-blogs')) {
            $this->generateSeedBlogs($input);
        }

        // STEP 13: Fetch products (if requested)
        if ($this->option('fetch-products')) {
            $this->fetchProducts();
        }

        // STEP 14: Final summary and next steps
        $this->showFinalSummary($input);

        return Command::SUCCESS;
    }

    /**
     * Validate and collect all input parameters
     */
    protected function validateAndCollectInput(): ?array
    {
        $niche = $this->option('niche') ?: $this->ask('What is the site niche?', 'producten');
        $uniqueFocus = $this->option('unique-focus') ?: null;
        $domain = $this->option('domain') ?: $this->ask('What is the domain name?', 'example.nl');
        $primaryColor = $this->option('primary-color') ?: $this->ask('Primary color (hex)?', '#7c3aed');
        $bolSiteId = $this->option('bol-site-id') ?: $this->ask('Bol.com Site ID? (optional, can set later in .env)', null);
        $bolCategoryId = $this->option('bol-category-id') ?: $this->ask('Bol.com Category ID? (optional, can set later in .env)', null);

        // Validate hex color
        if (!preg_match('/^#[a-fA-F0-9]{6}$/', $primaryColor)) {
            $this->error("Invalid hex color: {$primaryColor}");
            return null;
        }

        // Validate domain format
        if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $domain)) {
            $this->error("Invalid domain format: {$domain}");
            return null;
        }

        return [
            'niche' => $niche,
            'unique_focus' => $uniqueFocus,
            'domain' => $domain,
            'primary_color' => $primaryColor,
            'bol_site_id' => $bolSiteId,
            'bol_category_id' => $bolCategoryId,
        ];
    }

    /**
     * Perform safety checks to prevent overwriting existing sites
     */
    protected function performSafetyChecks(): bool
    {
        $existing = $this->generator->checkExistingContent();

        $hasContent = $existing['has_settings'] || $existing['has_content_blocks'] || $existing['has_team'];

        if (!$hasContent) {
            $this->info('Safety check: Database is empty. Safe to proceed.');
            $this->newLine();
            return true;
        }

        // Content exists - show warning
        $this->newLine();
        $this->warn('WARNING: Existing content detected!');
        $this->newLine();
        $this->table(
            ['Type', 'Status'],
            [
                ['Settings', $existing['has_settings'] ? 'EXISTS' : 'Empty'],
                ['Content Blocks', $existing['has_content_blocks'] ? 'EXISTS' : 'Empty'],
                ['Team Members', $existing['has_team'] ? 'EXISTS' : 'Empty'],
                ['Blog Posts', $existing['has_blogs'] ? 'EXISTS' : 'Empty'],
            ]
        );
        $this->newLine();

        if (!$this->option('force')) {
            $this->error('This site already has content!');
            $this->warn('Use --force to overwrite existing content (DANGEROUS!)');
            $this->warn('This will REPLACE all settings and content blocks.');
            return false;
        }

        // Force flag used - require confirmation (unless running non-interactively)
        if ($this->option('no-interaction')) {
            // Automated deployment - skip confirmation
            $this->warn('FORCE MODE: Overwriting existing content (automated deployment)');
            return true;
        }

        $this->error('DANGER: You are about to OVERWRITE existing content!');
        $this->warn('This will affect Settings and Content Blocks.');
        $this->warn('Team members and blog posts will NOT be deleted, but may be affected.');
        $this->newLine();

        if (!$this->confirm('Are you ABSOLUTELY SURE you want to continue?', false)) {
            $this->warn('Aborted by user. No changes made.');
            return false;
        }

        return true;
    }

    /**
     * Show what will be generated
     */
    protected function showGenerationPlan(array $input): void
    {
        $this->info('Generation Plan:');
        $this->newLine();

        $rows = [
            ['Niche', $input['niche']],
        ];

        if (!empty($input['unique_focus'])) {
            $rows[] = ['Unique Focus', $input['unique_focus']];
        }

        $rows = array_merge($rows, [
            ['Domain', $input['domain']],
            ['Primary Color', $input['primary_color']],
            ['Bol Site ID', $input['bol_site_id'] ?? 'Not set (configure in .env later)'],
            ['', ''],
            ['Settings', '6 entries (site_name, site_niche, primary_color, font_family, tone_of_voice, target_audience)'],
            ['Content Blocks', '23 entries (homepage, products, reviews, blogs)'],
            ['Team Members', $this->option('skip-team') ? 'SKIPPED' : '3 fictional team members'],
            ['Favicon', $this->option('skip-favicon') ? 'SKIPPED' : 'Multiple sizes (16x16 to 512x512)'],
            ['Seed Blogs', $this->option('skip-seed-blogs') ? 'SKIPPED' : '3 draft blog post ideas'],
            ['Products', $this->option('fetch-products') ? 'Will fetch after setup' : 'Not fetching (run manually later)'],
        ]);

        $this->table(['Item', 'Details'], $rows);
        $this->newLine();
    }

    /**
     * Generate settings
     */
    protected function generateSettings(array $input): void
    {
        $this->info('[1/6] Generating settings...');

        try {
            $settings = $this->generator->generateSettings($input);

            $this->info('Settings generated:');
            foreach ($settings as $key => $value) {
                $displayValue = strlen($value) > 50 ? substr($value, 0, 47) . '...' : $value;
                $this->line("  - {$key}: {$displayValue}");
            }
            $this->newLine();

        } catch (\Exception $e) {
            $this->error('Failed to generate settings: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate content blocks
     */
    protected function generateContentBlocks(array $input): void
    {
        $this->info('[2/6] Generating content blocks (this may take 30-60 seconds)...');

        // Use site_name from generated settings
        $siteName = \App\Models\Setting::where('key', 'site_name')->value('value') ?? $input['domain'];

        try {
            $blocks = $this->generator->generateContentBlocks(
                $input['niche'],
                $siteName,
                $input['unique_focus'] ?? null
            );

            $this->info("Generated " . count($blocks) . " content blocks:");
            $categories = [
                'homepage' => 0,
                'producten' => 0,
                'merken' => 0,
                'reviews' => 0,
                'blogs' => 0,
            ];

            foreach (array_keys($blocks) as $key) {
                if (str_starts_with($key, 'homepage')) $categories['homepage']++;
                elseif (str_starts_with($key, 'producten')) $categories['producten']++;
                elseif (str_starts_with($key, 'merken')) $categories['merken']++;
                elseif (str_starts_with($key, 'reviews')) $categories['reviews']++;
                elseif (str_starts_with($key, 'blogs')) $categories['blogs']++;
            }

            foreach ($categories as $category => $count) {
                if ($count > 0) {
                    $this->line("  - {$category}.*: {$count} blocks");
                }
            }
            $this->newLine();

        } catch (\Exception $e) {
            $this->error('Failed to generate content blocks: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate team members
     */
    protected function generateTeam(): void
    {
        $this->info('[3/6] Generating team members (delegating to team:generate)...');
        $this->newLine();

        try {
            Artisan::call('team:generate', [], $this->output);
            $this->newLine();

        } catch (\Exception $e) {
            $this->warn('Team generation failed: ' . $e->getMessage());
            $this->warn('You can run "php artisan team:generate" manually later.');
            $this->newLine();
        }
    }

    /**
     * Generate favicon
     */
    protected function generateFavicon(array $input): void
    {
        $this->info('[4/6] Generating favicon (delegating to generate:favicon)...');
        $this->newLine();

        try {
            Artisan::call('generate:favicon', [], $this->output);
            $this->newLine();

        } catch (\Exception $e) {
            $this->warn('Favicon generation failed: ' . $e->getMessage());
            $this->warn('You can run "php artisan generate:favicon" manually later.');
            $this->newLine();
        }
    }

    /**
     * Generate blog variations
     */
    protected function generateBlogVariations(array $input): void
    {
        $this->info('[8/14] Generating blog variations...');

        try {
            $variationCount = $this->generator->generateBlogVariations(
                $input['niche'],
                $input['unique_focus'] ?? null
            );
            $this->info("Generated {$variationCount} blog variations (doelgroepen, problemen, themas, etc.)");
            $this->newLine();
        } catch (\Exception $e) {
            $this->warn('Blog variations generation failed: ' . $e->getMessage());
            $this->newLine();
        }
    }

    /**
     * Generate blog templates
     */
    protected function generateBlogTemplates(array $input): void
    {
        $this->info('[9/14] Generating 60 blog templates via OpenAI (this may take 60-90 seconds)...');

        try {
            $count = $this->generator->generateBlogTemplates(
                $input['niche'],
                $input['unique_focus'] ?? null
            );

            if ($count > 0) {
                $this->info("Generated {$count} blog templates");
                $this->line('  These templates will be used for automated blog generation.');
                $this->line('  Each template can be reused after 60 days for infinite blog possibilities.');
            } else {
                $this->warn('No blog templates generated (falling back to variation system).');
            }

            $this->newLine();

        } catch (\Exception $e) {
            $this->warn('Blog template generation failed: ' . $e->getMessage());
            $this->warn('Blog generation will fall back to variation system.');
            $this->newLine();
        }
    }

    /**
     * Generate product blog templates
     */
    protected function generateProductBlogTemplates(array $input): void
    {
        $this->info('[10/14] Generating 20 product blog templates via OpenAI (this may take 60-90 seconds)...');

        try {
            $count = $this->generator->generateProductBlogTemplates(
                $input['niche'],
                $input['unique_focus'] ?? null
            );

            if ($count > 0) {
                $this->info("Generated {$count} product blog templates");
                $this->line('  These templates will be used for product-focused blog generation.');
                $this->line('  Use: php artisan app:generate-product-blog {product_id}');
            } else {
                $this->warn('No product blog templates generated.');
            }

            $this->newLine();

        } catch (\Exception $e) {
            $this->warn('Product blog template generation failed: ' . $e->getMessage());
            $this->warn('Product blog generation may not work correctly.');
            $this->newLine();
        }
    }

    /**
     * Generate information pages
     */
    protected function generateInformationPages(array $input): void
    {
        $this->info('[11/14] Generating 5-7 information pages via OpenAI (this may take 120-180 seconds)...');

        try {
            $count = $this->generator->generateInformationPages(
                $input['niche'],
                $input['unique_focus'] ?? null
            );

            if ($count > 0) {
                $this->info("Generated {$count} information pages");
                $this->line('  These pages provide decision-stage content to help users choose the right product.');
            } else {
                $this->warn('No information pages generated.');
            }

            $this->newLine();

        } catch (\Exception $e) {
            $this->warn('Information pages generation failed: ' . $e->getMessage());
            $this->warn('You can run "php artisan generate:all-information-pages" manually later.');
            $this->newLine();
        }
    }

    /**
     * Generate seed blog posts
     */
    protected function generateSeedBlogs(array $input): void
    {
        $this->info('[12/14] Generating seed blog posts...');

        $siteName = \App\Models\Setting::where('key', 'site_name')->value('value') ?? $input['domain'];

        try {
            $blogIds = $this->generator->generateSeedBlogPosts($input['niche'], $siteName);

            if (count($blogIds) > 0) {
                $this->info("Generated " . count($blogIds) . " seed blog post ideas (status: draft)");
                $this->line('  Use "php artisan blog:generate" to create full blog posts from these ideas.');
            } else {
                $this->warn('No seed blog posts generated.');
            }

            $this->newLine();

        } catch (\Exception $e) {
            $this->warn('Seed blog generation failed: ' . $e->getMessage());
            $this->newLine();
        }
    }

    /**
     * Fetch products from Bol.com
     */
    protected function fetchProducts(): void
    {
        $this->info('[13/14] Fetching products from Bol.com...');
        $this->newLine();

        try {
            Artisan::call('fetch:bol-products', [], $this->output);
            $this->newLine();

        } catch (\Exception $e) {
            $this->warn('Product fetching failed: ' . $e->getMessage());
            $this->warn('You can run "php artisan fetch:bol-products" manually later.');
            $this->newLine();
        }
    }

    /**
     * Show final summary and next steps
     */
    protected function showFinalSummary(array $input): void
    {
        $this->newLine();
        $this->info('========================================');
        $this->info('  SITE GENERATION COMPLETE!');
        $this->info('========================================');
        $this->newLine();

        $siteName = \App\Models\Setting::where('key', 'site_name')->value('value') ?? $input['domain'];

        $this->info("Site: {$siteName}");
        $this->info("Niche: {$input['niche']}");
        $this->newLine();

        $this->info('What was generated:');
        $this->line('  - 7+ settings');
        $this->line('  - 23 content blocks');
        if (!$this->option('skip-team')) {
            $this->line('  - 3 team members');
        }
        if (!$this->option('skip-favicon')) {
            $this->line('  - Favicon (SVG gradient)');
        }
        $this->line('  - ~96 blog variations (backwards compatibility)');

        $templateCount = \App\Models\BlogTemplate::where('niche', $input['niche'])->count();
        if ($templateCount > 0) {
            $this->line("  - {$templateCount} general blog templates");
        }

        $productTemplateCount = \App\Models\ProductBlogTemplate::where('niche', $input['niche'])->count();
        if ($productTemplateCount > 0) {
            $this->line("  - {$productTemplateCount} product blog templates");
        }

        $informationPageCount = \App\Models\InformationPage::where('is_active', true)->count();
        if ($informationPageCount > 0) {
            $this->line("  - {$informationPageCount} information pages");
        }

        if (!$this->option('skip-seed-blogs')) {
            $this->line('  - 3 seed blog post ideas (draft)');
        }
        $this->newLine();

        $this->info('Next steps:');
        $this->line('  1. Configure .env with BOL_SITE_ID: ' . ($input['bol_site_id'] ?? '[not set]'));

        if (!$this->option('fetch-products')) {
            $this->line('  2. Fetch products: php artisan fetch:bol-products');
        }

        $this->line('  3. Enable cron jobs on Forge/server:');
        $this->line('     - php artisan schedule:run (every minute)');
        $this->line('  4. Visit your site: https://' . $input['domain']);
        $this->newLine();

        $this->info('Site is ready to go live!');

        // Show Forge deployment info if applicable
        if ($this->forgeDeployment) {
            $this->newLine();
            $this->info('Forge Deployment Info:');
            $this->line("  - Site ID: {$this->forgeDeployment['site_id']}");
            $this->line("  - Domain: https://{$this->forgeDeployment['domain']}");
            $this->line("  - Database: {$this->forgeDeployment['database']['name']}");
            if (isset($this->forgeDeployment['ssl_id'])) {
                $this->line("  - SSL: Active (Let's Encrypt)");
            }
        }
    }

    /**
     * Validate Forge configuration
     */
    protected function validateForgeConfiguration(): bool
    {
        try {
            $this->forgeService = app(ForgeService::class);
            $this->info('Forge API credentials validated successfully.');
            return true;
        } catch (\Exception $e) {
            $this->error('Forge configuration error: ' . $e->getMessage());
            $this->warn('Make sure FORGE_API_TOKEN and FORGE_SERVER_ID are set in your .env file.');
            return false;
        }
    }

    /**
     * Deploy site to Laravel Forge
     */
    protected function deployToForge(array $input): void
    {
        $this->info('========================================');
        $this->info('  DEPLOYING TO FORGE');
        $this->info('========================================');
        $this->newLine();

        try {
            $siteName = $input['domain'];

            $this->info('Step 1/9: Creating site on Forge...');
            $this->line("Domain: {$input['domain']}");

            $deployment = $this->forgeService->createSite([
                'domain' => $input['domain'],
                'niche' => $input['niche'],
                'site_name' => $siteName,
                'bol_site_id' => $input['bol_site_id'] ?? null,
                'bol_category_id' => $input['bol_category_id'] ?? null,
                'primary_color' => $input['primary_color'],
            ]);

            $this->forgeDeployment = $deployment;

            $this->newLine();
            $this->info('========================================');
            $this->info('  FORGE DEPLOYMENT COMPLETE!');
            $this->info('========================================');
            $this->newLine();
            $this->info("Site created: https://{$deployment['domain']}");
            $this->info("Site ID: {$deployment['site_id']}");
            $this->info("Database: {$deployment['database']['name']}");
            $this->newLine();

        } catch (\Exception $e) {
            $this->error('Forge deployment failed: ' . $e->getMessage());
            $this->forgeDeployment = null;
        }
    }

}
