<?php

namespace App\Services\Auth;

use App\Models\Subscription;
use App\Models\TrainingLocation;
use App\Models\TrainingPreference;
use App\Models\User;
use App\Models\UserAppSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class InitialRegistrationService
{
    /** @param array<string, array<string, mixed>> $initialData */
    public function register(array $initialData, string $email, ?string $password = null): User
    {
        return DB::transaction(function () use ($initialData, $email, $password): User {
            $userData = $initialData['user'];
            $user = new User;
            $user->forceFill([
                ...$userData,
                'email' => strtolower($email),
                'password' => $password === null ? null : Hash::make($password),
            ]);
            $user->save();

            $user->trainingPreferences()->save((new TrainingPreference)->forceFill($initialData['training_preferences']));
            $user->trainingLocations()->save((new TrainingLocation)->forceFill($initialData['training_location']));
            $user->appSettings()->save((new UserAppSettings)->forceFill($initialData['app_settings']));
            $user->attachSubscription(Subscription::createTrial());

            return $user->load('subscription');
        });
    }
}
