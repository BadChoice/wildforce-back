<?php

namespace App\Http\Controllers\Api\Access;

use App\Enums\DemoCodeStatus;
use App\Enums\SubscriptionPlan;
use App\Http\Controllers\Controller;
use App\Models\DemoCode;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RedeemDemoCodeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255'],
        ]);
        $user = $request->user();
        $currentSubscription = $user->subscription()->first();

        if ($currentSubscription?->plan === SubscriptionPlan::Demo) {
            throw ValidationException::withMessages([
                'code' => 'A demo code has already been redeemed for this account.',
            ]);
        }

        if ($currentSubscription !== null && $currentSubscription->plan !== SubscriptionPlan::Trial) {
            throw ValidationException::withMessages([
                'code' => 'A demo code can only replace a trial subscription.',
            ]);
        }

        $demoCode = DB::transaction(function () use ($validated, $user): DemoCode {
            $demoCode = DemoCode::query()
                ->lockForUpdate()
                ->where('code', mb_strtoupper($validated['code']))
                ->first();

            if ($demoCode === null || $demoCode->status !== DemoCodeStatus::Active || $demoCode->expires_at?->isPast()) {
                throw ValidationException::withMessages([
                    'code' => 'This demo code is invalid or has expired.',
                ]);
            }

            $user->replaceSubscription(Subscription::createDemo($demoCode));

            return $demoCode;
        });

        return response()->json([
            'demo_code' => $demoCode->code,
            'demo_access_ends_at' => $user->fresh()->subscription->renews_at,
        ]);
    }
}
