@php
    $clientId = config('services.apple.web_client_id');
    $redirectUri = config('services.apple.web_redirect_uri');
@endphp

@if (filled($clientId) && filled($redirectUri))
    @assets
        <script src="https://appleid.cdn-apple.com/appleauth/static/jsapi/appleid/1/en_US/appleid.auth.js" async></script>
        @vite('resources/js/apple-sign-in.js')
    @endassets

    <form method="POST" action="{{ route('apple.login') }}" class="js-apple-sign-in-form" data-client-id="{{ $clientId }}" data-redirect-uri="{{ $redirectUri }}">
        @csrf
        <input type="hidden" name="authorization_code">
        <input type="hidden" name="full_name">

        <button type="button" class="flex w-full items-center justify-center gap-2 rounded-lg bg-black px-4 py-2.5 text-sm font-medium text-white transition hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900" data-apple-sign-in>
            <svg aria-hidden="true" viewBox="0 0 24 24" class="size-5 fill-current"><path d="M17.6 12.5c0-2.2 1.8-3.3 1.9-3.4-1-1.5-2.6-1.7-3.2-1.7-1.4-.1-2.7.8-3.4.8s-1.8-.8-3-.8C7.5 7.4 5.3 8.8 4.1 11c-2.4 4.2-.6 10.4 1.7 13.7 1.1 1.6 2.5 3.4 4.2 3.3 1.7-.1 2.3-1 4.3-1s2.6 1 4.3 1c1.8 0 2.9-1.6 4-3.2 1.3-1.9 1.8-3.8 1.8-3.9-.1 0-3.5-1.3-3.5-5.4zM15.4 5.9c.9-1.1 1.5-2.6 1.3-4.1-1.3.1-2.9.9-3.8 2-.8.9-1.5 2.5-1.3 3.9 1.5.1 3-.7 3.8-1.8z" transform="scale(.82) translate(2.6 1.2)" /></svg>
            {{ __('Continue with Apple') }}
        </button>
    </form>
@endif
