<?php

namespace App\Console\Commands;

use App\Services\ExerciseCatalog\ExerciseCatalog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Laravel\Ai\Files;
use Laravel\Ai\Image;

#[Signature('app:generate-vertical-shot {gender} {exercise}')]
#[Description('Generate a vertical exercise image from the catalog.')]
class GenerateVerticalExerciseShotImage extends Command
{
    /**
     * @param  array<string, mixed>  $exercise
     */
    private function prompt(array $exercise): string
    {
        $base = '
Create a vertical (4:5) high-resolution fitness studio photograph of the attached athlete model performing:
[EXERCISE_NAME]

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

### STYLE:
Professional fitness photography
Sharp subject focus, especially glutes and legs
Clean commercial look
No artifacts, no extra limbs, no warped equipment';

        return str_replace(
            ['[EXERCISE_NAME]', '[EQUIPMENT]', '[INSTRUCTIONS]'],
            [
                $exercise['name'],
                $this->equipment($exercise),
                $this->instructions($exercise),
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

        $image = Image::of($this->prompt($exercise))
            ->attachments([
                Files\Image::fromPath(resource_path("assetModels/{$gender}.png")),
            ])
            ->portrait()
            ->generate();

        $image->storeAs('vertical/'.$exerciseId.'_'.$gender.'.jpeg');

        return self::SUCCESS;
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
}
