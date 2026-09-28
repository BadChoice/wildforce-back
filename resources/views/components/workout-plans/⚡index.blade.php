<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    /** @var list<string> */
    public array $expandedPlanIds = [];

    #[Computed]
    public function plans(): Collection
    {
        return auth()->user()->workoutPlans()
            ->withCount('workoutDays')
            ->when($this->expandedPlanIds !== [], fn (Builder $query): Builder => $query->with([
                'workoutDays' => fn (HasMany $query): HasMany => $query
                    ->whereIn('workout_plan_id', $this->expandedPlanIds)
                    ->orderByRaw('coalesce(completed_at, started_at, scheduled_for, created_at) desc'),
                'workoutDays.blocks' => fn (HasMany $query): HasMany => $query->orderBy('order_index'),
                'workoutDays.blocks.exercises' => fn (HasMany $query): HasMany => $query->orderBy('order_index'),
                'workoutDays.directExercises' => fn (HasMany $query): HasMany => $query->orderBy('order_index'),
            ]))
            ->orderByDesc('starts_on')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();
    }

    public function togglePlan(string $planId): void
    {
        if (in_array($planId, $this->expandedPlanIds, true)) {
            $this->expandedPlanIds = array_values(array_diff($this->expandedPlanIds, [$planId]));

            return;
        }

        if (! auth()->user()->workoutPlans()->whereKey($planId)->exists()) {
            return;
        }

        $this->expandedPlanIds[] = $planId;
    }
};
?>

@php
    $formatDate = static fn (?\Carbon\CarbonInterface $date): ?string => $date?->format('d/m/Y');
@endphp

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
    <div class="space-y-4">
        @forelse ($this->plans as $plan)
            @php($isExpanded = in_array($plan->id, $expandedPlanIds, true))

            <section wire:key="workout-plan-{{ $plan->id }}" class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <button type="button" wire:click="togglePlan('{{ $plan->id }}')" wire:loading.attr="disabled" class="flex w-full flex-wrap items-center justify-between gap-3 px-5 py-4 text-left transition-colors hover:bg-zinc-50 disabled:cursor-wait disabled:opacity-70 dark:hover:bg-zinc-800/40" aria-expanded="{{ $isExpanded ? 'true' : 'false' }}" aria-controls="workout-plan-content-{{ $plan->id }}">
                    <div>
                        <h2 class="font-semibold text-zinc-950 dark:text-white">{{ $plan->name }}</h2>
                        <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-sm text-zinc-500 dark:text-zinc-400">
                            <span>{{ trans_choice(':count workout day|:count workout days', $plan->workout_days_count, ['count' => $plan->workout_days_count]) }}</span>
                            @if ($planStartDate = $formatDate($plan->starts_on))
                                <span>{{ __('Starts :date', ['date' => $planStartDate]) }}</span>
                            @endif
                            <span>{{ __('Created :date', ['date' => $formatDate($plan->created_at)]) }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <flux:badge size="sm">{{ str($plan->status)->headline() }}</flux:badge>
                        <flux:icon name="chevron-down" class="size-5 text-zinc-400 transition-transform {{ $isExpanded ? 'rotate-180' : '' }}" />
                    </div>
                </button>

                @if ($isExpanded)
                    <div id="workout-plan-content-{{ $plan->id }}" class="space-y-6 border-t border-zinc-100 p-4 dark:border-zinc-800 sm:p-5">
                    @forelse ($plan->workoutDays as $workout)
                        <section wire:key="workout-day-{{ $workout->id }}" aria-labelledby="workout-day-{{ $workout->id }}">
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
                                        @if ($scheduledDate = $formatDate($workout->scheduled_for))
                                            <span>{{ __('Scheduled :date', ['date' => $scheduledDate]) }}</span>
                                        @endif
                                        @if ($startedDate = $formatDate($workout->started_at))
                                            <span>{{ __('Started :date', ['date' => $startedDate]) }}</span>
                                        @endif
                                        @if ($completedDate = $formatDate($workout->completed_at))
                                            <span class="font-medium text-emerald-700 dark:text-emerald-400">{{ __('Completed :date', ['date' => $completedDate]) }}</span>
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
                @endif
            </section>
        @empty
            <div class="rounded-2xl border border-dashed border-zinc-300 px-4 py-10 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">{{ __('No workout plans have been added yet.') }}</div>
        @endforelse
    </div>
</div>
