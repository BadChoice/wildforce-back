<x-mail::message>
# {{ __("You've been invited to Wildforce by :coach", ['coach' => $coach->name]) }}

{{ __(':coach has invited you to join their coaching service on Wildforce.', ['coach' => $coach->name]) }}

@if ($invitationMessage)
{{ $invitationMessage }}
@endif

<x-mail::button :url="$invitationUrl">
{{ __('Create your account') }}
</x-mail::button>

{{ __('Thanks') }},<br>
{{ config('app.name') }}
</x-mail::message>
