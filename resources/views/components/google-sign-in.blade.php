@php($clientId = config('services.google.web_client_id'))

@if (filled($clientId))
    @assets
        <script src="https://accounts.google.com/gsi/client" async></script>
        @vite('resources/js/google-sign-in.js')
    @endassets

    <form method="POST" action="{{ route('google.login') }}" class="js-google-sign-in-form">
        @csrf
        <input type="hidden" name="id_token">
        <div data-google-sign-in data-client-id="{{ $clientId }}" class="w-full"></div>
    </form>

    <div class="relative my-6">
        <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-zinc-200 dark:border-zinc-700"></div></div>
        <div class="relative flex justify-center text-xs uppercase"><span class="bg-white px-2 text-zinc-500 dark:bg-zinc-900">{{ __('Or continue with email') }}</span></div>
    </div>
@endif
