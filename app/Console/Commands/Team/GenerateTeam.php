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
Je bent een branding specialist die realistische, geloofwaardige fictieve teamleden bedenkt voor een Nederlandse affiliate website.

CONTEXT:
- Website: {$siteName}
- Niche: {$niche}
- Doel: E-E-A-T verbeteren door menselijk gezicht aan content te geven
- Random seed: {$randomSeed} (gebruik dit voor variatie)

JURIDISCHE EISEN (KRITIEK):
- Dit zijn FICTIEVE PERSONA'S - redactionele karakters, geen echte mensen
- Ze mogen GEEN persoonlijke ervaringen claimen ("Ik gebruik dit al 3 maanden")
- Ze zijn "redactieleden", "testers", "productliefhebbers" - NIET consumenten
- Tone moet altijd redactioneel zijn: "Volgens Lisa...", "Het team test...", "Onze ervaring..."

DIVERSITEIT EISEN:
- 3 teamleden met verschillende perspectieven
- Mix van geslachten en leeftijdsgroepen
- Elk lid heeft unieke expertise focus:
  * Lid 1: Gebruiksgemak & praktische toepassingen
  * Lid 2: Technische specificaties & prestaties
  * Lid 3: Duurzaamheid & design
- Verschillende schrijfstijlen voor variatie in content

NAAM STRATEGIE:
- Gebruik ALLEEN Nederlandse voornamen (geen achternamen)
- Kies WILLEKEURIGE, gevarieerde namen uit deze lijst: Lisa, Daan, Emma, Tom, Sophie, Lars, Nina, Bas, Anne, Tim, Eva, Sem, Julia, Max, Lotte, Finn, Sara, Thijs, Mila, Ruben, Anna, Jesse, Luna, Noah, Sanne, Bram, Evi, Robin, Isa, Stijn, Noa, Lucas, Fleur, Jasper, Lynn, Tess, Owen, Liv, Sam, Fenna, Nick, Iris, Quinn, Roos, Casper, Amy, Milan, Sofie, Jayden, Lauren, Maud, Luuk, Saar, Bente, Dex, Koen, Lieke, Rick, Noor, Jelle, Renske, Guus, Merel, Ties, Indy, Teun, Floor, Dylan, Lise, Daniël, Charlotte, Stan, Eline, Jip, Femke, Gijs, Lize, Joep, Pien, David, Julie, Mats, Anouk, Jim, Lena, Lukas, Isabel, Dean, Lot, Benjamin, Marijn, Quinten, Amber, Olivier, Rosa, Thomas, Claire, Niels, Lisa, Mark, Iris, Pieter, Laura, Joran, Rosalie, Felix, Naomi, Brent, Hannah, Matthijs, Michelle, Kai, Kim, Alexander, Marit, Erik, Lieke, Stefan, Zoë, Jeroen, Vera, Tobias, Leonie, Wessel, Elise, Yannick, Danielle, Pepijn, Jade, Floris, Melanie, Joris, Annelies, Dion, Sandra, Roel, Patricia
- Kies 3 VERSCHILLENDE namen die je nog NOOIT eerder hebt gebruikt
- Mix van mannelijke en vrouwelijke namen
- NIET: Michelle van der Berg, Peter Jansen (te specifiek/formeel)

TOON & KARAKTER:
- Vriendelijk en toegankelijk
- Deskundig maar niet academisch
- Enthousiast over de niche
- Oprecht willen helpen met keuzeproces

OUTPUT: JSON array met exact 3 teamleden volgens dit schema:

[
  {
    "name": "Nederlandse voornaam",
    "role": "Korte rol zoals 'Productliefhebber', 'Tester', 'Specialist'",
    "quote": "Eén inspirerende zin over wat hen drijft (max 100 tekens)",
    "focus": "gebruiksgemak|techniek|duurzaamheid",
    "tone": "Korte beschrijving schrijfstijl bijv. 'vriendelijk en praktisch'",
    "personality": "Korte beschrijving karakter in 1-2 zinnen",
    "photo_prompt": "Portrait description for DALL-E matching the Dutch name (bijv. 'Dutch woman in her 30s' of 'Dutch man in his 40s'). Be specific about ethnicity to match typical Dutch names.",
    "ethnicity": "caucasian|dutch-european (match typical appearance for this Dutch name)"
  }
]

VOORBEELDEN VAN GOEDE QUOTES:
- "Ik help je de beste keuze maken zonder technische poespas"
- "Eerlijk advies over wat écht werkt in de praktijk"
- "Producten die het leven makkelijker maken, daar ga ik voor"

Return ALLEEN minified JSON, geen markdown, geen commentary.
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
Je bent een content writer die een geloofwaardige, SEO-geoptimaliseerde profielpagina schrijft voor een fictief redactielid.

JURIDISCHE COMPLIANCE (KRITIEK):
- Dit is een FICTIEVE PERSONA - redactioneel karakter, geen echte persoon
- NOOIT persoonlijke ervaringen claimen als consumer ("Ik kocht mijn eerste...")
- WEL redactionele expertise tonen ("Als tester van het team...")
- Altijd professionele, redactionele toon - GEEN persoonlijke verhalen als gebruiker

CONTEXT:
- Naam: {$profile['name']}
- Rol: {$profile['role']}
- Karakter: {$profile['personality']}
- Focus: {$profile['focus']}
- Schrijfstijl: {$profile['tone']}
- Quote: "{$profile['quote']}"
- Website: {$siteName}
- Niche: {$niche}

SEO DOELEN:
- Lengte: 900-1200 woorden (voor autoriteit)
- Longtail keywords: "expert {$niche}", "specialist {$niche}", "tester {$niche}"
- E-E-A-T: Toon expertise, ervaring als REDACTEUR/TESTER, vertrouwbaarheid
- Conversie: 3-4 interne links naar /producten, /top-5, /reviews

STRUCTUUR (gebruik HTML):

<div class="team-bio">
  <div class="intro">
    <p>Pakkende introductie (100-150 woorden): Wie is {$profile['name']}, wat doet ze bij {$siteName}, wat is haar rol in het team?</p>
  </div>

  <div class="passion">
    <h2>Passie voor {$niche}</h2>
    <p>Waarom {$profile['name']} gek is op {$niche} (150-200 woorden). Wat maakt deze niche bijzonder? Wat drijft haar als redacteur/tester?</p>
  </div>

  <div class="expertise">
    <h2>Expertise & Werkwijze</h2>
    <p>Hoe test {$profile['name']} producten? (200-250 woorden) Waar let ze op bij {$profile['focus']}? Wat is haar unieke aanpak? Concrete voorbeelden van waar ze op let bij reviews/vergelijkingen.</p>
  </div>

  <div class="approach">
    <h2>Voor Wie {$profile['name']} Schrijft</h2>
    <p>Welke doelgroep helpt ze? (150-200 woorden) Voor welk type gebruiker/koper is haar advies het meest waardevol? Waarom lezen mensen haar content?</p>
  </div>

  <div class="tips">
    <h2>Inzichten & Adviezen</h2>
    <p>Praktische tips over {$niche} (200-250 woorden). Concrete adviezen voor wie {$niche} zoekt. Wat zijn veelgemaakte fouten? Waar moet je op letten bij aankoop?</p>
  </div>

  <div class="closing">
    <h2>Vind {$profile['name']}'s Artikelen</h2>
    <p>Afsluiting (100-150 woorden). {$profile['name']} schrijft reviews en vergelijkingen voor {$siteName}. Link naar haar werk, uitnodiging om content te lezen.</p>
    <p><strong>Bekijk alle <a href="/producten">producten</a>, <a href="/top-5">top 5 lijsten</a> en <a href="/reviews">reviews</a> waar {$profile['name']} aan heeft bijgedragen.</strong></p>
  </div>
</div>

TOON & STIJL:
- Schrijfstijl: {$profile['tone']}
- Persoonlijk maar professioneel
- Redactionele autoriteit (geen consumer verhalen!)
- Behulpzaam en toegankelijk
- Conversiegericht (subtiele CTAs)

VERBODEN:
- Geen persoonlijke koopdecisies als consumer ("Toen ik mijn eerste {$niche} kocht...")
- Geen valse claims over certificaten/opleidingen
- Geen specifieke leeftijd/woonplaats/privédetails
- Geen emojis

VERPLICHT:
- 3-4 interne links (gebruik relatieve URLs: /producten, /top-5, /reviews, /blogs)
- H2 headings voor structuur
- Paragrafen van 100+ woorden
- Natuurlijke keyword integratie
- Redactionele expertise tonen

Return ALLEEN de HTML content, geen JSON, geen markdown wrappers.
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
    <p>{$profile['name']} is {$profile['role']} bij {$siteName} en specialiseert zich in {$niche}. Met oog voor {$profile['focus']} helpt {$profile['name']} bezoekers de juiste keuze te maken.</p>
  </div>

  <div class="passion">
    <h2>Passie voor {$niche}</h2>
    <p>{$profile['name']} is gepassioneerd over {$niche} en test regelmatig de nieuwste producten. {$profile['quote']}</p>
  </div>

  <div class="expertise">
    <h2>Expertise & Werkwijze</h2>
    <p>Bij het testen van {$niche} let {$profile['name']} vooral op {$profile['focus']}. Elk product wordt grondig getest en beoordeeld op basis van praktische criteria.</p>
  </div>

  <div class="closing">
    <h2>Artikelen van {$profile['name']}</h2>
    <p>Bekijk alle <a href="/producten">producten</a>, <a href="/top-5">top 5 lijsten</a> en <a href="/reviews">reviews</a> waar {$profile['name']} aan heeft bijgedragen.</p>
  </div>
</div>
HTML;
    }
}
