<?php

namespace App\Console\Commands;

use App\Services\ExerciseCatalog\ExerciseCatalog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Files;
use Laravel\Ai\Image;

#[Signature('app:generate-vertical-shot {gender} {exercise}')]
#[Description('Generate a vertical exercise image from the catalog.')]
class GenerateVerticalExerciseShotImage extends Command
{
    /**
     * @param  array<string, mixed>  $exercise
     * @param  array<string, mixed>|null  $biomechanics
     */
    private function prompt(array $exercise, ?array $biomechanics = null): string
    {
        $base = '
Create a vertical (4:5) high-resolution fitness studio photograph.

Use the EXACT SAME PERSON from the attached athlete reference image as the athlete.
This is an identity-preservation task, not a request to create a new fitness model.

When a second exercise tutorial reference image is attached, use it only to
accurately reproduce the exercise pose, equipment setup, and biomechanics.
It must not override the athlete identity reference.

The referenced athlete is performing:

[EXERCISE_NAME]

[BIOMECHANICS]

### FRAMING & LAYOUT (STRICT):
Portrait orientation (4:5 ratio)
Athlete centered horizontally
Athlete occupies ~50–60% of image height (do NOT fill the frame)
Leave clear empty space at the top (~20–25%) and bottom (~20–25%) for text overlays
Full body visible at all times (no cropping of limbs)
Camera angle: 3/4 view, facing slightly right

### ENVIRONMENT:
Modern home gym, industrial Nordic style
Concrete + white brick walls
Matte black fitness equipment
Minimal, clean, uncluttered background

### EQUIPMENT:
[EQUIPMENT]

### LIGHTING:
Natural daylight from a window (side lighting)
Bright, soft, diffused light
Slightly desaturated tones
Background slightly blurred (shallow depth of field)

### EXERCISE EXECUTION (CRITICAL – MUST BE CORRECT):
Exercise: [EXERCISE_NAME]
- Demonstrate perfect beginner-safe form
- Biomechanics must be accurate and realistic
- No exaggerated or unsafe positions
- Only one person in the image, no spotter or background person.

These are the instructions of the exercise:
[INSTRUCTIONS]


### SUBJECT INTEGRATION:
Correct anatomical proportions (no distortions)
Realistic interaction with equipment
Consistent lighting and shadows with environment
No texts at all

### ATHLETE IDENTITY — CRITICAL:
The attached athlete image is the identity reference for the person in the generated photograph.

You MUST depict the EXACT SAME PERSON shown in the attached reference image.

Preserve her/his identity with very high fidelity:
- same face and facial structure
- same eyes, nose, mouth and jawline
- same apparent age
- same skin tone
- same hair color, hairstyle and hair length
- same body type and proportions
- same overall physical appearance

Do NOT create a different fitness model.
Do NOT beautify, reinterpret, or redesign the person.
Do NOT change facial features.
Do NOT change apparent age.
Do NOT change hair or body type.

The exercise, pose, camera angle, clothing and environment may change,
but the athlete\'s identity must remain visually consistent with the attached reference.

IDENTITY PRESERVATION HAS HIGH PRIORITY.

### EQUIPMENT PHYSICS — CRITICAL:
All exercise equipment must be mechanically realistic and physically connected.

Cables, ropes, bars, straps, pulleys and handles must form physically
possible connections.

For cable exercises:
- Every cable must have a clear origin and attachment point.
- A cable must be one continuous line between attachment points.
- Cables must remain under realistic tension.
- Cables must never pass through the athlete, limbs, clothing or equipment.
- Never create duplicate, branching, floating or disconnected cables.
- Pulley routing must be mechanically plausible.

### STYLE:
Professional fitness photography
Sharp subject focus, especially glutes and legs
Clean commercial look
No artifacts, no extra limbs, no warped equipment';

        return str_replace(
            ['[EXERCISE_NAME]', '[EQUIPMENT]', '[INSTRUCTIONS]', '[BIOMECHANICS]'],
            [
                $exercise['name'],
                $this->equipment($exercise),
                $this->instructions($exercise),
                $this->biomechanics($biomechanics),
            ],
            $base,
        );
    }

    /**
     * Execute the console command.
     */
    public function handle(ExerciseCatalog $exerciseCatalog): int
    {
        $gender = $this->argument('gender');
        $exerciseId = $this->argument('exercise');
        $exercise = $exerciseCatalog->exercise($exerciseId);

        if ($exercise === null) {
            $this->components->error("Exercise [{$exerciseId}] was not found in the catalog.");

            return self::FAILURE;
        }

        $biomechanics = $exerciseCatalog->biomechanics($exerciseId);

        $image = Image::of($this->prompt($exercise, $biomechanics))
            ->attachments($this->attachments($gender, $exerciseId))
            ->portrait()
            //->generate();
            ->generate(provider: Lab::OpenAI);

        $image->storeAs('vertical/'.$exerciseId.'_'.$gender.'.jpeg');

        return self::SUCCESS;
    }

    /**
     * @return array<int, Files\Image>
     */
    private function attachments(string $gender, string $exerciseId): array
    {
        $attachments = [
            Files\Image::fromPath(resource_path("assetModels/{$gender}.png")),
        ];

        $tutorialReference = resource_path("assetModels/exerciseTutorialReferences/{$exerciseId}.png");

        if (is_file($tutorialReference)) {
            $attachments[] = Files\Image::fromPath($tutorialReference);
        }

        return $attachments;
    }

    /**
     * @param  array<string, mixed>  $exercise
     */
    private function equipment(array $exercise): string
    {
        $equipment = array_map(
            fn (string $equipment): string => Str::headline($equipment),
            $exercise['requiredEquipment'],
        );

        return $equipment === [] ? 'No required equipment' : implode(', ', $equipment);
    }

    /**
     * @param  array<string, mixed>  $exercise
     */
    private function instructions(array $exercise): string
    {
        return implode("\n", [...$exercise['instructions'], ...$exercise['tips']]);
    }

    /**
     * @param  array<string, mixed>|null  $biomechanics
     */
    private function biomechanics(?array $biomechanics): string
    {
        if (empty($biomechanics)) {
            return '';
        }

        return <<<PROMPT
### VISUAL BIOMECHANICS — STRICT

The following constraints define exactly how the exercise must
look in the generated image.

These constraints have priority over generic assumptions about
the exercise name.

{$this->formatBiomechanics($biomechanics)}

IMPORTANT:
- Follow these biomechanical constraints literally.
- The final pose must clearly communicate the specified exercise.
- Do not substitute a visually similar exercise.
- Anything listed under MUST NOT must not appear in the image.
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function formatBiomechanics(
        array $data,
        int $level = 0
    ): string {
        $lines = [];

        foreach ($data as $key => $value) {
            $label = Str::headline($key);

            if ($key === 'mustNot') {
                $label = 'MUST NOT';
            }

            if (is_array($value)) {
                if (array_is_list($value)) {
                    $lines[] = strtoupper($label).':';

                    foreach ($value as $item) {
                        $lines[] = "- {$item}";
                    }
                } else {
                    $lines[] = strtoupper($label).':';

                    foreach ($value as $subKey => $subValue) {
                        $subLabel = Str::headline($subKey);
                        $lines[] = "- {$subLabel}: {$subValue}";
                    }
                }

                continue;
            }

            $lines[] = strtoupper($label).": {$value}";
        }

        return implode("\n", $lines);
    }
}
