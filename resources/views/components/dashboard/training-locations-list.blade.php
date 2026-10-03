@props(['trainingLocations', 'editable' => false])

<div class="pt-4 border-t border-zinc-200 dark:border-zinc-700">
    <div class="flex items-center justify-between mb-2">
        <flux:heading size="sm">{{ __('Training locations') }}</flux:heading>
        <flux:button size="xs" variant="ghost" icon="plus" wire:click="openTrainingLocationModal()">
            {{ __('Add location') }}
        </flux:button>
    </div>

    @if ($trainingLocations && $trainingLocations->isNotEmpty())
    <flux:table>
        @foreach ($trainingLocations as $location)
        <flux:table.row class="text-xs">
            <flux:table.cell>
                <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $location->name }}</span>
                @if ($location->is_default)
                    <flux:badge size="sm" color="zinc" class="ml-2">{{ __('Default') }}</flux:badge>
                @endif
            </flux:table.cell>
            <flux:table.cell class="text-right">
                {{ trans_choice('{1} :count equipment|[2,*] :count equipments', is_countable($location->equipment) ? count($location->equipment) : 0) }}
            </flux:table.cell>
            <flux:table.cell class="w-px text-right">
                <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" wire:click="editTrainingLocation('{{ $location->id }}')" aria-label="{{ __('Edit location') }}" />
            </flux:table.cell>
        </flux:table.row>
        @endforeach
    </flux:table>
    @else
    <div class="rounded-lg border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
        {{ __('No training locations configured.') }}
        <flux:button icon="plus" wire:click="createDefaultLocation()">Create one</flux:button>
    </div>
    @endif
</div>
