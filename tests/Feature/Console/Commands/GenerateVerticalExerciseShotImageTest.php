<?php

use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Image;
use Laravel\Ai\Prompts\ImagePrompt;

test('generates a portrait image using catalog exercise details', function () {
    $disk = Storage::fake();
    Image::fake();

    $this->artisan('app:generate-vertical-shot', [
        'gender' => 'female',
        'exercise' => 'treadmillWalking',
    ])->assertSuccessful();

    Image::assertGenerated(fn (ImagePrompt $prompt): bool => $prompt->isPortrait()
        && $prompt->contains('Treadmill Walking')
        && $prompt->contains('Treadmill')
        && $prompt->contains('Step onto the treadmill and attach the safety clip to your clothing.')
        && $prompt->contains('Keep your eyes forward to maintain balance.'));

    $disk->assertExists('vertical/treadmillWalking_female.jpeg');
});

test('rejects an exercise missing from the catalog', function () {
    Image::fake();

    $this->artisan('app:generate-vertical-shot', [
        'gender' => 'female',
        'exercise' => 'missingExercise',
    ])->assertFailed();

    Image::assertNothingGenerated();
});
