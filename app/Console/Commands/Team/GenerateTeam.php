<?php

namespace App\Console\Commands\Team;

use App\Models\BlogPost;
use App\Models\Review;
use App\Models\TeamMember;
use App\Services\OpenAIService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenAI;

class GenerateTeam extends Command
{
    protected $signature = 'team:generate {--force : Regenerate team even if it already exists}';

    protected $description = 'Generate 3 fictional team members with AI-generated profiles, bios, and photos';

    protected OpenAIService $openAI;

    public function __construct(OpenAIService $openAIService)
    {
        parent::__construct();
        $this->openAI = $openAIService;
    }

    public function handle()
    {
        $this->info('🚀 Starting team generation...');
        $this->newLine();

        // Check if team already exists
        $existingCount = TeamMember::count();
        if ($existingCount > 0 && !$this->option('force')) {
            $this->warn("⚠️  Team already exists ({$existingCount} members).");
            $this->warn("Use --force to regenerate.");
            return Command::FAILURE;
        }

        if ($existingCount > 0 && $this->option('force')) {
            $this->warn("⚠️  Deleting existing team members...");

            // First, set team_member_id to null on all blogs and reviews
            BlogPost::whereNotNull('team_member_id')->update(['team_member_id' => null]);
            Review::whereNotNull('team_member_id')->update(['team_member_id' => null]);

            // Now we can delete team members
            TeamMember::query()->delete();
        }

        // Get site context
        $niche = getSetting('site_niche', 'producten');
        $siteName = getSetting('site_name', config('app.name'));

        $this->info("📋 Site: {$siteName}");
        $this->info("🎯 Niche: {$niche}");
        $this->newLine();

        // Generate 3 team member profiles
        $this->info('🤖 Generating team member profiles via OpenAI...');
        $teamProfiles = $this->generateTeamProfiles($niche, $siteName);

        if (empty($teamProfiles)) {
            $this->error('❌ Failed to generate team profiles');
            return Command::FAILURE;
        }

        $this->info("✅ Generated " . count($teamProfiles) . " team profiles");
        $this->newLine();

        // Create each team member
        $bar = $this->output->createProgressBar(count($teamProfiles));
        $bar->setFormat('Creating team members: %current%/%max% [%bar%] %message%');

        foreach ($teamProfiles as $index => $profile) {
            $bar->setMessage("Generating {$profile['name']}...");

            try {
                $teamMember = $this->createTeamMember($profile, $niche, $siteName);
                $bar->advance();
                $this->newLine();
                $this->info("✅ Created: {$teamMember->name} ({$teamMember->role})");
            } catch (\Exception $e) {
                $bar->advance();
                $this->newLine();
                $this->error("❌ Failed to create {$profile['name']}: {$e->getMessage()}");
            }
        }

        $bar->finish();
        $this->newLine(2);

        // Summary
        $finalCount = TeamMember::count();
        $this->info("🎉 Team generation complete!");
        $this->info("📊 Total team members: {$finalCount}");
        $this->newLine();

        // Show team members
        $this->table(
            ['Name', 'Role', 'Focus', 'Tone'],
            TeamMember::all(['name', 'role', 'focus', 'tone'])->toArray()
        );

        $this->newLine();
        $this->info("🌐 View team at: " . url('/team'));

        // Assign team members to existing content
        $this->newLine();
        $this->info("🔗 Assigning team members to existing content...");
        $this->assignExistingContent();

        return Command::SUCCESS;
    }

    /**
     * Assign team members to existing blogs and reviews that don't have one yet
     */
    protected function assignExistingContent(): void
    {
        $teamMembers = TeamMember::all();

        if ($teamMembers->isEmpty()) {
            $this->warn("⚠️  No team members found to assign");
            return;
        }

        // Assign to blogs
        $blogsWithoutTeam = BlogPost::whereNull('team_member_id')->get();
        foreach ($blogsWithoutTeam as $blog) {
            $blog->team_member_id = $teamMembers->random()->id;
            $blog->save();
        }

        // Assign to reviews
        $reviewsWithoutTeam = Review::whereNull('team_member_id')->get();
        foreach ($reviewsWithoutTeam as $review) {
            $review->team_member_id = $teamMembers->random()->id;
            $review->save();
        }

        $totalAssigned = $blogsWithoutTeam->count() + $reviewsWithoutTeam->count();

        if ($totalAssigned > 0) {
            $this->info("✅ Assigned team members to {$blogsWithoutTeam->count()} blogs and {$reviewsWithoutTeam->count()} reviews");
        } else {
            $this->info("✅ All content already has team members assigned");
        }
    }

    /**
     * Generate 3 team member profiles using OpenAI
     */
    protected function generateTeamProfiles(string $niche, string $siteName): array
    {
        // Random seed to ensure different teams per site
        $randomSeed = time() . rand(1000, 9999);

        $prompt = <<<PROMPT
Sie sind ein Branding-Spezialist, der realistische, glaubwürdige fiktive Teammitglieder für eine deutsche Affiliate-Website erstellt.

KONTEXT:
- Website: {$siteName}
- Nische: {$niche}
- Ziel: E-E-A-T durch menschliches Gesicht für Content verbessern
- Random seed: {$randomSeed} (für Variation verwenden)

RECHTLICHE ANFORDERUNGEN (KRITISCH):
- Dies sind FIKTIVE PERSONAS - redaktionelle Charaktere, keine echten Personen
- Sie dürfen KEINE persönlichen Erfahrungen als Verbraucher beanspruchen ("Ich benutze das seit 3 Monaten")
- Sie sind "Redaktionsmitglieder", "Tester", "Produktliebhaber" - NICHT Konsumenten
- Der Ton muss immer redaktionell sein: "Laut Lisa...", "Das Team testet...", "Unsere Erfahrung..."

DIVERSITÄTSANFORDERUNGEN:
- 3 Teammitglieder mit unterschiedlichen Perspektiven
- Mix aus Geschlechtern und Altersgruppen
- Jedes Mitglied hat einzigartigen Expertisefokus:
  * Mitglied 1: Benutzerfreundlichkeit & praktische Anwendungen
  * Mitglied 2: Technische Spezifikationen & Leistung
  * Mitglied 3: Nachhaltigkeit & Design
- Unterschiedliche Schreibstile für Variation im Content

NAMENSSTRATEGIE:
- Verwenden Sie NUR deutsche Vornamen (keine Nachnamen)
- Wählen Sie ZUFÄLLIGE, variierende Namen aus dieser Liste: Lisa, Max, Emma, Felix, Sophie, Lars, Nina, Paul, Anna, Tim, Eva, Jan, Julia, Ben, Lotte, Finn, Sara, Tom, Mila, Luca, Hannah, Leon, Luna, Noah, Marie, Lukas, Evi, Robin, Lea, Elias, Nora, Lucas, Clara, Jonas, Lynn, Oskar, Liv, David, Mia, Anton, Ella, Theo, Ida, Emil, Pia, Hugo, Romy, Leo, Leni, Carl, Greta, Moritz, Frida, Jakob, Emilia, Simon, Amelie, Vincent, Charlotte, Rafael, Lina, Samuel, Elena, Julius, Marlene, Matteo, Johanna, Gabriel, Frieda, Adrian, Martha, Raphael, Rosa, Maximilian, Claire, Sebastian, Elisa, Valentin, Hanna, Jonathan, Zoe, Konstantin, Vera, Alexander, Leonie, Benedikt, Mathilda, Dominik, Alma, Philip, Isabel, Leonard, Rosalie, Heinrich, Maja, Friedrich, Laura, Wilhelm, Paula, Caspar, Ida, Leopold, Thea
- Wählen Sie 3 VERSCHIEDENE Namen, die Sie noch NIE zuvor verwendet haben
- Mix aus männlichen und weiblichen Namen
- NICHT: Lisa Müller, Max Schmidt (zu spezifisch/formell)

TON & CHARAKTER:
- Freundlich und zugänglich
- Sachkundig aber nicht akademisch
- Begeistert über die Nische
- Aufrichtig helfen wollen beim Auswahlprozess

OUTPUT: JSON-Array mit genau 3 Teammitgliedern nach diesem Schema:

[
  {
    "name": "Deutscher Vorname",
    "role": "Kurze Rolle wie 'Produktliebhaber', 'Tester', 'Spezialist'",
    "quote": "Ein inspirierender Satz über ihre Motivation (max 100 Zeichen)",
    "focus": "Benutzerfreundlichkeit|Technik|Nachhaltigkeit",
    "tone": "Kurze Beschreibung des Schreibstils z.B. 'freundlich und praktisch'",
    "personality": "Kurze Charakterbeschreibung in 1-2 Sätzen",
    "photo_prompt": "Portrait description for DALL-E matching the German name (z.B. 'German woman in her 30s' oder 'German man in his 40s'). Be specific about ethnicity to match typical German names.",
    "ethnicity": "caucasian|german-european (match typical appearance for this German name)"
  }
]

BEISPIELE FÜR GUTE QUOTES:
- "Ich helfe dir, die beste Wahl zu treffen, ohne technischen Schnickschnack"
- "Ehrliche Beratung darüber, was wirklich in der Praxis funktioniert"
- "Produkte, die das Leben einfacher machen – dafür setze ich mich ein"

Geben Sie NUR minified JSON zurück, kein Markdown, kein Kommentar.
PROMPT;

        try {
            $response = $this->openAI->generateFromPrompt($prompt, 'gpt-4o-mini');

            // Clean up response
            $response = trim($response);
            $response = preg_replace('/^```(?:json)?\s*/', '', $response);
            $response = preg_replace('/\s*```$/', '', $response);
            $response = preg_replace('/^[^[]*/', '', $response);
            $response = preg_replace('/\][^\]]*$/', ']', $response);

            $profiles = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($profiles) || count($profiles) !== 3) {
                $this->error("JSON decode error: " . json_last_error_msg());
                $this->line("Raw response: " . substr($response, 0, 500));
                return [];
            }

            return $profiles;

        } catch (\Exception $e) {
            $this->error("OpenAI error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Create a single team member with photo and full bio
     */
    protected function createTeamMember(array $profile, string $niche, string $siteName): TeamMember
    {
        // Skip photo generation - use letter circles instead
        $photoUrl = null;

        // Generate full bio (900-1200 words)
        $this->line("  ✍️  Generating full bio (900-1200 words)...");
        $bio = $this->generateFullBio($profile, $niche, $siteName);

        // Create team member
        $teamMember = TeamMember::create([
            'name' => $profile['name'],
            'role' => $profile['role'],
            'quote' => $profile['quote'],
            'focus' => $profile['focus'],
            'tone' => $profile['tone'],
            'bio' => $bio,
            'photo_url' => $photoUrl,
        ]);

        return $teamMember;
    }

    /**
     * Generate profile photo using DALL-E
     */
    protected function generateProfilePhoto(string $prompt, string $name): ?string
    {
        try {
            $client = OpenAI::client(config('services.openai.key'));

            // Raw unpolished webcam photo
            $enhancedPrompt = "Webcam screenshot from video call. {$prompt}. Age 30-45, average Dutch person working from home. Taken with laptop webcam, slightly grainy low-res quality. Wearing whatever they had on that day (old sweater, basic t-shirt, hoodie). Not smiling much, just normal resting face or slight polite smile. Unstyled hair, natural bedhead or ponytail. Real skin with visible texture, no makeup or minimal. Boring plain background (white wall, basic room, blurry background). Bad webcam lighting from laptop screen glow or window behind. Colors slightly off, webcam auto-white-balance. Image compression artifacts. Looks exactly like Zoom meeting screenshot or Teams profile picture. Zero effort put into this photo. NOT posed, NOT styled, NOT good lighting, NOT high quality. Just grabbed from webcam during regular workday.";

            $response = $client->images()->create([
                'model' => 'dall-e-3',
                'prompt' => $enhancedPrompt,
                'size' => '1024x1024', // DALL-E 3 only supports 1024x1024, 1792x1024, or 1024x1792
                'quality' => 'standard',
                'n' => 1,
            ]);

            $imageUrl = $response->data[0]->url ?? null;

            if (!$imageUrl) {
                $this->warn("  ⚠️  No image URL returned for {$name}");
                return null;
            }

            // Download and save the image
            $imageContent = file_get_contents($imageUrl);
            if (!$imageContent) {
                $this->warn("  ⚠️  Failed to download image for {$name}");
                return null;
            }

            // Compress image to reduce file size (convert to JPG with 85% quality)
            $image = @imagecreatefromstring($imageContent);
            if ($image !== false) {
                // Resize to 512x512 for profile photos (plenty for circular avatars)
                $resized = imagescale($image, 512, 512);

                // Save as JPG with compression
                ob_start();
                imagejpeg($resized, null, 85); // 85% quality - good balance
                $compressedContent = ob_get_clean();

                imagedestroy($image);
                imagedestroy($resized);

                $filename = 'team/' . Str::slug($name) . '-' . time() . '.jpg';
                Storage::disk('public')->put($filename, $compressedContent);
            } else {
                // Fallback: save original if compression fails
                $filename = 'team/' . Str::slug($name) . '-' . time() . '.png';
                Storage::disk('public')->put($filename, $imageContent);
            }

            return '/storage/' . $filename;

        } catch (\Exception $e) {
            $this->warn("  ⚠️  Photo generation failed for {$name}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate full bio (900-1200 words) for profile page
     */
    protected function generateFullBio(array $profile, string $niche, string $siteName): string
    {
        $currentYear = date('Y');

        $prompt = <<<PROMPT
Sie sind ein Content-Writer, der eine glaubwürdige, SEO-optimierte Profilseite für ein fiktives Redaktionsmitglied schreibt.

RECHTLICHE COMPLIANCE (KRITISCH):
- Dies ist eine FIKTIVE PERSONA - redaktioneller Charakter, keine echte Person
- NIEMALS persönliche Erfahrungen als Verbraucher beanspruchen ("Ich kaufte meinen ersten...")
- WOHL redaktionelle Expertise zeigen ("Als Tester des Teams...")
- Immer professioneller, redaktioneller Ton - KEINE persönlichen Geschichten als Benutzer

KONTEXT:
- Name: {$profile['name']}
- Rolle: {$profile['role']}
- Charakter: {$profile['personality']}
- Fokus: {$profile['focus']}
- Schreibstil: {$profile['tone']}
- Zitat: "{$profile['quote']}"
- Website: {$siteName}
- Nische: {$niche}

SEO-ZIELE:
- Länge: 900-1200 Wörter (für Autorität)
- Longtail-Keywords: "Experte {$niche}", "Spezialist {$niche}", "Tester {$niche}"
- E-E-A-T: Expertise zeigen, Erfahrung als REDAKTEUR/TESTER, Vertrauenswürdigkeit
- Konversion: 3-4 interne Links zu /produkte, /top-5, /testberichte

STRUKTUR (HTML verwenden):

<div class="team-bio">
  <div class="intro">
    <p>Packende Einführung (100-150 Wörter): Wer ist {$profile['name']}, was macht sie bei {$siteName}, was ist ihre Rolle im Team?</p>
  </div>

  <div class="passion">
    <h2>Leidenschaft für {$niche}</h2>
    <p>Warum {$profile['name']} begeistert ist von {$niche} (150-200 Wörter). Was macht diese Nische besonders? Was treibt sie als Redakteurin/Testerin an?</p>
  </div>

  <div class="expertise">
    <h2>Expertise & Arbeitsweise</h2>
    <p>Wie testet {$profile['name']} Produkte? (200-250 Wörter) Worauf achtet sie bei {$profile['focus']}? Was ist ihr einzigartiger Ansatz? Konkrete Beispiele, worauf sie bei Testberichten/Vergleichen achtet.</p>
  </div>

  <div class="approach">
    <h2>Für wen {$profile['name']} schreibt</h2>
    <p>Welche Zielgruppe hilft sie? (150-200 Wörter) Für welchen Benutzer-/Käufertyp sind ihre Ratschläge am wertvollsten? Warum lesen Menschen ihren Content?</p>
  </div>

  <div class="tips">
    <h2>Einblicke & Ratschläge</h2>
    <p>Praktische Tipps zu {$niche} (200-250 Wörter). Konkrete Ratschläge für diejenigen, die {$niche} suchen. Was sind häufige Fehler? Worauf sollte man beim Kauf achten?</p>
  </div>

  <div class="closing">
    <h2>{$profile['name']}'s Artikel finden</h2>
    <p>Abschluss (100-150 Wörter). {$profile['name']} schreibt Testberichte und Vergleiche für {$siteName}. Link zu ihrer Arbeit, Einladung zum Lesen des Contents.</p>
    <p><strong>Sehen Sie sich alle <a href="/produkte">Produkte</a>, <a href="/top-5">Top-5-Listen</a> und <a href="/testberichte">Testberichte</a> an, zu denen {$profile['name']} beigetragen hat.</strong></p>
  </div>
</div>

TON & STIL:
- Schreibstil: {$profile['tone']}
- Persönlich aber professionell
- Redaktionelle Autorität (keine Verbrauchergeschichten!)
- Hilfsbereit und zugänglich
- Konversionsorientiert (subtile CTAs)

VERBOTEN:
- Keine persönlichen Kaufentscheidungen als Verbraucher ("Als ich meinen ersten {$niche} kaufte...")
- Keine falschen Behauptungen über Zertifikate/Ausbildungen
- Kein spezifisches Alter/Wohnort/Privatdetails
- Keine Emojis

VERPFLICHTEND:
- 3-4 interne Links (relative URLs verwenden: /produkte, /top-5, /testberichte, /blog)
- H2-Überschriften für Struktur
- Absätze von 100+ Wörtern
- Natürliche Keyword-Integration
- Redaktionelle Expertise zeigen

Geben Sie NUR den HTML-Content zurück, kein JSON, keine Markdown-Wrapper.
PROMPT;

        try {
            $bio = $this->openAI->generateFromPrompt($prompt, 'gpt-4o-mini');

            // Clean up any markdown artifacts
            $bio = trim($bio);
            $bio = preg_replace('/^```(?:html)?\s*/', '', $bio);
            $bio = preg_replace('/\s*```$/', '', $bio);

            // Validate HTML structure
            if (empty($bio) || !str_contains($bio, '<div')) {
                $this->warn("  ⚠️  Bio generation returned invalid HTML, using fallback");
                return $this->getFallbackBio($profile, $niche, $siteName);
            }

            return $bio;

        } catch (\Exception $e) {
            $this->warn("  ⚠️  Bio generation failed: " . $e->getMessage());
            return $this->getFallbackBio($profile, $niche, $siteName);
        }
    }

    /**
     * Fallback bio if AI generation fails
     */
    protected function getFallbackBio(array $profile, string $niche, string $siteName): string
    {
        return <<<HTML
<div class="team-bio">
  <div class="intro">
    <p>{$profile['name']} ist {$profile['role']} bei {$siteName} und spezialisiert sich auf {$niche}. Mit Blick auf {$profile['focus']} hilft {$profile['name']} Besuchern, die richtige Wahl zu treffen.</p>
  </div>

  <div class="passion">
    <h2>Leidenschaft für {$niche}</h2>
    <p>{$profile['name']} ist leidenschaftlich bei {$niche} und testet regelmäßig die neuesten Produkte. {$profile['quote']}</p>
  </div>

  <div class="expertise">
    <h2>Expertise & Arbeitsweise</h2>
    <p>Beim Testen von {$niche} achtet {$profile['name']} besonders auf {$profile['focus']}. Jedes Produkt wird gründlich getestet und anhand praktischer Kriterien bewertet.</p>
  </div>

  <div class="closing">
    <h2>Artikel von {$profile['name']}</h2>
    <p>Sehen Sie sich alle <a href="/produkte">Produkte</a>, <a href="/top-5">Top-5-Listen</a> und <a href="/testberichte">Testberichte</a> an, zu denen {$profile['name']} beigetragen hat.</p>
  </div>
</div>
HTML;
    }
}
