@props(['date'])

@php($dateKey = $date->toDateString())

<flux:dropdown position="bottom" align="end">
    <flux:button size="xs" variant="subtle" icon="ellipsis-horizontal" :aria-label="__('Workout day options for :date', ['date' => $date->toFormattedDateString()])" />

    <flux:menu>
        <flux:menu.item icon="plus" wire:click="createWorkoutDay('{{ $dateKey }}')">
            {{ __('Create workout day') }}
        </flux:menu.item>

        <flux:menu.separator />
        <flux:menu.item icon="document-text" wire:click="openWorkoutFromText('{{ $dateKey }}')">{{ __('From text') }}</flux:menu.item>
        <flux:menu.item icon="document-duplicate" wire:click="openTemplatePicker('{{ $dateKey }}')">{{ __('Add workout from template') }}</flux:menu.item>
    </flux:menu>
</flux:dropdown>
