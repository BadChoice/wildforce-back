<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Coaching\ClientInvitationService;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private ClientInvitationService $clientInvitationService) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'invitation' => ['nullable', 'string', 'max:255'],
        ])->validate();

        if (! empty($input['invitation'])) {
            return $this->clientInvitationService->register($input['invitation'], function () use ($input): User {
                return User::create([
                    'name' => $input['name'],
                    'email' => $input['email'],
                    'password' => $input['password'],
                ]);
            });
        }

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        $user->attachSubscription(Subscription::createTrial());

        return $user;
    }
}
