@props(['exerciseProfiles'])

<section aria-labelledby="exercise-profiles-heading" class="space-y-4">
    <div>
        <flux:heading id="exercise-profiles-heading" size="sm">{{ __('Exercise profiles') }}</flux:heading>
        <flux:text variant="subtle">{{ __('Saved ability and preference data for each exercise.') }}</flux:text>
    </div>

    <div class="space-y-2">
        @forelse ($exerciseProfiles as $exerciseProfile)
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="font-medium text-zinc-950 dark:text-white">{{ $exerciseProfile->exercise }}</div>
                        <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ str($exerciseProfile->level)->headline() }}</div>
                    </div>
                    @if ($exerciseProfile->working_weight)
                        <flux:badge>{{ __(':weight kg', ['weight' => $exerciseProfile->working_weight]) }}</flux:badge>
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                {{ __('This user has not saved any exercise profiles yet.') }}
            </div>
        @endforelse
    </div>
</section>
