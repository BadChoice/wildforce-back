@props(['enrollment'])

<flux:dropdown position="bottom" align="end">
    <flux:button size="xs" variant="subtle" icon="ellipsis-horizontal" :aria-label="__('Coaching relationship options')" />

    <flux:menu>
        @if ($enrollment->status === \App\Enums\CoachingEnrollmentStatus::Ended)
            <flux:menu.item icon="arrow-path" wire:click="reactivateCoachingEnrollment('{{ $enrollment->id }}')">{{ __('Reactivate') }}</flux:menu.item>
        @else
            @if ($enrollment->status === \App\Enums\CoachingEnrollmentStatus::Active)
                <flux:menu.item icon="pause" wire:click="pauseCoachingEnrollment('{{ $enrollment->id }}')">{{ __('Pause') }}</flux:menu.item>
            @else
                <flux:menu.item icon="play" wire:click="resumeCoachingEnrollment('{{ $enrollment->id }}')">{{ __('Resume') }}</flux:menu.item>
            @endif

            <flux:menu.separator />

            <flux:menu.item icon="x-circle" variant="danger" wire:click="endCoachingEnrollment('{{ $enrollment->id }}')" wire:confirm="{{ __('End this coaching relationship?') }}">
                {{ __('End relationship') }}
            </flux:menu.item>
        @endif
    </flux:menu>
</flux:dropdown>
