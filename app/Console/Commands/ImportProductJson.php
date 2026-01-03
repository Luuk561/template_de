<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductSpecification;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportProductJson extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'product:import-json {--file= : Path to JSON file} {--ssh : Import via SSH to production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Amazon product from JSON (extracted via bookmarklet)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Amazon Product Importer');
        $this->info('======================');
        $this->newLine();

        // Get JSON input
        $json = null;

        if ($this->option('file')) {
            // Read from file
            $filePath = $this->option('file');
            if (!file_exists($filePath)) {
                $this->error("File not found: {$filePath}");
                return 1;
            }
            $json = file_get_contents($filePath);
        } else {
            // Ask user to paste JSON
            $this->info('Paste the product JSON (from bookmarklet) and press Enter twice:');
            $this->newLine();

            $lines = [];
            while (true) {
                $line = trim(fgets(STDIN));
                if ($line === '' && count($lines) > 0) {
                    break;
                }
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
            $json = implode("\n", $lines);
        }

        if (empty($json)) {
            $this->error('No JSON provided');
            return 1;
        }

        // Parse JSON
        $data = json_decode($json, true);
        if (!$data) {
            $this->error('Invalid JSON format');
            return 1;
        }

        // Validate required fields
        $required = ['title', 'asin', 'amazon_affiliate_link'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $this->error("Missing required field: {$field}");
                return 1;
            }
        }

        $this->newLine();
        $this->info('Product data received:');
        $this->line("  Title: {$data['title']}");
        $this->line("  ASIN: {$data['asin']}");
        $this->line("  Brand: " . ($data['brand'] ?? 'N/A'));
        $this->line("  Images: " . count($data['images'] ?? []));
        $this->line("  Specs: " . count($data['specs'] ?? []));
        $this->newLine();

        if ($this->option('ssh')) {
            return $this->importViaSSH($data);
        }

        // Check if product already exists
        $existing = Product::where('asin', $data['asin'])->first();
        if ($existing) {
            if (!$this->confirm("Product with ASIN {$data['asin']} already exists (ID: {$existing->id}). Update it?", false)) {
                $this->info('Import cancelled');
                return 0;
            }
        }

        // Import product
        return $this->importProduct($data, $existing);
    }

    protected function importProduct(array $data, ?Product $existing = null): int
    {
        $this->info('Importing product...');

        // Create or update product
        $product = $existing ?? new Product();

        $product->title = $data['title'];
        $product->slug = $data['slug'] ?? Str::slug($data['title']);
        $product->brand = $data['brand'] ?? null;
        $product->asin = $data['asin'];
        $product->ean = $data['ean'] ?? "ASIN-{$data['asin']}";
        $product->amazon_affiliate_link = $data['amazon_affiliate_link'];
        $product->description = $data['description'] ?? null;
        $product->rating_average = $data['rating_average'] ?? null;
        $product->rating_count = $data['rating_count'] ?? null;

        // Images (only first one to image_url)
        if (!empty($data['images'][0])) {
            $product->image_url = $data['images'][0];
        }

        $product->save();

        $this->info("Product saved with ID: {$product->id}");

        // Import specifications
        if (!empty($data['specs'])) {
            $this->info('Importing specifications...');

            // Delete existing specs if updating
            if ($existing) {
                ProductSpecification::where('product_id', $product->id)->delete();
            }

            $specCount = 0;
            foreach ($data['specs'] as $key => $value) {
                // Skip empty values
                if (empty($value) || $value === '-') {
                    continue;
                }

                // Determine group based on key
                $group = $this->determineSpecGroup($key);

                ProductSpecification::create([
                    'product_id' => $product->id,
                    'name' => $this->formatSpecKey($key),
                    'value' => $value,
                    'group' => $group,
                ]);

                $specCount++;
            }

            $this->info("Imported {$specCount} specifications");
        }

        $this->newLine();
        $this->info('Import successful!');
        $this->line("Product ID: {$product->id}");
        $this->line("URL: /produkte/{$product->slug}");

        return 0;
    }

    protected function importViaSSH(array $data): int
    {
        $this->info('SSH import mode');
        $this->newLine();

        // Create temporary JSON file
        $tempFile = storage_path('app/temp-product-import.json');
        file_put_contents($tempFile, json_encode($data, JSON_PRETTY_PRINT));

        $server = $this->ask('SSH server (e.g., forge@46.224.108.150)', 'forge@46.224.108.150');
        $path = $this->ask('Site path (e.g., /home/forge/bestelaufband.de)', '/home/forge/bestelaufband.de');

        $this->info('Uploading product data to server...');

        // Upload JSON to server
        $uploadCommand = "scp {$tempFile} {$server}:{$path}/storage/app/product-import.json";
        exec($uploadCommand, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->error('Failed to upload to server');
            unlink($tempFile);
            return 1;
        }

        // Execute import on server
        $this->info('Importing on production...');
        $sshCommand = "ssh {$server} \"cd {$path} && php artisan product:import-json --file=storage/app/product-import.json\"";

        passthru($sshCommand, $returnCode);

        // Cleanup
        unlink($tempFile);
        exec("ssh {$server} \"rm {$path}/storage/app/product-import.json\"");

        return $returnCode;
    }

    protected function determineSpecGroup(string $key): string
    {
        $key = strtolower($key);

        // Common spec groups
        $groups = [
            'algemeen' => ['marke', 'hersteller', 'modell', 'farbe', 'material', 'gewicht', 'abmessungen', 'grosse'],
            'motor' => ['motor', 'leistung', 'watt', 'ps'],
            'laufband' => ['laufflache', 'geschwindigkeit', 'steigung', 'incline', 'belt'],
            'training' => ['programme', 'training', 'herzfrequenz', 'pulsmessung'],
            'display' => ['display', 'bildschirm', 'anzeige', 'monitor'],
            'konnektivitat' => ['bluetooth', 'wifi', 'app', 'konnektivitat'],
            'sonstiges' => ['batterie', 'garantie', 'lieferumfang', 'zertifizierung'],
        ];

        foreach ($groups as $group => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($key, $keyword)) {
                    return $group;
                }
            }
        }

        return 'sonstiges';
    }

    protected function formatSpecKey(string $key): string
    {
        // Convert underscores to spaces and capitalize
        return ucwords(str_replace('_', ' ', $key));
    }
}
