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
@endif
