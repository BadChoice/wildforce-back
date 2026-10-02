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

    <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-900">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <flux:heading size="lg" level="1">{{ __('Workout plans') }}</flux:heading>
                <flux:text variant="subtle">{{ __('The list of workout plans.') }}</flux:text>
            </div>
            <flux:button variant="primary" icon="plus" wire:click="$dispatch('open-create-workout-plan-modal')">{{ __('New plan') }}</flux:button>
        </div>
        <flux:table>
            <flux:table.columns>
                <flux:table.column> {{ __('Name') }}</flux:table.column>
                <flux:table.column> {{ __('Workouts') }}</flux:table.column>
                <flux:table.column> {{ __('Starts') }}</flux:table.column>
                <flux:table.column> {{ __('Created') }}</flux:table.column>
                <flux:table.column> {{ __('Status') }}</flux:table.column>
                <flux:table.column>  </flux:table.column>
            </flux:table.columns>

            @forelse ($this->plans as $plan)
            <flux:table.row>
                <flux:table.cell>
                    <a href="{{ route('workout-plans.show', $plan) }}" class="font-bold text-black">
                        {{ $plan->name}}
                    </a>
                </flux:table.cell>
                <flux:table.cell> {{ trans_choice(':count workout|:count workouts', $plan->workout_days_count, ['count' => $plan->workout_days_count]) }} </flux:table.cell>
                <flux:table.cell>
                    @if ($planStartDate = $formatDate($plan->starts_on))
                        {{ $planStartDate }}
                    @endif
                </flux:table.cell>
                <flux:table.cell>
                    {{ $formatDate($plan->created_at) }}
                </flux:table.cell>

                <flux:table.cell>
                    <flux:badge size="sm">{{ str($plan->status)->headline() }}</flux:badge>
                </flux:table.cell>

                <flux:table.cell>
                    <a href="{{ route('workout-plans.show', $plan) }}">
                        <flux:icon.ellipsis-horizontal variant="mini" />
                    </a>
                </flux:table.cell>
            </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="py-8 text-center text-sm text-zinc-500">{{ __('No workout plans found.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse

        </flux:table>
    </div>

    <livewire:workout-plans.create-plan-modal />
</div>
