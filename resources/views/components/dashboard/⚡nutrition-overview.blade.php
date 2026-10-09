<?php

use App\Models\User;
use App\Services\Nutrition\Adherence\NutritionAdherenceSummary;
use App\Services\Nutrition\Adherence\NutritionDayAdherence;
use App\Services\Nutrition\NutritionAdherenceCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    /**
     * Number of days shown in the adherence strip, ending today.
     */
    public const int AdherenceStripDays = 14;

    #[Locked]
    public User $user;

    public ?string $selectedDate = null;

    protected NutritionAdherenceCalculator $calculator;

    public function boot(NutritionAdherenceCalculator $calculator): void
    {
        $this->calculator = $calculator;
    }

    public function mount(User $user): void
    {
        abort_unless(auth()->user()?->canViewUserData($user->getKey()), 403);

        $this->user = $user;
    }

    #[Computed]
    public function today(): CarbonImmutable
    {
        return $this->calculator->today($this->user);
    }

    #[Computed]
    public function summary(): NutritionAdherenceSummary
    {
        return $this->calculator->summary($this->user);
    }

    /**
     * @return list<NutritionDayAdherence>
     */
    #[Computed]
    public function adherenceDays(): array
    {
        return $this->calculator->days($this->user, $this->today->subDays(self::AdherenceStripDays - 1), $this->today);
    }

    #[Computed]
    public function plans(): Collection
    {
        return $this->user->nutritionPlans()
            ->with('sourceWorkoutPlan:id,name')
            ->orderByDesc('starts_on')
            ->latest()
            ->get();
    }

    #[Computed]
    public function selectedDay(): ?NutritionDayAdherence
    {
        if ($this->selectedDate === null) {
            return null;
        }

        return collect($this->adherenceDays)
            ->first(fn (NutritionDayAdherence $day): bool => $day->date->toDateString() === $this->selectedDate);
    }

    #[Computed]
    public function selectedDayMeals(): Collection
    {
        return $this->selectedDay?->planDay?->meals()->orderBy('order_index')->get() ?? new Collection;
    }

    #[Computed]
    public function selectedDayEntries(): Collection
    {
        if ($this->selectedDay === null) {
            return new Collection;
        }

        return $this->calculator->entriesOn($this->user, $this->selectedDay->date);
    }

    public function selectDay(string $date): void
    {
        $this->selectedDate = $date;
        unset($this->selectedDay);

        if ($this->selectedDay === null) {
            $this->selectedDate = null;
        }
    }

    public function closeDay(): void
    {
        $this->selectedDate = null;
    }
};
?>

<div class="space-y-8">
    @if ($this->selectedDay)
        <div class="space-y-4">
            <flux:button size="sm" variant="ghost" icon="arrow-left" wire:click="closeDay" class="-ml-2">{{ __('Nutrition') }}</flux:button>
            <x-nutrition.day-detail :day="$this->selectedDay" :meals="$this->selectedDayMeals" :entries="$this->selectedDayEntries" :timezone="$user->preferredTimezone()" />
        </div>
    @else
        <x-nutrition.adherence-summary :summary="$this->summary" />
        <x-nutrition.adherence-strip :days="$this->adherenceDays" :selected-date="$selectedDate" />
        <x-nutrition.plan-list :plans="$this->plans" :today="$this->today" />
        <x-dashboard.nutrition-profile :profile="$user->nutritionProfile" />
    @endif
</div>
