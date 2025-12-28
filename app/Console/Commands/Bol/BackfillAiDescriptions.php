<?php

namespace App\Console\Commands\Bol;

use App\Models\Product;
use App\Services\OpenAIService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BackfillAiDescriptions extends Command
{
    protected $signature = 'app:backfill-ai-descriptions {--limit=0 : Maximaal aantal te verwerken records (0 = alles)}';

    protected $description = 'Genereer of normaliseer AI-beschrijvingen voor producten';

    public function __construct(protected OpenAIService $openAI)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        // We nemen ALLE producten en beslissen per record wat we doen:
        // - als ai_description_html leeg is => AI genereren
        // - als ai_description_html plain text is => gratis normaliseren
        $totalCandidates = Product::count();
        if ($totalCandidates === 0) {
            $this->info('Geen producten gevonden.');

            return self::SUCCESS;
        }

        $this->info('Backfill/normalisatie gestart.');
        $targetCount = $limit > 0 ? min($limit, $totalCandidates) : $totalCandidates;

        $bar = $this->output->createProgressBar($targetCount);
        $bar->start();

        $siteName = getSetting('site_name', config('app.name'));
        $siteNiche = getSetting('site_niche', '');

        $processed = 0;

        Product::query()
            ->orderBy('id')
            ->chunkById(50, function ($products) use (&$processed, $targetCount, $limit, $bar, $siteName, $siteNiche) {
                foreach ($products as $product) {
                    if ($limit > 0 && $processed >= $limit) {
                        return false; // stop chunking
                    }

                    try {
                        $ai = (string) ($product->ai_description_html ?? '');
                        $hasAi = trim($ai) !== '';

                        // 1) Leeg => AI genereren
                        if (! $hasAi) {
                            $source = $product->source_description ?: $product->description ?: '';

                            // Specs samenvatten (max 12)
                            $specPairs = [];
                            foreach ($product->specifications()->take(50)->get() as $spec) {
                                if (count($specPairs) >= 12) {
                                    break;
                                }
                                $val = is_array($spec->value) ? json_encode($spec->value, JSON_UNESCAPED_UNICODE) : (string) $spec->value;
                                $specPairs[$spec->name] = $val;
                            }

                            $rewrite = $this->openAI->rewriteProductDescription([
                                'title' => $product->title ?? '',
                                'brand' => $product->brand ?? '',
                                'niche' => $siteNiche,
                                'source_description' => $source,
                                'specs' => $specPairs,
                                'site_name' => $siteName,
                                // 'model' => 'gpt-4o-mini', // optioneel override
                            ]);

                            $product->ai_description_html = $rewrite['html'] ?? null;
                            $product->ai_summary = $rewrite['summary'] ?? null;
                            $product->rewritten_at = now();
                            $product->rewrite_model = $rewrite['model'] ?? 'gpt-4o-mini';
                            $product->rewrite_version = 'v1';

                            // Plain description fallback voor meta/snippets
                            if (empty($product->description)) {
                                $product->description = strip_tags($product->ai_summary ?: Str::limit(strip_tags($source), 150));
                            }

                            $product->save();

                            // Vriendelijke throttle alleen bij AI-calls
                            usleep(120000);
                        }
                        // 2) Bestaat maar is plain text => gratis normaliseren (geen AI-call)
                        elseif ($ai === strip_tags($ai)) {
                            $normalized = $this->normalizePlainTextToHtml($ai);
                            $product->ai_description_html = $normalized;
                            $product->save();
                        }

                        $processed++;
                        $bar->advance();
                    } catch (\Throwable $e) {
                        $this->error("Fout bij product ID {$product->id}: {$e->getMessage()}");
                        $processed++;
                        $bar->advance();
                    }

                    if ($processed >= $targetCount) {
                        return false; // stop chunking
                    }
                }
            });

        $bar->finish();
        $this->newLine(2);
        $this->info("Backfill klaar. Verwerkt: {$processed} product(en).");

        return self::SUCCESS;
    }

    /**
     * Zet plain text (met NL-kopjes) om naar simpele semantische HTML.
     * Gratis normalisatie, dus geen extra tokens. Houdt het compact en veilig.
     */
    protected function normalizePlainTextToHtml(string $text): string
    {
        $lines = preg_split("/\R+/", trim($text)) ?: [];
        $html = [];
        $inSpecs = false;
        $inFaq = false;
        $specItems = [];

        $openSection = function () use (&$html) {
            if (empty($html)) {
                $html[] = '<section>';
            }
        };
        $closeSection = function () use (&$html, &$inSpecs, &$specItems) {
            if ($inSpecs) {
                $html[] = '<ul>';
                foreach ($specItems as $li) {
                    $html[] = '<li>'.e($li).'</li>';
                }
                $html[] = '</ul>';
                $inSpecs = false;
                $specItems = [];
            }
            if (! empty($html) && substr(end($html), -10) !== '</section>') {
                $html[] = '</section>';
            }
        };

        $headingMap = [
            'introductie' => 'h2',
            'belangrijkste voordelen' => 'h2',
            'wie kiest voor dit model?' => 'h3',
            'gebruik & praktische tips' => 'h2',
            'specificaties in mensentaal' => 'h2',
            'veelgestelde vragen' => 'h2',
            'conclusie' => 'h2',
        ];

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);
            if ($line === '') {
                continue;
            }

            $lower = mb_strtolower($line);

            // CTA-regel
            if (str_starts_with($lower, 'cta:')) {
                $openSection();
                $html[] = '<p><strong>CTA:</strong> '.e(trim(mb_substr($line, 4))).'</p>';

                continue;
            }

            // FAQ-vraag in FAQ-sectie
            if ($inFaq && str_ends_with($line, '?')) {
                $openSection();
                $html[] = '<h3>'.e($line).'</h3>';

                continue;
            }

            // Kopjes
            $matchedHeading = null;
            foreach ($headingMap as $label => $tag) {
                if ($lower === $label) {
                    $matchedHeading = [$tag, $label];
                    break;
                }
            }

            if ($matchedHeading) {
                // Sluit eventueel lopende specs-lijst
                if ($inSpecs) {
                    $html[] = '<ul>';
                    foreach ($specItems as $li) {
                        $html[] = '<li>'.e($li).'</li>';
                    }
                    $html[] = '</ul>';
                    $inSpecs = false;
                    $specItems = [];
                }

                $openSection();
                [$tag, $label] = $matchedHeading;
                $html[] = "<{$tag}>".e($line)."</{$tag}>";

                // Modi
                $inSpecs = ($label === 'specificaties in mensentaal');
                $inFaq = ($label === 'veelgestelde vragen');

                continue;
            }

            // Specregel "Naam: waarde"
            if ($inSpecs && preg_match('/^[^:]{2,}:\s*.+$/u', $line)) {
                $specItems[] = $line;

                continue;
            }

            // Standaard paragraaf
            $openSection();
            $html[] = '<p>'.e($line).'</p>';
        }

        $closeSection();

        if (empty($html)) {
            $paras = array_filter(preg_split("/\R{2,}/", $text) ?: []);
            if ($paras) {
                $out = '<section>';
                foreach ($paras as $p) {
                    $out .= '<p>'.e(trim($p)).'</p>';
                }
                $out .= '</section>';

                return $out;
            }

            return '<section><p>'.e($text).'</p></section>';
        }

        return implode("\n", $html);
    }
}
