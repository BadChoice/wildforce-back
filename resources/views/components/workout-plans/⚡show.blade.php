<?php

use App\Enums\CoachingEnrollmentStatus;
use App\Enums\WorkoutKind;
use App\Enums\Generated\MajorMuscleGroup;
use App\Enums\Generated\MuscleGroup;
use App\Jobs\CopyTemplateWorkoutToWorkoutPlan;
use App\Models\WorkoutDay;
use App\Models\WorkoutBlock;
use App\Models\PlannedExercise;
use App\Models\WorkoutPlan;
use App\Services\ExerciseCatalog\ExerciseCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public WorkoutPlan $workoutPlan;

    public bool $showWorkoutDayEditor = false;
    public bool $showTemplatePicker = false;
    public ?string $editingWorkoutDayId = null;
    public ?string $selectedScheduledFor = null;
    public string $workoutDayTitle = '';
    public string $workoutDayNotes = '';
    public string $workoutDayFocus = 'fullBody';
    public string $workoutDayEstimatedDurationMinutes = '';
    public string $templateSearch = '';
    public ?string $selectedWorkoutBlockId = null;
    public string $exerciseSearch = '';
    public string $exerciseCategory = '';
    public string $exerciseMuscle = '';
    public array $workoutDayBlocks = [];
    protected ExerciseCatalog $exerciseCatalog;

    public function boot(ExerciseCatalog $exerciseCatalog): void
    {
        $this->exerciseCatalog = $exerciseCatalog;
    }

    #[Computed]
    public function availableExercises(): array
    {
        $exercises = Arr::get($this->exerciseCatalog->all(), 'exercises', []);

        if (! is_array($exercises)) {
            return [];
        }

        $search = Str::lower($this->exerciseSearch);

        return array_values(array_filter($exercises, function (mixed $exercise) use ($search): bool {
            if (! is_array($exercise)) {
                return false;
            }

            $matchesSearch = $search === '' || Str::contains(Str::lower(implode(' ', [(string) ($exercise['id'] ?? ''), (string) ($exercise['name'] ?? '')])), $search);
            $matchesCategory = $this->exerciseCategory === '' || ($exercise['category'] ?? null) === $this->exerciseCategory;
            $exerciseMuscles = array_merge($exercise['primaryMuscles'] ?? [], $exercise['secondaryMuscles'] ?? []);
            $selectedMuscleGroup = MuscleGroup::tryFrom($this->exerciseMuscle);
            $selectedMajorMuscleGroup = MajorMuscleGroup::tryFrom($this->exerciseMuscle);
            $matchesMuscle = $this->exerciseMuscle === ''
                || in_array($this->exerciseMuscle, $exerciseMuscles, true)
                || ($selectedMajorMuscleGroup !== null && collect($exerciseMuscles)
                    ->map(fn (string $muscle): ?MuscleGroup => MuscleGroup::tryFrom($muscle))
                    ->contains(fn (?MuscleGroup $muscle): bool => $muscle?->majorGroup() === $selectedMajorMuscleGroup))
                || ($selectedMuscleGroup !== null && in_array($selectedMuscleGroup->value, $exerciseMuscles, true));

            return $matchesSearch && $matchesCategory && $matchesMuscle;
        }));
    }

    #[Computed]
    public function categories(): array { return Arr::get($this->exerciseCatalog->all(), 'referenceData.exerciseCategories', []); }
    #[Computed]
    public function muscleGroups(): array
    {
        return array_merge(
            Arr::get($this->exerciseCatalog->all(), 'referenceData.majorMuscleGroups', []),
            Arr::get($this->exerciseCatalog->all(), 'referenceData.muscleGroups', []),
        );
    }

    public function mount(WorkoutPlan $workoutPlan): void
    {
        Gate::authorize('view', $workoutPlan);

        $this->workoutPlan = $workoutPlan;
    }

    #[Computed]
    public function isCompleted(): bool
    {
        return $this->workoutPlan->status === 'completed';
    }

    #[Computed]
    public function baseStartDate(): CarbonImmutable
    {
        return ($this->workoutPlan->starts_on ?? now())
            ->toImmutable()
            ->startOfWeek();
    }

    #[Computed]
    public function visibleStartDate(): CarbonImmutable
    {
        if ($this->isCompleted) {
            return $this->baseStartDate;
        }

        return now()->toImmutable()->startOfWeek();
    }

    /**
     * @return Collection<string, Collection<int, WorkoutDay>>
     */
    #[Computed]
    public function workoutsByDate(): Collection
    {
        $baseStart = $this->baseStartDate;
        $visibleStart = $this->visibleStartDate;

        $weekdayOffsets = [
            'monday' => 0,
            'tuesday' => 1,
            'wednesday' => 2,
            'thursday' => 3,
            'friday' => 4,
            'saturday' => 5,
            'sunday' => 6,
        ];

        return $this->workoutPlan->workoutDays()
            ->orderBy('order_index')
            ->get()
            ->groupBy(function (WorkoutDay $workoutDay) use ($baseStart, $visibleStart, $weekdayOffsets): string {
                if ($workoutDay->scheduled_for !== null) {
                    $dayScheduled = $workoutDay->scheduled_for->toImmutable()->startOfDay();
                    $weekOffset = max(0, min(3, (int) floor($baseStart->diffInDays($dayScheduled) / 7)));
                    $dayOffset = (int) ($dayScheduled->dayOfWeekIso - 1);
                } else {
                    $weekOffset = 0;
                    $intended = strtolower((string) $workoutDay->intended_weekday);
                    $dayOffset = $weekdayOffsets[$intended] ?? 0;
                }

                return $visibleStart->addWeeks($weekOffset)->addDays($dayOffset)->toDateString();
            });
    }

    /**
     * @return Collection<int, WorkoutDay>
     */
    #[Computed]
    public function templateWorkouts(): Collection
    {
        return WorkoutDay::query()
            ->templates()
            ->where(function (Builder $query): void {
                $query->whereBelongsTo(auth()->user())
                    ->orWhereHas('user.clients', function (Builder $query): void {
                $query
                    ->whereKey($this->workoutPlan->user_id)
                    ->where('coaching_enrollments.status', CoachingEnrollmentStatus::Active->value);
                    });
            })
            ->orderByDesc('updated_at')
            ->get();
    }

    /** @return Collection<int, WorkoutDay> */
    #[Computed]
    public function filteredTemplateWorkouts(): Collection
    {
        $search = Str::lower($this->templateSearch);

        return $this->templateWorkouts
            ->filter(fn (WorkoutDay $workoutDay): bool => $search === '' || Str::contains(Str::lower($workoutDay->title.' '.$workoutDay->focus), $search))
            ->values();
    }

    public function createWorkoutDay(string $scheduledFor): void
    {
        Gate::authorize('update', $this->workoutPlan);

        $scheduledDate = $this->scheduledDateWithinVisibleWeeks($scheduledFor);

        if ($scheduledDate === null) {
            return;
        }

        $this->resetValidation();
        $this->editingWorkoutDayId = null;
        $this->selectedScheduledFor = $scheduledDate->toDateString();
        $this->workoutDayTitle = '';
        $this->workoutDayNotes = '';
        $this->workoutDayFocus = 'fullBody';
        $this->workoutDayEstimatedDurationMinutes = '';
        $this->workoutDayBlocks = [];
        $this->addWorkoutBlock();
        $this->showWorkoutDayEditor = true;
    }

    public function openWorkoutDayEditor(string $workoutDayId): void
    {
        Gate::authorize('update', $this->workoutPlan);

        $workoutDay = $this->workoutPlan->workoutDays()->with('blocks.exercises')->whereKey($workoutDayId)->first();

        if ($workoutDay === null) {
            return;
        }

        $this->resetValidation();
        $this->editingWorkoutDayId = $workoutDay->id;
        $this->selectedScheduledFor = $workoutDay->scheduled_for?->toDateString();
        $this->workoutDayTitle = $workoutDay->title;
        $this->workoutDayNotes = $workoutDay->notes ?? '';
        $this->workoutDayFocus = $workoutDay->focus;
        $this->workoutDayEstimatedDurationMinutes = $workoutDay->estimated_duration_minutes === null ? '' : (string) $workoutDay->estimated_duration_minutes;
        $names = collect(Arr::get($this->exerciseCatalog->all(), 'exercises', []))->keyBy('id');
        $this->workoutDayBlocks = $workoutDay->blocks->map(fn (WorkoutBlock $block): array => ['id' => $block->id, 'type' => $block->type, 'notes' => $block->notes ?? '', 'exercises' => $block->exercises->map(fn (PlannedExercise $exercise): array => ['id' => $exercise->id, 'exercise' => $exercise->exercise, 'name' => (string) data_get($names->get($exercise->exercise), 'name', $exercise->exercise), 'notes' => $exercise->notes ?? '', 'sets' => $exercise->sets ?? 3, 'reps_min' => $exercise->reps_min ?? 8, 'reps_max' => $exercise->reps_max ?? 12, 'target_weight_kg' => $exercise->target_weight_kg ?? '', 'rest_seconds' => $exercise->rest_seconds ?? 90])->all()])->all();
        $this->selectedWorkoutBlockId = $this->workoutDayBlocks[0]['id'] ?? null;
        $this->showWorkoutDayEditor = true;
    }

    public function closeWorkoutDayEditor(): void
    {
        $this->showWorkoutDayEditor = false;
    }

    public function addWorkoutBlock(): void
    {
        $blockId = (string) Str::uuid();
        $this->workoutDayBlocks[] = ['id' => $blockId, 'type' => 'standard', 'notes' => '', 'exercises' => []];
        $this->selectedWorkoutBlockId = $blockId;
    }

    public function selectWorkoutBlock(string $blockId): void
    {
        if (collect($this->workoutDayBlocks)->contains('id', $blockId)) { $this->selectedWorkoutBlockId = $blockId; }
    }

    public function removeWorkoutBlock(string $blockId): void
    {
        $this->workoutDayBlocks = array_values(array_filter($this->workoutDayBlocks, fn (array $block): bool => $block['id'] !== $blockId));
        $this->selectedWorkoutBlockId = $this->workoutDayBlocks[0]['id'] ?? null;
    }

    public function addExercise(string $exerciseId): void
    {
        $exercise = collect($this->availableExercises)->firstWhere('id', $exerciseId);
        if ($this->selectedWorkoutBlockId === null || ! is_array($exercise)) { return; }
        foreach ($this->workoutDayBlocks as $index => $block) { if ($block['id'] === $this->selectedWorkoutBlockId) { $this->workoutDayBlocks[$index]['exercises'][] = ['id' => (string) Str::uuid(), 'exercise' => $exerciseId, 'name' => (string) $exercise['name'], 'notes' => '', 'sets' => 3, 'reps_min' => 8, 'reps_max' => 12, 'target_weight_kg' => '', 'rest_seconds' => 90]; return; } }
    }

    public function removeExercise(string $blockId, string $exerciseId): void
    {
        foreach ($this->workoutDayBlocks as $index => $block) { if ($block['id'] === $blockId) { $this->workoutDayBlocks[$index]['exercises'] = array_values(array_filter($block['exercises'], fn (array $exercise): bool => $exercise['id'] !== $exerciseId)); return; } }
    }

    public function saveWorkoutDay(): void
    {
        Gate::authorize('update', $this->workoutPlan);

        $validated = $this->validate([
            'workoutDayTitle' => ['required', 'string', 'max:255'],
            'workoutDayNotes' => ['nullable', 'string'],
            'workoutDayFocus' => ['required', 'string', 'max:255'],
            'workoutDayEstimatedDurationMinutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ]);
        $scheduledDate = $this->selectedScheduledFor === null ? null : $this->scheduledDateWithinVisibleWeeks($this->selectedScheduledFor);

        if ($scheduledDate === null) {
            return;
        }

        $workoutDay = $this->editingWorkoutDayId === null
            ? new WorkoutDay
            : $this->workoutPlan->workoutDays()->whereKey($this->editingWorkoutDayId)->first();

        if ($workoutDay === null) {
            return;
        }

        $workoutDay->forceFill([
            'user_id' => $this->workoutPlan->user_id,
            'workout_plan_id' => $this->workoutPlan->id,
            'kind' => WorkoutKind::Workout,
            'title' => $validated['workoutDayTitle'],
            'focus' => $validated['workoutDayFocus'],
            'status' => 'planned',
            'scheduled_for' => $scheduledDate,
            'intended_weekday' => strtolower($scheduledDate->format('l')),
            'estimated_duration_minutes' => $validated['workoutDayEstimatedDurationMinutes'] === '' ? null : $validated['workoutDayEstimatedDurationMinutes'],
            'notes' => $validated['workoutDayNotes'] ?: null,
        ]);

        if (! $workoutDay->exists) {
            $workoutDay->order_index = ((int) $this->workoutPlan->workoutDays()->max('order_index')) + 1;
            $workoutDay->creation_source = 'manual';
        }

        $workoutDay->save();
        $this->showWorkoutDayEditor = false;
        unset($this->workoutsByDate);
    }

    public function openTemplatePicker(string $scheduledFor): void
    {
        Gate::authorize('update', $this->workoutPlan);

        $scheduledDate = $this->scheduledDateWithinVisibleWeeks($scheduledFor);

        if ($scheduledDate === null) {
            return;
        }

        $this->templateSearch = '';
        $this->selectedScheduledFor = $scheduledDate->toDateString();
        $this->showTemplatePicker = true;
    }

    public function copyTemplateWorkout(string $templateWorkoutId): void
    {
        Gate::authorize('update', $this->workoutPlan);

        $scheduledDate = $this->selectedScheduledFor === null ? null : $this->scheduledDateWithinVisibleWeeks($this->selectedScheduledFor);

        if ($scheduledDate === null) {
            return;
        }

        $templateWorkout = $this->templateWorkouts->firstWhere('id', $templateWorkoutId);

        if ($templateWorkout === null) {
            return;
        }

        CopyTemplateWorkoutToWorkoutPlan::dispatchSync(
            $templateWorkout,
            $this->workoutPlan,
            $scheduledDate->toDateString(),
        );

        $this->showTemplatePicker = false;
        unset($this->workoutsByDate);
    }

    private function scheduledDateWithinVisibleWeeks(string $scheduledFor): ?CarbonImmutable
    {
        try {
            $scheduledDate = CarbonImmutable::parse($scheduledFor)->startOfDay();
        } catch (Throwable) {
            return null;
        }

        $startDate = $this->visibleStartDate();

        if ($scheduledDate->lessThan($startDate) || $scheduledDate->greaterThan($startDate->addWeeks(4)->subDay())) {
            return null;
        }

        return $scheduledDate;
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
    <div>
        <flux:heading size="xl" level="1">{{ $this->workoutPlan->name }}</flux:heading>
        <flux:text variant="subtle">{{ __('The next four weeks of this workout plan.') }}</flux:text>
    </div>

    <div class="overflow-x-auto pb-2">
        <div class="grid min-w-[52rem] grid-cols-[32px_repeat(7,minmax(0,1fr))] gap-2">
            @foreach (range(0, 3) as $weekOffset)
                @php($weekStart = $this->visibleStartDate->addWeeks($weekOffset))

                <div wire:key="workout-week-{{ $weekStart->toDateString() }}" class="group flex min-h-44 items-center justify-center rounded-xl bg-zinc-100 p-1 dark:bg-zinc-800">
                    <span class="-rotate-90 whitespace-nowrap text-xs font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ __('Week') }} {{ $weekOffset + 1 }}</span>
                </div>

                @foreach (range(0, 6) as $dayOffset)
                    @php($date = $weekStart->addDays($dayOffset))
                    @php($dateKey = $date->toDateString())

                    <div wire:key="workout-day-cell-{{ $dateKey }}" class="min-h-44 rounded-xl bg-zinc-50 p-2 dark:bg-zinc-950">
                        <div class="mb-2 flex items-start justify-between gap-1">
                            <div class="min-w-0">
                                <p class="text-[10px] font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ $date->format('D') }}</p>
                                <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $date->format('j') }}</p>
                            </div>

                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="xs" variant="subtle" icon="ellipsis-horizontal" :aria-label="__('Workout day options for :date', ['date' => $date->toFormattedDateString()])" />

                                <flux:menu>
                                    <flux:menu.item icon="plus" wire:click="createWorkoutDay('{{ $dateKey }}')">
                                        {{ __('Create workout day') }}
                                    </flux:menu.item>

                                    <flux:menu.separator />
                                    <flux:menu.item icon="document-duplicate" wire:click="openTemplatePicker('{{ $dateKey }}')">{{ __('Add workout from template') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </div>

                        <div class="space-y-2">
                            @foreach ($this->workoutsByDate->get($dateKey, collect()) as $workoutDay)
                                <button type="button" wire:key="scheduled-workout-{{ $workoutDay->id }}" wire:click="openWorkoutDayEditor('{{ $workoutDay->id }}')" class="w-full rounded-lg border border-zinc-200 bg-white p-2 text-left text-xs shadow-sm transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-800">
                                    <p class="font-semibold text-zinc-900 dark:text-white">{{ $workoutDay->title }}</p>
                                    <p class="mt-1 text-zinc-500 dark:text-zinc-400">{{ str($workoutDay->focus)->headline() }}</p>
                                    @if ($workoutDay->estimated_duration_minutes)
                                        <p class="mt-1 text-zinc-500 dark:text-zinc-400">{{ trans_choice(':count min', $workoutDay->estimated_duration_minutes, ['count' => $workoutDay->estimated_duration_minutes]) }}</p>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>

    <flux:modal wire:model="showWorkoutDayEditor" :closable="false" class="w-full max-w-7xl p-0">
        <x-dashboard.workout-day-editor :blocks="$workoutDayBlocks" :available-exercises="$this->availableExercises" :categories="$this->categories" :muscle-groups="$this->muscleGroups" :selected-workout-block-id="$selectedWorkoutBlockId" />
    </flux:modal>

    <flux:modal wire:model="showTemplatePicker" class="w-full max-w-xl">
        <div class="space-y-4"><div><flux:heading size="lg">{{ __('Add workout from template') }}</flux:heading><flux:text variant="subtle">{{ __('Choose a template to schedule on this day.') }}</flux:text></div><flux:input wire:model.live.debounce.300ms="templateSearch" icon="magnifying-glass" placeholder="{{ __('Search templates') }}" />
            <div class="max-h-96 space-y-2 overflow-y-auto">@forelse ($this->filteredTemplateWorkouts as $templateWorkout)<button type="button" wire:key="picker-template-{{ $templateWorkout->id }}" wire:click="copyTemplateWorkout('{{ $templateWorkout->id }}')" class="flex w-full items-center justify-between rounded-lg border border-zinc-200 p-3 text-left hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"><span><span class="block font-medium">{{ $templateWorkout->title }}</span><span class="text-sm text-zinc-500">{{ str($templateWorkout->focus)->headline() }}</span></span><flux:icon name="plus" class="size-5" /></button>@empty <p class="py-8 text-center text-sm text-zinc-500">{{ __('No templates match the search.') }}</p>@endforelse</div>
        </div>
    </flux:modal>
</div>
