<?php

namespace App\Services\Coaching;

use App\Enums\CoachingEnrollmentStatus;
use App\Mail\ClientInvitationMail;
use App\Models\ClientInvitation;
use App\Models\CoachingEnrollment;
use App\Models\Subscription;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClientInvitationService
{
    public function invite(User $coach, string $email, ?string $message): ClientInvitation
    {
        $token = Str::random(64);

        $invitation = DB::transaction(function () use ($coach, $email, $message, $token): ClientInvitation {
            $coachSubscription = $coach->subscription()->lockForUpdate()->first();

            if (! $coachSubscription?->isActiveCoachSubscription()) {
                throw ValidationException::withMessages([
                    'invitationEmail' => [__('An active coach subscription is required to invite clients.')],
                ]);
            }

            $email = Str::lower($email);

            if ($coach->clients(CoachingEnrollmentStatus::Active)
                ->whereRaw('lower(users.email) = ?', [$email])
                ->exists()) {
                throw ValidationException::withMessages([
                    'invitationEmail' => [__('This person is already an active client.')],
                ]);
            }

            ClientInvitation::query()
                ->pending()
                ->where('coach_user_id', $coach->id)
                ->where('email', $email)
                ->lockForUpdate()
                ->update(['revoked_at' => now()]);

            $usedPlaces = CoachingEnrollment::query()
                ->where('coach_user_id', $coach->id)
                ->where('status', CoachingEnrollmentStatus::Active->value)
                ->count()
                + ClientInvitation::query()
                    ->pending()
                    ->where('coach_user_id', $coach->id)
                    ->count();

            if ($coachSubscription->active_client_limit !== null && $usedPlaces >= $coachSubscription->active_client_limit) {
                throw ValidationException::withMessages([
                    'invitationEmail' => [__('You have reached your client limit.')],
                ]);
            }

            return ClientInvitation::create([
                'coach_user_id' => $coach->id,
                'email' => $email,
                'message' => $message,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDays(7),
            ]);
        });

        $invitation->load('coach');
        $locale = $invitation->coach->language;

        Mail::to($invitation->email)
            ->locale(in_array($locale, array_keys(config('translations.locales')), true) ? $locale : config('app.fallback_locale'))
            ->queue(new ClientInvitationMail($invitation, $token));

        return $invitation;
    }

    public function pendingInvitation(string $token): ?ClientInvitation
    {
        return ClientInvitation::query()
            ->pending()
            ->with('coach')
            ->where('token_hash', hash('sha256', $token))
            ->first();
    }

    /**
     * @param  Closure(): User  $createUser
     */
    public function register(string $token, Closure $createUser): User
    {
        return DB::transaction(function () use ($token, $createUser): User {
            $invitation = $this->lockPendingInvitation($token);
            $user = $createUser();

            $this->accept($invitation, $user);

            return $user;
        });
    }

    public function acceptForUser(string $token, User $user): void
    {
        DB::transaction(function () use ($token, $user): void {
            $this->accept($this->lockPendingInvitation($token), $user);
        });
    }

    private function lockPendingInvitation(string $token): ClientInvitation
    {
        $invitation = ClientInvitation::query()
            ->pending()
            ->where('token_hash', hash('sha256', $token))
            ->lockForUpdate()
            ->first();

        if ($invitation === null) {
            throw ValidationException::withMessages([
                'invitation' => [__('This invitation is invalid or has expired.')],
            ]);
        }

        return $invitation;
    }

    private function accept(ClientInvitation $invitation, User $user): void
    {
        if (! hash_equals($invitation->email, Str::lower($user->email))) {
            throw ValidationException::withMessages([
                'invitation' => [__('This invitation belongs to a different email address.')],
            ]);
        }

        $coach = User::query()->whereKey($invitation->coach_user_id)->lockForUpdate()->first();

        if ($coach === null) {
            throw (new ModelNotFoundException)->setModel(User::class, [$invitation->coach_user_id]);
        }

        $coachSubscription = $coach->subscription()->lockForUpdate()->first();

        if (! $coachSubscription?->isActiveCoachSubscription()) {
            throw ValidationException::withMessages([
                'invitation' => [__('This coach can no longer accept clients.')],
            ]);
        }

        $enrollment = CoachingEnrollment::query()
            ->where('coach_user_id', $coach->id)
            ->where('client_user_id', $user->id)
            ->lockForUpdate()
            ->first();

        if ($enrollment?->status !== CoachingEnrollmentStatus::Active) {
            $activeClientCount = CoachingEnrollment::query()
                ->where('coach_user_id', $coach->id)
                ->where('status', CoachingEnrollmentStatus::Active->value)
                ->count();

            if ($coachSubscription->active_client_limit !== null && $activeClientCount >= $coachSubscription->active_client_limit) {
                throw ValidationException::withMessages([
                    'invitation' => [__('This coach has reached their client limit.')],
                ]);
            }

            if ($enrollment === null) {
                $enrollment = new CoachingEnrollment;
                $enrollment->forceFill([
                    'coach_user_id' => $coach->id,
                    'client_user_id' => $user->id,
                ]);
            }

            $enrollment->forceFill([
                'status' => CoachingEnrollmentStatus::Active,
                'starts_at' => now(),
                'ends_at' => null,
            ])->save();
        }

        if (! $user->subscription()->exists()) {
            $user->attachSubscription(Subscription::createCoachedExternal());
        }

        $invitation->forceFill(['accepted_at' => now()])->save();
    }
}
