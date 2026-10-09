<?php

namespace App\Http\Controllers\Api\BodyProgress;

use App\Ai\Agents\BodyProgress\BodyCompositionPhotoAnalysisAgent;
use App\Http\Controllers\Controller;
use App\Models\BodyProgressPhotoSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Files\Base64Image;
use Laravel\Ai\Responses\StructuredAgentResponse;

final class BodyCompositionAnalysisController extends Controller
{
    public function __invoke(Request $request, BodyProgressPhotoSession $bodyProgressPhotoSession): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('update', $bodyProgressPhotoSession);

        $photos = collect([
            'front' => $bodyProgressPhotoSession->front_photo_path,
            'profile' => $bodyProgressPhotoSession->profile_photo_path,
        ])->filter(fn (?string $path): bool => $path !== null && Storage::exists($path));

        if ($photos->isEmpty()) {
            throw ValidationException::withMessages([
                'photos' => ['At least one uploaded body progress photo is required for analysis.'],
            ]);
        }

        set_time_limit(120);

        $response = (new BodyCompositionPhotoAnalysisAgent($user->language ?? 'en'))->prompt(
            $this->prompt($user, $bodyProgressPhotoSession, $photos->keys()->all()),
            $photos->map(fn (string $path): Base64Image => new Base64Image(base64_encode(Storage::get($path)), 'image/jpeg'))->all(),
        );

        if (! $response instanceof StructuredAgentResponse) {
            abort(502, 'The AI provider did not return structured JSON.');
        }

        return response()->json(['data' => $response->toArray()]);
    }

    /** @param list<string> $availableAngles */
    private function prompt(User $user, BodyProgressPhotoSession $bodyProgressPhotoSession, array $availableAngles): string
    {
        $trainingPreferences = $user->trainingPreferences()->first();
        $goal = $trainingPreferences?->goal ?? 'Not specified';
        $trainingLevel = $trainingPreferences?->general_training_level ?? 'Not specified';
        $bodyCompositionPhase = $trainingPreferences?->body_composition_phase ?? 'Not specified';
        $lifestyle = $trainingPreferences?->lifestyle ?? 'Not specified';

        return <<<PROMPT
Analyse the attached body-progress photo set from {$bodyProgressPhotoSession->created_at->toDateString()}.

Photo input:
- Available angles: {$this->formattedAngles($availableAngles)}
- If both angles are present, compare cues across both photos before estimating the range.
- Clothing, pose, lighting, and image quality can reduce certainty.

User context for recommendation tailoring:
- Goal: {$goal}
- Training level: {$trainingLevel}
- Body composition phase: {$bodyCompositionPhase}
- Lifestyle: {$lifestyle}
PROMPT;
    }

    /** @param list<string> $availableAngles */
    private function formattedAngles(array $availableAngles): string
    {
        return implode(', ', $availableAngles);
    }
}
