<?php

use App\Ai\Agents\Workouts\SingleWorkoutFromTextAgent;
use App\Models\User;
use App\Services\Workouts\SingleWorkoutFromTextGenerator;

test('converts free-form workout text into an unsaved workout day', function () {
    $user = User::factory()->create();
    SingleWorkoutFromTextAgent::fake([[
        'title' => 'Back and biceps',
        'focus' => 'back',
        'dayType' => 'hypertrophy',
        'estimatedDurationMinutes' => 55,
        'blocks' => [[
            'type' => 'standard',
            'rounds' => 1,
            'exercises' => [[
                'exercise' => 'bentOverRow',
                'sets' => 3,
                'repsMin' => 8,
                'repsMax' => 12,
                'targetWeightKg' => 59,
                'restSeconds' => 90,
            ]],
        ]],
    ]])->preventStrayPrompts();

    $workoutDay = app(SingleWorkoutFromTextGenerator::class)->generate($user, '1. Remo Tandem — 59 kg — 3 series');

    expect($workoutDay->exists)->toBeFalse()
        ->and($workoutDay->title)->toBe('Back and biceps')
        ->and($workoutDay->blocks->first()->exercises->first()->exercise)->toBe('bentOverRow')
        ->and($workoutDay->blocks->first()->exercises->first()->target_weight_kg)->toBe('59.00');

    $this->assertDatabaseCount('workout_days', 0);

    SingleWorkoutFromTextAgent::assertPrompted(fn ($prompt): bool => $prompt
        ->contains('Remo Tandem')
        && $prompt->contains('## Exercise catalog'));
});
