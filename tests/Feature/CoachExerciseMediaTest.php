<?php

use App\Models\CoachExerciseMedia;
use App\Models\CoachingEnrollment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->disk = Storage::fake((string) config('filesystems.public_storage_disc'));
    $this->coach = User::factory()->create();
    CoachingEnrollment::factory()->create(['coach_user_id' => $this->coach->id]);
});

test('users without active clients do not get the coach media tab', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('exercises.catalog')
        ->call('selectExercise', 'walking')
        ->assertDontSee('Coach media')
        ->call('selectExerciseTab', 'coach')
        ->assertSet('selectedExerciseTab', 'details')
        ->call('saveCoachExerciseMedia')
        ->assertForbidden();

    expect(CoachExerciseMedia::query()->count())->toBe(0);
});

test('a coach saves an image and a YouTube video for an exercise', function () {
    Livewire::actingAs($this->coach)
        ->test('exercises.catalog')
        ->call('selectExercise', 'walking')
        ->assertSee('Coach media')
        ->call('selectExerciseTab', 'coach')
        ->assertSet('selectedExerciseTab', 'coach')
        ->set('coachExerciseImage', UploadedFile::fake()->image('walking.jpg', 1080, 1350))
        ->set('coachExerciseYoutubeUrl', 'https://youtu.be/dQw4w9WgXcQ')
        ->call('saveCoachExerciseMedia')
        ->assertHasNoErrors()
        ->assertSet('coachExerciseYoutubeUrl', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');

    $media = $this->coach->coachExerciseMedia()->sole();

    expect($media->exercise)->toBe('walking')
        ->and($media->youtube_video_id)->toBe('dQw4w9WgXcQ')
        ->and($media->image_path)->toStartWith('coach-exercise-images/'.$this->coach->id.'/walking-');
    $this->disk->assertExists($media->image_path);
});

test('replacing the image deletes the previous file', function () {
    $component = Livewire::actingAs($this->coach)
        ->test('exercises.catalog')
        ->call('selectExercise', 'walking')
        ->set('coachExerciseImage', UploadedFile::fake()->image('first.jpg'))
        ->call('saveCoachExerciseMedia');
    $previousImagePath = $this->coach->coachExerciseMedia()->sole()->image_path;

    $component->set('coachExerciseImage', UploadedFile::fake()->image('second.jpg'))
        ->call('saveCoachExerciseMedia');

    $this->disk->assertMissing($previousImagePath);
    $this->disk->assertExists($this->coach->coachExerciseMedia()->sole()->image_path);
});

test('an invalid YouTube link is rejected', function () {
    Livewire::actingAs($this->coach)
        ->test('exercises.catalog')
        ->call('selectExercise', 'walking')
        ->call('selectExerciseTab', 'coach')
        ->set('coachExerciseYoutubeUrl', 'https://vimeo.com/123')
        ->call('saveCoachExerciseMedia')
        ->assertHasErrors(['coachExerciseYoutubeUrl'])
        ->assertSee('Enter a valid YouTube video URL.');

    expect(CoachExerciseMedia::query()->count())->toBe(0);
});

test('an image that is too large is rejected', function () {
    Livewire::actingAs($this->coach)
        ->test('exercises.catalog')
        ->call('selectExercise', 'walking')
        ->set('coachExerciseImage', UploadedFile::fake()->image('huge.jpg')->size(3000))
        ->call('saveCoachExerciseMedia')
        ->assertHasErrors(['coachExerciseImage' => 'max']);
});

test('removing the image and video soft deletes the media so clients sync the removal', function () {
    $media = CoachExerciseMedia::factory()->withImage()->create([
        'coach_user_id' => $this->coach->id,
        'exercise' => 'walking',
    ]);
    $this->disk->put($media->image_path, 'image');

    Livewire::actingAs($this->coach)
        ->test('exercises.catalog')
        ->call('selectExercise', 'walking')
        ->assertSet('coachExerciseYoutubeUrl', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        ->call('removeCoachExerciseImage')
        ->set('coachExerciseYoutubeUrl', '')
        ->call('saveCoachExerciseMedia');

    $this->disk->assertMissing($media->image_path);
    expect($media->fresh()->trashed())->toBeTrue();
});

test('saving media again restores previously removed media', function () {
    $media = CoachExerciseMedia::factory()->create([
        'coach_user_id' => $this->coach->id,
        'exercise' => 'walking',
    ]);
    $media->delete();

    Livewire::actingAs($this->coach)
        ->test('exercises.catalog')
        ->call('selectExercise', 'walking')
        ->set('coachExerciseYoutubeUrl', 'https://www.youtube.com/watch?v=aaaaaaaaaaa')
        ->call('saveCoachExerciseMedia');

    expect($media->fresh())
        ->trashed()->toBeFalse()
        ->youtube_video_id->toBe('aaaaaaaaaaa');
});
