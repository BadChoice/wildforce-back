<?php

use App\Enums\SubscriptionPlan;
use App\Models\CoachExerciseContent;
use App\Models\CoachingEnrollment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->disk = Storage::fake((string) config('filesystems.public_storage_disc'));
    $this->coach = User::factory()->create();
    $this->coach->subscription()->delete();
    Subscription::factory()->for($this->coach)->create(['plan' => SubscriptionPlan::CoachBasic]);
    CoachingEnrollment::factory()->create(['coach_user_id' => $this->coach->id]);
});

test('users without active clients do not get the coach content tab', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('exercises.catalog')
        ->call('selectExercise', 'walking')
        ->assertDontSee('Coach content')
        ->call('selectExerciseTab', 'coach')
        ->assertSet('selectedExerciseTab', 'details')
        ->call('saveCoachExerciseContent')
        ->assertForbidden();

    expect(CoachExerciseContent::query()->count())->toBe(0);
});

test('a coach saves an image and a YouTube video for an exercise', function () {
    Livewire::actingAs($this->coach)
        ->test('exercises.catalog')
        ->call('selectExercise', 'walking')
        ->assertSee('Coach content')
        ->call('selectExerciseTab', 'coach')
        ->assertSet('selectedExerciseTab', 'coach')
        ->set('coachExerciseImage', UploadedFile::fake()->image('walking.jpg', 1080, 1350))
        ->set('coachExerciseYoutubeUrl', 'https://youtu.be/dQw4w9WgXcQ')
        ->set('coachExerciseNotes', 'Keep your ribs down.')
        ->call('saveCoachExerciseContent')
        ->assertHasNoErrors()
        ->assertSet('coachExerciseYoutubeUrl', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');

    $media = $this->coach->coachExerciseContent()->sole();

    expect($media->exercise)->toBe('walking')
        ->and($media->youtube_video_id)->toBe('dQw4w9WgXcQ')
        ->and($media->notes)->toBe('Keep your ribs down.')
        ->and($media->image_path)->toStartWith('coach-exercise-images/'.$this->coach->id.'/walking-');
    $this->disk->assertExists($media->image_path);
});

test('notes keep coach content from being soft deleted', function () {
    Livewire::actingAs($this->coach)
        ->test('exercises.catalog')
        ->call('selectExercise', 'walking')
        ->set('coachExerciseNotes', 'Keep a conversational pace.')
        ->call('saveCoachExerciseContent');

    expect($this->coach->coachExerciseContent()->sole())
        ->image_path->toBeNull()
        ->youtube_video_id->toBeNull()
        ->notes->toBe('Keep a conversational pace.');
});

test('replacing the image deletes the previous file', function () {
    $component = Livewire::actingAs($this->coach)
        ->test('exercises.catalog')
        ->call('selectExercise', 'walking')
        ->set('coachExerciseImage', UploadedFile::fake()->image('first.jpg'))
        ->call('saveCoachExerciseContent');
    $previousImagePath = $this->coach->coachExerciseContent()->sole()->image_path;

    $component->set('coachExerciseImage', UploadedFile::fake()->image('second.jpg'))
        ->call('saveCoachExerciseContent');

    $this->disk->assertMissing($previousImagePath);
    $this->disk->assertExists($this->coach->coachExerciseContent()->sole()->image_path);
});

test('an invalid YouTube link is rejected', function () {
    Livewire::actingAs($this->coach)
        ->test('exercises.catalog')
        ->call('selectExercise', 'walking')
        ->call('selectExerciseTab', 'coach')
        ->set('coachExerciseYoutubeUrl', 'https://vimeo.com/123')
        ->call('saveCoachExerciseContent')
        ->assertHasErrors(['coachExerciseYoutubeUrl'])
        ->assertSee('Enter a valid YouTube video URL.');

    expect(CoachExerciseContent::query()->count())->toBe(0);
});

test('an image that is too large is rejected', function () {
    Livewire::actingAs($this->coach)
        ->test('exercises.catalog')
        ->call('selectExercise', 'walking')
        ->set('coachExerciseImage', UploadedFile::fake()->image('huge.jpg')->size(3000))
        ->call('saveCoachExerciseContent')
        ->assertHasErrors(['coachExerciseImage' => 'max']);
});

test('removing the image and video soft deletes the content so clients sync the removal', function () {
    $media = CoachExerciseContent::factory()->withImage()->create([
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
        ->call('saveCoachExerciseContent');

    $this->disk->assertMissing($media->image_path);
    expect($media->fresh()->trashed())->toBeTrue();
});

test('saving content again restores previously removed content', function () {
    $media = CoachExerciseContent::factory()->create([
        'coach_user_id' => $this->coach->id,
        'exercise' => 'walking',
    ]);
    $media->delete();

    Livewire::actingAs($this->coach)
        ->test('exercises.catalog')
        ->call('selectExercise', 'walking')
        ->set('coachExerciseYoutubeUrl', 'https://www.youtube.com/watch?v=aaaaaaaaaaa')
        ->call('saveCoachExerciseContent');

    expect($media->fresh())
        ->trashed()->toBeFalse()
        ->youtube_video_id->toBe('aaaaaaaaaaa');
});

test('the exercise list marks the exercises with coach content', function () {
    CoachExerciseContent::factory()->create(['coach_user_id' => $this->coach->id, 'exercise' => 'walking']);
    $removedMedia = CoachExerciseContent::factory()->create(['coach_user_id' => $this->coach->id, 'exercise' => 'airSquat']);
    $removedMedia->delete();
    CoachExerciseContent::factory()->create(['exercise' => 'barbellBenchPress']);

    Livewire::actingAs($this->coach)
        ->test('exercises.catalog')
        ->set('search', 'walking')
        ->assertSee('Custom')
        ->set('search', 'airSquat')
        ->assertDontSee('Custom')
        ->set('search', 'barbellBenchPress')
        ->assertDontSee('Custom');
});
