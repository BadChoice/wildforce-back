<?php

use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Image;
use Laravel\Ai\Prompts\ImagePrompt;

test('generates a tutorial image using catalog exercise details', function () {
    $disk = Storage::fake();
    Image::fake();

    $this->artisan('app:generate-exercise-tutorial', [
        'exercise' => 'treadmillWalking',
    ])->assertSuccessful();

    Image::assertGenerated(fn (ImagePrompt $prompt): bool => $prompt->isLandscape()
        && $prompt->contains('Treadmill Walking')
        && $prompt->contains('Treadmill')
        && $prompt->contains('Step onto the treadmill and attach the safety clip to your clothing.')
        && $prompt->contains('Keep your eyes forward to maintain balance.'));

    $disk->assertExists('tutorials/treadmillWalking.jpeg');
});

test('rejects an exercise missing from the catalog', function () {
    Image::fake();

    $this->artisan('app:generate-exercise-tutorial', [
        'exercise' => 'missingExercise',
    ])->assertFailed();

    Image::assertNothingGenerated();
});
