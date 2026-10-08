<?php

use App\Models\NutritionDay;
use App\Models\NutritionPlan;
use App\Services\Nutrition\Adherence\NutritionDayAdherence;
use App\Services\Nutrition\NutritionAdherenceCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public NutritionPlan $nutritionPlan;

    public ?string $selectedDate = null;

    public bool $showDayDetail = false;

    protected NutritionAdherenceCalculator $calculator;

    public function boot(NutritionAdherenceCalculator $calculator): void
    {
        $this->calculator = $calculator;
    }

    public function mount(NutritionPlan $nutritionPlan): void
    {
        Gate::authorize('view', $nutritionPlan);

        $this->nutritionPlan = $nutritionPlan;
    }

    #[Computed]
    public function today(): CarbonImmutable
    {
        return $this->calculator->today($this->nutritionPlan->user);
    }

    #[Computed]
    public function planDays(): Collection
    {
        return $this->nutritionPlan->days()
            ->with(['meals' => fn (HasMany $query): HasMany => $query->orderBy('order_index')])
            ->orderBy('date')
            ->get();
    }

    /**
     * @return array<string, NutritionDayAdherence>
     */
    #[Computed]
    public function adherenceByDate(): array
    {
        $plan = $this->nutritionPlan;

        return collect($this->calculator->days($plan->user, $plan->startDate(), $plan->endDate(), $plan))
            ->keyBy(fn (NutritionDayAdherence $day): string => $day->date->toDateString())
            ->all();
    }

    #[Computed]
    public function selectedDay(): ?NutritionDayAdherence
    {
        return $this->selectedDate === null ? null : ($this->adherenceByDate[$this->selectedDate] ?? null);
    }

    #[Computed]
    public function selectedDayMeals(): Collection
    {
        return $this->planDays
            ->first(fn (NutritionDay $planDay): bool => $planDay->date->toDateString() === $this->selectedDate)
            ?->meals ?? new Collection;
    }

    #[Computed]
    public function selectedDayEntries(): Collection
    {
        if ($this->selectedDay === null) {
            return new Collection;
        }

        return $this->calculator->entriesOn($this->nutritionPlan->user, $this->selectedDay->date);
    }

    public function selectDay(string $date): void
    {
        if (! array_key_exists($date, $this->adherenceByDate)) {
            return;
        }

        $this->selectedDate = $date;
        $this->showDayDetail = true;
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
    @php($planStatus = $nutritionPlan->statusOn($this->today))

    <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <flux:heading size="xl" level="1">{{ __('Nutrition plan') }}</flux:heading>
                    <flux:badge :color="$planStatus->color()">{{ $planStatus->label() }}</flux:badge>
                </div>
                <flux:text variant="subtle" class="mt-1">
                    {{ $nutritionPlan->user->name }} · {{ $nutritionPlan->startDate()->translatedFormat('j M') }} – {{ $nutritionPlan->endDate()->translatedFormat('j M Y') }}
                </flux:text>
            </div>
        </div>

        <dl class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-dashboard.detail-item :label="__('Goal')" :value="str($nutritionPlan->goal)->headline()" />
            <x-dashboard.detail-item :label="__('Body composition phase')" :value="$nutritionPlan->body_composition_phase ? str($nutritionPlan->body_composition_phase)->headline() : __('Not set')" />
            <x-dashboard.detail-item :label="__('Daily average')" :value="__(':calories kcal', ['calories' => number_format((float) $nutritionPlan->daily_calorie_average)])" />
            <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800/60">
                <dt class="text-xs font-medium tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Source workout plan') }}</dt>
                <dd class="mt-1 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                    @if ($nutritionPlan->sourceWorkoutPlan)
                        <a href="{{ route('workout-plans.show', $nutritionPlan->sourceWorkoutPlan) }}" class="hover:underline">{{ $nutritionPlan->sourceWorkoutPlan->name }}</a>
                    @else
                        {{ __('None') }}
                    @endif
                </dd>
            </div>
            @if ($nutritionPlan->notes)
                <x-dashboard.detail-item :label="__('Notes')" :value="$nutritionPlan->notes" class="col-span-2 lg:col-span-4" />
            @endif
        </dl>
    </div>

    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        @forelse ($this->planDays as $planDay)
            @php($adherence = $this->adherenceByDate[$planDay->date->toDateString()] ?? null)
            <div wire:key="nutrition-plan-day-{{ $planDay->id }}" x-data="{ showMeals: false }" @class([
                'flex flex-col gap-3 rounded-xl border bg-white p-4 dark:bg-zinc-900',
                'border-zinc-900 dark:border-white' => $adherence?->isToday,
                'border-zinc-200 dark:border-zinc-700' => ! $adherence?->isToday,
            ])>
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <flux:heading size="sm">{{ $planDay->date->translatedFormat('l') }}</flux:heading>
                        <flux:text variant="subtle" class="text-xs">{{ $planDay->date->translatedFormat('j M') }}</flux:text>
                    </div>
                    <div class="flex flex-wrap justify-end gap-1">
                        <flux:badge size="sm">{{ $planDay->day_type->label() }}</flux:badge>
                        @if ($adherence)
                            <flux:badge size="sm" :color="$adherence->status->color()">{{ $adherence->status->label() }}</flux:badge>
                        @endif
                    </div>
                </div>

                @if ($planDay->planned_workout_title)
                    <flux:text class="text-sm">{{ $planDay->planned_workout_title }}</flux:text>
                @endif

                <table class="w-full text-xs tabular-nums">
                    <thead class="text-zinc-500 dark:text-zinc-400">
                        <tr>
                            <th class="text-left font-medium"></th>
                            <th class="text-right font-medium">{{ __('Target') }}</th>
                            <th class="text-right font-medium">{{ __('Logged') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ([
                            ['label' => __('Calories'), 'target' => $planDay->target_calories, 'logged' => $adherence?->calories],
                            ['label' => __('Protein'), 'target' => $planDay->target_protein_grams, 'logged' => $adherence?->proteinGrams],
                            ['label' => __('Carbs'), 'target' => $planDay->target_carbs_grams, 'logged' => $adherence?->carbsGrams],
                            ['label' => __('Fat'), 'target' => $planDay->target_fat_grams, 'logged' => $adherence?->fatGrams],
                        ] as $macro)
                            <tr>
                                <td class="py-0.5 text-zinc-500 dark:text-zinc-400">{{ $macro['label'] }}</td>
                                <td class="py-0.5 text-right">{{ number_format((float) $macro['target']) }}</td>
                                <td class="py-0.5 text-right font-medium">{{ $adherence?->isLogged() ? number_format($macro['logged']) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div x-show="showMeals" x-cloak class="space-y-2 border-t border-zinc-100 pt-3 text-xs dark:border-zinc-800">
                    @foreach ($planDay->meals as $meal)
                        <div wire:key="nutrition-plan-meal-{{ $meal->id }}">
                            <div class="flex items-baseline justify-between gap-2">
                                <span class="font-medium">{{ $meal->title }}</span>
                                <span class="tabular-nums text-zinc-500 dark:text-zinc-400">{{ number_format((float) $meal->target_calories) }} kcal</span>
                            </div>
                            @if ($meal->guidance)
                                <p class="text-zinc-600 dark:text-zinc-300">{{ $meal->guidance }}</p>
                            @endif
                        </div>
                    @endforeach
                    @if ($planDay->pre_workout_guidance)
                        <p><span class="font-medium">{{ __('Pre-workout') }}:</span> {{ $planDay->pre_workout_guidance }}</p>
                    @endif
                    @if ($planDay->post_workout_guidance)
                        <p><span class="font-medium">{{ __('Post-workout') }}:</span> {{ $planDay->post_workout_guidance }}</p>
                    @endif
                </div>

                <div class="mt-auto flex items-center justify-between gap-2">
                    <flux:button size="xs" variant="ghost" icon="chevron-down" x-on:click="showMeals = ! showMeals" class="-ml-2">
                        {{ trans_choice(':count meal|:count meals', $planDay->meals->count(), ['count' => $planDay->meals->count()]) }}
                    </flux:button>
                    <flux:button size="xs" variant="ghost" icon-trailing="arrow-right" wire:click="selectDay('{{ $planDay->date->toDateString() }}')">{{ __('View day') }}</flux:button>
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500 md:col-span-2 xl:col-span-4 dark:border-zinc-700 dark:text-zinc-400">
                {{ __('This nutrition plan has no days.') }}
            </div>
        @endforelse
    </div>

    <flux:modal wire:model="showDayDetail" class="w-full max-w-4xl">
        @if ($this->selectedDay)
            <x-nutrition.day-detail :day="$this->selectedDay" :meals="$this->selectedDayMeals" :entries="$this->selectedDayEntries" />
        @endif
    </flux:modal>
</div>
