<?php

namespace App\Console\Commands\Maintenance;

use App\Models\BlogPost;
use Illuminate\Console\Command;

class CleanupFallbackBlogs extends Command
{
    protected $signature = 'blogs:cleanup-fallback {--dry-run : Toon alleen welke blogs verwijderd zouden worden}';

    protected $description = 'Verwijder oude fallback blogs die geen content hebben';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $this->info('🔍 Zoeken naar fallback blogs...');

        // Vind fallback blogs op basis van verschillende markers
        // Simpele checks zonder JSON_EXTRACT (om errors te vermijden bij invalid JSON)
        $fallbackBlogs = BlogPost::where(function($query) {
            $query->where('intro', 'Er is een fout opgetreden bij het genereren van content.')
                  ->orWhere('title', 'like', 'Content generatie mislukt%')
                  ->orWhere('title', 'like', 'Blog in afwachting van configuratie%')
                  ->orWhere('content', 'like', '%"sections":[]%')
                  ->orWhere('content', 'like', '%"is_fallback":true%');
        })->get();

        if ($fallbackBlogs->isEmpty()) {
            $this->info('✅ Geen fallback blogs gevonden!');
            return 0;
        }

        $this->warn("⚠️  Gevonden: {$fallbackBlogs->count()} fallback blogs");
        $this->newLine();

        // Toon lijst
        $this->table(
            ['ID', 'Titel', 'Type', 'Created'],
            $fallbackBlogs->map(fn($blog) => [
                $blog->id,
                substr($blog->title, 0, 50),
                $blog->type,
                $blog->created_at->format('Y-m-d H:i'),
            ])
        );

        if ($dryRun) {
            $this->newLine();
            $this->info('🔍 Dry-run mode - geen blogs verwijderd');
            $this->info("💡 Run zonder --dry-run om te verwijderen:");
            $this->line("   php artisan blogs:cleanup-fallback");
            return 0;
        }

        // Bevestiging
        if (!$this->confirm("Wil je deze {$fallbackBlogs->count()} fallback blogs verwijderen?", true)) {
            $this->warn('Geannuleerd door gebruiker');
            return 0;
        }

        // Verwijder
        $deleted = 0;
        foreach ($fallbackBlogs as $blog) {
            $this->line("🗑️  Verwijderen: {$blog->title} (ID: {$blog->id})");
            $blog->delete();
            $deleted++;
        }

        $this->newLine();
        $this->success("✅ {$deleted} fallback blogs succesvol verwijderd!");

        return 0;
    }
}
