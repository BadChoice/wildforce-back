<x-mail::message>
# Feedback received

**Category:** {{ $category }}

**From:** {{ $user->name }} ({{ $user->email }})

**Message:**

{!! nl2br(e($feedbackMessage)) !!}
</x-mail::message>
