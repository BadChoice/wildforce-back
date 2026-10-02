<?php

namespace App\Services\Workouts;

use App\Ai\Agents\Workouts\SingleWorkoutFromTextAgent;
use App\Models\User;
use App\Models\WorkoutDay;
use App\Services\ExerciseCatalog\ExerciseCatalog;
use Illuminate\Support\Collection;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;

final class SingleWorkoutFromTextGenerator
{
    public function __construct(
        private readonly ExerciseCatalog $exerciseCatalog,
        private readonly WorkoutDayResponseMapper $workoutDayResponseMapper,
    ) {}

    public function generate(User $user, string $workoutText): WorkoutDay
    {
        $exercises = collect($this->exerciseCatalog->all()['exercises'] ?? [])
            ->filter(fn (mixed $exercise): bool => is_array($exercise))
            ->values();

        if ($exercises->isEmpty()) {
            throw new RuntimeException('Workout generation from text requires an exercise catalog.');
        }

        $response = (new SingleWorkoutFromTextAgent($exercises->pluck('id')->all()))->prompt(
            $this->prompt($workoutText, $exercises),
        );

        if (! $response instanceof StructuredAgentResponse) {
            throw new RuntimeException('The workout text generator did not return structured data.');
        }

        return $this->workoutDayResponseMapper->make($user, $response->toArray());
    }

    /** @param Collection<int, array<string, mixed>> $exercises */
    private function prompt(string $workoutText, Collection $exercises): string
    {
        $exerciseList = $exercises
            ->map(fn (array $exercise): string => $exercise['id'].' | '.$exercise['name'])
            ->implode("\n");

        return <<<PROMPT
## Workout text
```text
{$workoutText}
```

## Exercise catalog
```text
ID | name
{$exerciseList}
```
PROMPT;
    }
}
