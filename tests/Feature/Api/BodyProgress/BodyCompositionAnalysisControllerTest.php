<?php

use App\Ai\Agents\BodyProgress\BodyCompositionPhotoAnalysisAgent;
use App\Models\BodyProgressPhotoSession;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\Base64Image;

test('it returns a composition estimate using the private progress photos', function () {
    Storage::fake(config('filesystems.default'));
    $user = User::factory()->create(['language' => 'es']);
    $session = BodyProgressPhotoSession::factory()->for($user)->create([
        'front_photo_path' => 'body-progress-photos/front.jpg',
        'profile_photo_path' => 'body-progress-photos/profile.jpg',
    ]);
    Storage::put($session->front_photo_path, 'front-image');
    Storage::put($session->profile_photo_path, 'profile-image');
    BodyCompositionPhotoAnalysisAgent::fake([bodyCompositionAnalysisResponse()])->preventStrayPrompts();

    $response = $this->actingAs($user, 'sanctum')->postJson("/api/body-progress-photo-sessions/{$session->id}/composition-analysis", [], [
        'Idempotency-Key' => 'body-composition-analysis-1',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.estimatedBodyFatRange', '18%–21%')
        ->assertJsonPath('data.confidence', 'medium')
        ->assertJsonPath('data.insights.0', 'Moderate muscular definition is visible.');

    BodyCompositionPhotoAnalysisAgent::assertPrompted(fn ($prompt): bool => $prompt->contains('Available angles: front, profile')
        && $prompt->attachments->count() === 2
        && $prompt->attachments->every(fn (mixed $attachment): bool => $attachment instanceof Base64Image));
});

test('it returns 422 when the session has no uploaded progress photos', function () {
    $user = User::factory()->create();
    $session = BodyProgressPhotoSession::factory()->for($user)->create();
    BodyCompositionPhotoAnalysisAgent::fake()->preventStrayPrompts();

    $this->actingAs($user, 'sanctum')->postJson("/api/body-progress-photo-sessions/{$session->id}/composition-analysis", [], [
        'Idempotency-Key' => 'body-composition-analysis-no-photos',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['photos'])
        ->assertJsonPath('errors.photos.0', 'At least one uploaded body progress photo is required for analysis.');

    BodyCompositionPhotoAnalysisAgent::assertNeverPrompted();
});

test('it returns 404 when analysing another user\'s progress photo session', function () {
    $user = User::factory()->create();
    $session = BodyProgressPhotoSession::factory()->for(User::factory())->create();
    BodyCompositionPhotoAnalysisAgent::fake()->preventStrayPrompts();

    $this->actingAs($user, 'sanctum')->postJson("/api/body-progress-photo-sessions/{$session->id}/composition-analysis", [], [
        'Idempotency-Key' => 'body-composition-analysis-other-user',
    ])->assertNotFound();

    BodyCompositionPhotoAnalysisAgent::assertNeverPrompted();
});

test('it returns 401 when no token is provided', function () {
    $session = BodyProgressPhotoSession::factory()->for(User::factory())->create();

    $this->postJson("/api/body-progress-photo-sessions/{$session->id}/composition-analysis")
        ->assertUnauthorized();
});

/** @return array<string, mixed> */
function bodyCompositionAnalysisResponse(): array
{
    return [
        'estimatedBodyFatRange' => '18%–21%',
        'confidence' => 'medium',
        'summary' => 'The check-in shows a moderately lean appearance with some visible muscle definition.',
        'insights' => [
            'Moderate muscular definition is visible.',
            'Some softness remains through the midsection.',
        ],
        'recommendations' => [
            'A steady training routine can support gradual changes.',
            'Consistent nutrition habits may help maintain progress.',
        ],
    ];
}
