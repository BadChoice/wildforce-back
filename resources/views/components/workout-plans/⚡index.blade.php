<?php

use App\Services\ExerciseCatalog\ExerciseCatalog;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $search = '';

    public function plans(): Collection
    {
        return auth()->user()->workoutPlans()
            ->with([
                'workoutDays' => fn ($query) => $query->orderBy('order_index'),
                'workoutDays.blocks' => fn ($query) => $query->orderBy('order_index'),
                'workoutDays.blocks.exercises' => fn ($query) => $query->orderBy('order_index'),
                'workoutDays.directExercises' => fn ($query) => $query->orderBy('order_index'),
            ])
            ->limit(20)
            ->get();
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
    <div class="space-y-4">
        @forelse ($this->plans() as $plan)
            <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
                    <div>
                        <h2 class="font-semibold text-zinc-950 dark:text-white">{{ $plan->name }}</h2>
                        <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ trans_choice(':count workout day|:count workout days', $plan->workoutDays->count(), ['count' => $plan->workoutDays->count()]) }}</p>
                    </div>
                    <flux:badge size="sm">{{ str($plan->status)->headline() }}</flux:badge>
                </header>

                <div class="space-y-6 p-4 sm:p-5">
                    @forelse ($plan->workoutDays as $workout)
                        <section aria-labelledby="workout-day-{{ $workout->id }}">
                            <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
                                <div>
                                    <h3 id="workout-day-{{ $workout->id }}" class="text-lg font-semibold text-zinc-950 dark:text-white">{{ $workout->title }}</h3>
                                    <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-sm text-zinc-500 dark:text-zinc-400">
                                        @if ($workout->focus)
                                            <span>{{ str($workout->focus)->headline() }}</span>
                                        @endif
                                        @if ($workout->estimated_duration_minutes)
                                            <span>{{ trans_choice(':count min', $workout->estimated_duration_minutes, ['count' => $workout->estimated_duration_minutes]) }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <x-workout-plans.workout-day-table :workout="$workout" />
                        </section>
                    @empty
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No workout days have been added to this plan yet.') }}</p>
                    @endforelse
                </div>
            </section>
        @empty
            <div class="rounded-2xl border border-dashed border-zinc-300 px-4 py-10 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">{{ __('No workout plans have been added yet.') }}</div>
        @endforelse
    </div>
</div>
