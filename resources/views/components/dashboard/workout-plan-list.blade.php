@props(['workoutPlans'])

<section aria-labelledby="workout-plans-heading" class="space-y-4">
    <div>
        <flux:heading id="workout-plans-heading" size="sm">{{ __('Workout plans') }}</flux:heading>
        <flux:text variant="subtle">{{ __('Open a plan to see its workout days.') }}</flux:text>
    </div>

    <div class="space-y-3">
        @forelse ($workoutPlans as $workoutPlan)
            <details class="group overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-4 marker:hidden">
                    <div class="min-w-0">
                        <div class="truncate font-medium text-zinc-950 dark:text-white">{{ $workoutPlan->name }}</div>
                        <div class="mt-1 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                            <flux:badge size="sm">{{ str($workoutPlan->status)->headline() }}</flux:badge>
                            <span>{{ trans_choice(':count workout day|:count workout days', $workoutPlan->workoutDays->count(), ['count' => $workoutPlan->workoutDays->count()]) }}</span>
                        </div>
                    </div>
                    <flux:icon name="chevron-down" class="size-5 shrink-0 transition-transform group-open:rotate-180" />
                </summary>

                <div class="border-t border-zinc-200 p-3 dark:border-zinc-700">
                    <div class="space-y-2">
                        @forelse ($workoutPlan->workoutDays as $workoutDay)
                            <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800/60">
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $workoutDay->title }}</div>
                                <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-sm text-zinc-500 dark:text-zinc-400">
                                    <span>{{ str($workoutDay->focus)->headline() }}</span>
                                    @if ($workoutDay->intended_weekday)
                                        <span>{{ str($workoutDay->intended_weekday)->headline() }}</span>
                                    @endif
                                    @if ($workoutDay->estimated_duration_minutes)
                                        <span>{{ trans_choice(':count min', $workoutDay->estimated_duration_minutes, ['count' => $workoutDay->estimated_duration_minutes]) }}</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="py-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No workout days have been added to this plan yet.') }}</p>
                        @endforelse
                    </div>
                </div>
            </details>
        @empty
            <div class="rounded-lg border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                {{ __('This user does not have any workout plans yet.') }}
            </div>
        @endforelse
    </div>
</section>
