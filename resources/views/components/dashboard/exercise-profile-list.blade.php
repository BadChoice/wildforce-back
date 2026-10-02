@props(['exerciseProfiles'])

<section aria-labelledby="exercise-profiles-heading" class="space-y-4">
    <div>
        <flux:heading id="exercise-profiles-heading" size="sm">{{ __('Exercise profiles') }}</flux:heading>
        <flux:text variant="subtle">{{ __('Saved ability and preference data for each exercise.') }}</flux:text>
    </div>

    <div class="space-y-2">
        <flux:table>
        @forelse ($exerciseProfiles as $exerciseProfile)
            <flux:table.row>
                <flux:table.cell class="w-12 whitespace-nowrap">
                    <img src="{{ app(\App\Services\ExerciseCatalog\ExerciseCatalog::class)->imageUrl($exerciseProfile->exercise) }}" alt="" class="h-14 w-10 rounded-md bg-zinc-100 object-cover dark:bg-zinc-800" loading="lazy" />
                </flux:table.cell>
                <flux:table.cell class="flex flex-col w-full">
                    <div class="font-medium text-zinc-950 dark:text-white">{{ $exerciseProfile->exercise }}</div>
                    <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ str($exerciseProfile->level)->headline() }}</div>
                </flux:table.cell>

                <flux:table.cell class="w-px whitespace-nowrap">
                    @if ($exerciseProfile->working_weight)
                        <flux:badge>{{ __(':weight kg', ['weight' => $exerciseProfile->working_weight]) }}</flux:badge>
                    @endif
                </div>
                </flux:table.cell>
            </flux:table.row>
        @empty
            <flux:table.row>
                <flux:table.cell>
                    {{ __('This user has not saved any exercise profiles yet.') }}
                </flux:table.cell>
            </flux:table.row>
        @endforelse
        </flux:table>
    </div>
</section>
