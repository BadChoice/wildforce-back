@props(['workout'])

<flux:table>
    <flux:table.columns>
        <flux:table.column>{{ __('Exercise') }}</flux:table.column>
        <flux:table.column>{{ __('Sets') }}</flux:table.column>
        <flux:table.column>{{ __('Sets') }}</flux:table.column>
    </flux:table.columns>
    <flux:table.rows>

    @foreach($workout->exercises as $plannedExercise)
        <flux:table.row>
            <flux:table.cell>
                <div class="flex gap-1 items-center">
                <img src="{{$plannedExercise->imageUrl()}}" class="h-12 w-8 rounded-lg bg-zinc-100 object-cover dark:bg-zinc-800" alt="{{$plannedExercise->exercise}}" loading="lazy"/>
                <span class="font-mono text-xs ml-1 font-normal text-zinc-500 dark:text-zinc-400">{{ $plannedExercise->exercise }}</span>
                </div>
            </flux:table.cell>
            <flux:table.cell>{{ $plannedExercise->sets }}</flux:table.cell>
            <flux:table.cell>{{ $plannedExercise->sets }}</flux:table.cell>
        </flux:table.row>
    @endforeach

    </flux:table.rows>

</flux:table>
