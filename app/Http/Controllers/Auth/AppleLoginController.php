<?php

namespace App\Http\Controllers\Auth;

use App\Contracts\AppleAuthentication;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AppleLoginController extends Controller
{
    public function __construct(private AppleAuthentication $appleAuthentication) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $request->validate([
            'authorization_code' => ['required', 'string', 'max:8192'],
            'full_name' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->appleAuthentication->resolveWeb(
            $request->string('authorization_code')->toString(),
            $request->string('full_name')->trim()->toString() ?: null,
        );

        Auth::login($result->user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
