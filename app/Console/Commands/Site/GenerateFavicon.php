<?php

namespace App\Console\Commands\Site;

use Illuminate\Console\Command;

class GenerateFavicon extends Command
{
    protected $signature = 'generate:favicon';
    protected $description = 'Generate favicon SVG based on site niche first letter and primary color';

    public function handle()
    {
        $this->info('Generating favicon...');

        $niche = getSetting('site_niche', 'products');
        $primaryColor = getSetting('primary_color', '#3B82F6');

        // Get first letter of niche (uppercase)
        $letter = strtoupper(mb_substr($niche, 0, 1));

        $this->line("Niche: {$niche}");
        $this->line("Letter: {$letter}");
        $this->line("Primary Color: {$primaryColor}");
        $this->newLine();

        try {
            // Generate SVG
            $svg = $this->generateSvg($letter, $primaryColor);

            // Save to public root
            file_put_contents(public_path('favicon.svg'), $svg);

            $this->info('SVG favicon created!');

            // Update database setting to use the new favicon
            \App\Models\Setting::updateOrCreate(
                ['key' => 'favicon_url'],
                ['value' => '/favicon.svg']
            );

            $this->info('Database setting updated!');

            $this->newLine();
            $this->info('Favicon generated successfully!');
            $this->line('File created: public/favicon.svg');
            $this->line('Database updated: favicon_url = /favicon.svg');

            return 0;

        } catch (\Exception $e) {
            $this->error('Error generating favicon: ' . $e->getMessage());
            return 1;
        }
    }

    private function generateSvg(string $letter, string $primaryColor): string
    {
        // Calculate darker color for gradient
        $darkerColor = $this->darkenColor($primaryColor, 15);

        return <<<SVG
<svg width="512" height="512" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:{$primaryColor};stop-opacity:1" />
      <stop offset="100%" style="stop-color:{$darkerColor};stop-opacity:1" />
    </linearGradient>
  </defs>
  <rect width="512" height="512" rx="102" fill="url(#grad)"/>
  <text x="50%" y="50%" dominant-baseline="central" text-anchor="middle" font-family="system-ui, -apple-system, sans-serif" font-size="280" font-weight="700" fill="white">{$letter}</text>
</svg>
SVG;
    }

    private function darkenColor(string $hex, int $percent): string
    {
        $hex = ltrim($hex, '#');

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $r = max(0, $r - ($r * $percent / 100));
        $g = max(0, $g - ($g * $percent / 100));
        $b = max(0, $b - ($b * $percent / 100));

        return sprintf("#%02x%02x%02x", (int)$r, (int)$g, (int)$b);
    }
}
