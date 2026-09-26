<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\FeedbackSubmitted;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;

class FeedbackController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $request->validate([
            'category' => ['required', 'string', 'max:100'],
            'message' => ['required', 'string', 'max:5000', 'not_regex:/^\s*$/'],
        ]);

        /** @var User $user */
        $user = $request->user();

        Mail::to((string) config('services.feedback.recipient'))->send(
            new FeedbackSubmitted(
                user: $user,
                category: $request->string('category')->toString(),
                feedbackMessage: $request->string('message')->toString(),
            ),
        );

        return response()->noContent();
    }
}
