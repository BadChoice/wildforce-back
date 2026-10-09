<?php

namespace App\Http\Controllers;

use App\Services\Coaching\ClientInvitationService;
use Illuminate\Http\RedirectResponse;

class ClientInvitationController extends Controller
{
    public function show(string $token, ClientInvitationService $clientInvitationService): RedirectResponse
    {
        $invitation = $clientInvitationService->pendingInvitation($token);

        abort_if($invitation === null, 404);

        $user = auth()->user();

        if ($user === null) {
            return redirect()->route('register', ['invitation' => $token]);
        }

        abort_unless(mb_strtolower($user->email) === $invitation->email, 403);

        $clientInvitationService->acceptForUser($token, $user);

        return redirect()->route('dashboard')->with('status', __('You are now connected with :coach.', ['coach' => $invitation->coach->name]));
    }
}
