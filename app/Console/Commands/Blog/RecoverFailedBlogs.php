<?php

namespace App\Console\Commands\Blog;

use App\Models\BlogPost;
use Illuminate\Console\Command;

class RecoverFailedBlogs extends Command
{
    protected $signature = 'app:recover-failed-blogs {--dry-run : Show what would be recovered without actually doing it}';

    protected $description = 'Recover failed blog posts from temporary storage and save them to database';

    public function handle()
    {
        $failedBlogsPath = storage_path('app/failed_blogs');

        if (!is_dir($failedBlogsPath)) {
            $this->info('💡 Geen failed blogs directory gevonden. Niets te herstellen.');
            return;
        }

        $files = glob($failedBlogsPath . '/blog_*.json');

        if (empty($files)) {
            $this->info('💡 Geen failed blogs gevonden om te herstellen.');
            return;
        }

        $this->info("🔄 Gevonden " . count($files) . " failed blog(s) om te herstellen...");

        $recovered = 0;
        $failed = 0;

        foreach ($files as $file) {
            $this->line("📄 Verwerking: " . basename($file));

            $data = json_decode(file_get_contents($file), true);

            if (!$data) {
                $this->error("❌ Ongeldige JSON in {$file}");
                $failed++;
                continue;
            }

            // Check if blog with same title already exists
            if (BlogPost::where('title', $data['title'])->exists()) {
                $this->warn("⚠️ Blog '{$data['title']}' bestaat al. Overslaan.");

                if (!$this->option('dry-run')) {
                    unlink($file); // Remove processed file
                }
                continue;
            }

            if ($this->option('dry-run')) {
                $this->info("🔍 [DRY-RUN] Zou blog herstellen: {$data['title']}");
                continue;
            }

            try {
                $blogPost = BlogPost::create([
                    'product_id' => $data['product_id'],
                    'title' => $data['title'],
                    'slug' => $data['slug'],
                    'content' => $data['content'],
                    'excerpt' => $data['excerpt'],
                    'type' => $data['type'],
                    'status' => 'published',
                    'meta_title' => $data['meta_title'],
                    'meta_description' => $data['meta_description'],
                    'intro' => null,
                    'main_content' => null,
                    'benefits' => null,
                    'usage_tips' => null,
                    'closing' => null,
                ]);

                $this->info("✅ Blog hersteld met ID: {$blogPost->id}");

                // Remove successfully processed file
                unlink($file);
                $recovered++;

            } catch (\Exception $e) {
                $this->error("❌ Kon blog niet herstellen: " . $e->getMessage());
                $failed++;
            }
        }

        if ($this->option('dry-run')) {
            $this->info("🔍 [DRY-RUN] Zou {$recovered} blog(s) herstellen.");
        } else {
            $this->info("🎉 Herstel voltooid: {$recovered} succesvol, {$failed} gefaald.");
        }
    }
}