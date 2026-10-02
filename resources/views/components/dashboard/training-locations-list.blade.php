@props(['trainingLocations', 'editable' => false])

<div class="pt-4 border-t border-zinc-200 dark:border-zinc-700">
    <flux:heading size="sm" class="mb-2">{{ __('Training locations') }}</flux:heading>

    @if ($trainingLocations && $trainingLocations->isNotEmpty())
    <flux:table>
        @foreach ($trainingLocations as $location)
        <flux:table.row class="text-xs">

            @php logger($location->equipment) @endphp
            @php logger(count($location->equipment)) @endphp
            <flux:table.cell class=""><span class="text-black">{{ $location->name }}</span></flux:table.cell>
            <flux:table.cell class="w-px text-right">
                {{ trans_choice('{1} :count equipment|[2,*] :count equipments', count($location->equipment)) }}
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
