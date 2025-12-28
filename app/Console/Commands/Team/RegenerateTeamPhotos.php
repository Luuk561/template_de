<?php

namespace App\Console\Commands\Team;

use App\Models\TeamMember;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenAI;

class RegenerateTeamPhotos extends Command
{
    protected $signature = 'team:regenerate-photos';
    protected $description = 'Regenerate only the profile photos for existing team members';

    public function handle()
    {
        $this->info('Starting photo regeneration...');
        $this->newLine();

        $teamMembers = TeamMember::all();

        if ($teamMembers->isEmpty()) {
            $this->warn('No team members found. Run team:generate first.');
            return Command::FAILURE;
        }

        $this->info("Found {$teamMembers->count()} team members");
        $this->newLine();

        $bar = $this->output->createProgressBar($teamMembers->count());
        $bar->setFormat('Regenerating photos: %current%/%max% [%bar%] %message%');

        foreach ($teamMembers as $member) {
            $bar->setMessage("Generating photo for {$member->name}...");

            try {
                // Delete old photo if exists
                if ($member->photo_url) {
                    $oldPath = str_replace('/storage/', '', $member->photo_url);
                    Storage::disk('public')->delete($oldPath);
                }

                // Generate new photo
                $photoPrompt = $this->generatePhotoPrompt($member);
                $newPhotoUrl = $this->generateProfilePhoto($photoPrompt, $member->name);

                if ($newPhotoUrl) {
                    $member->photo_url = $newPhotoUrl;
                    $member->save();
                    $bar->advance();
                    $this->newLine();
                    $this->info("Updated: {$member->name}");
                } else {
                    $bar->advance();
                    $this->newLine();
                    $this->warn("Failed: {$member->name}");
                }
            } catch (\Exception $e) {
                $bar->advance();
                $this->newLine();
                $this->error("Error for {$member->name}: {$e->getMessage()}");
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('Photo regeneration complete!');

        return Command::SUCCESS;
    }

    protected function generatePhotoPrompt(TeamMember $member): string
    {
        // Infer gender and age from name for better prompts
        $maleNames = ['Tom', 'Daan', 'Lars', 'Bas', 'Tim', 'Max', 'Finn', 'Sem', 'Thijs', 'Ruben', 'Jesse', 'Noah', 'Bram', 'Robin', 'Stijn', 'Lucas', 'Jasper', 'Owen', 'Sam', 'Nick', 'Quinn', 'Casper', 'Milan', 'Jayden'];
        $femaleNames = ['Lisa', 'Emma', 'Sophie', 'Nina', 'Anne', 'Eva', 'Julia', 'Lotte', 'Sara', 'Mila', 'Anna', 'Luna', 'Sanne', 'Evi', 'Isa', 'Noa', 'Fleur', 'Lynn', 'Tess', 'Liv', 'Fenna', 'Iris', 'Roos', 'Amy', 'Sofie', 'Lauren'];

        $gender = in_array($member->name, $maleNames) ? 'man' : (in_array($member->name, $femaleNames) ? 'woman' : 'person');

        return "Dutch {$gender} in their mid-30s";
    }

    protected function generateProfilePhoto(string $prompt, string $name): ?string
    {
        try {
            $client = OpenAI::client(config('services.openai.key'));

            // Raw unpolished webcam photo
            $enhancedPrompt = "Webcam screenshot from video call. {$prompt}. Age 30-45, average Dutch person working from home. Taken with laptop webcam, slightly grainy low-res quality. Wearing whatever they had on that day (old sweater, basic t-shirt, hoodie). Not smiling much, just normal resting face or slight polite smile. Unstyled hair, natural bedhead or ponytail. Real skin with visible texture, no makeup or minimal. Boring plain background (white wall, basic room, blurry background). Bad webcam lighting from laptop screen glow or window behind. Colors slightly off, webcam auto-white-balance. Image compression artifacts. Looks exactly like Zoom meeting screenshot or Teams profile picture. Zero effort put into this photo. NOT posed, NOT styled, NOT good lighting, NOT high quality. Just grabbed from webcam during regular workday.";

            $response = $client->images()->create([
                'model' => 'dall-e-3',
                'prompt' => $enhancedPrompt,
                'size' => '1024x1024',
                'quality' => 'standard',
                'n' => 1,
            ]);

            $imageUrl = $response->data[0]->url ?? null;

            if (!$imageUrl) {
                $this->warn("  No image URL returned for {$name}");
                return null;
            }

            // Download and compress
            $imageContent = file_get_contents($imageUrl);
            if (!$imageContent) {
                $this->warn("  Failed to download image for {$name}");
                return null;
            }

            // Compress to JPG
            $image = @imagecreatefromstring($imageContent);
            if ($image !== false) {
                $resized = imagescale($image, 512, 512);
                ob_start();
                imagejpeg($resized, null, 85);
                $compressedContent = ob_get_clean();
                imagedestroy($image);
                imagedestroy($resized);

                $filename = 'team/' . Str::slug($name) . '-' . time() . '.jpg';
                Storage::disk('public')->put($filename, $compressedContent);
            } else {
                $filename = 'team/' . Str::slug($name) . '-' . time() . '.png';
                Storage::disk('public')->put($filename, $imageContent);
            }

            return '/storage/' . $filename;

        } catch (\Exception $e) {
            $this->warn("  Photo generation failed for {$name}: " . $e->getMessage());
            return null;
        }
    }
}
