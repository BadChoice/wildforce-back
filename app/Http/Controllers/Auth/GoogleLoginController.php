<?php

namespace App\Http\Controllers\Auth;

use App\Contracts\GoogleAuthentication;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GoogleLoginController extends Controller
{
    public function __construct(private GoogleAuthentication $googleAuthentication) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $request->validate(['id_token' => ['required', 'string', 'max:8192']]);

        $result = $this->googleAuthentication->resolve($request->string('id_token')->toString());

        Auth::login($result->user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
