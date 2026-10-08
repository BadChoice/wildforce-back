<?php

use App\Enums\CoachingEnrollmentStatus;
use App\Enums\WorkoutFocus;
use App\Enums\WorkoutDayStatus;
use App\Enums\WorkoutKind;
use App\Enums\Generated\MajorMuscleGroup;
use App\Enums\Generated\MuscleGroup;
use App\Jobs\CopyTemplateWorkoutToWorkoutPlan;
use App\Jobs\RescheduleWorkoutDay;
use App\Models\WorkoutDay;
use App\Models\WorkoutBlock;
use App\Models\PlannedExercise;
use App\Models\WorkoutPlan;
use App\Services\ExerciseCatalog\ExerciseCatalog;
use App\Services\Workouts\SingleWorkoutFromTextGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component {
    public WorkoutPlan $workoutPlan;

    #[Url(as: 'view', except: 'calendar')]
    public string $viewMode = 'calendar';

    public bool $showWorkoutDayEditor = false;
    public bool $showWorkoutDayCompleted = false;
    public bool $showTemplatePicker = false;
    public bool $showWorkoutFromText = false;
    public bool $showWorkoutPlanEditor = false;
    public ?string $editingWorkoutDayId = null;
    public ?WorkoutDay $viewingWorkoutDay = null;
    public ?string $selectedScheduledFor = null;
    public string $workoutDayTitle = '';
    public string $workoutDayNotes = '';
    public string $workoutDayFocus = 'fullBody';
    public string $workoutDayEstimatedDurationMinutes = '';
    public string $templateSearch = '';
    public string $workoutText = '';
    public ?string $selectedWorkoutBlockId = null;
    public string $exerciseSearch = '';
    public string $exerciseCategory = '';
    public string $exerciseMuscle = '';
    public string $workoutPlanGoal = '';
    public string $workoutPlanStatus = '';
    public string $workoutPlanBodyCompositionPhase = '';
    public string $workoutPlanMesocycleNumber = '';
    public string $workoutPlanPhase = '';
    public string $workoutPlanPhaseWeek = '';
    public string $workoutPlanCycleLength = '';
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

    public function openWorkoutPlanEditor(): void
    {
        Gate::authorize('update', $this->workoutPlan);

        $this->resetValidation();
        $this->workoutPlanGoal = $this->workoutPlan->goal;
        $this->workoutPlanStatus = $this->workoutPlan->status;
        $this->workoutPlanBodyCompositionPhase = $this->workoutPlan->body_composition_phase ?? '';
        $this->workoutPlanMesocycleNumber = $this->workoutPlan->mesocycle_number === null ? '' : (string) $this->workoutPlan->mesocycle_number;
        $this->workoutPlanPhase = $this->workoutPlan->phase?->value ?? '';
        $this->workoutPlanPhaseWeek = $this->workoutPlan->phase_week === null ? '' : (string) $this->workoutPlan->phase_week;
        $this->workoutPlanCycleLength = $this->workoutPlan->cycle_length === null ? '' : (string) $this->workoutPlan->cycle_length;
        $this->showWorkoutPlanEditor = true;
    }

    public function saveWorkoutPlan(): void
    {
        Gate::authorize('update', $this->workoutPlan);

        $validated = $this->validate([
            'workoutPlanGoal' => ['required', Rule::in(['generalFitness', 'loseWeight', 'buildMuscle', 'gainStrength', 'improveEndurance', 'improveMobility', 'bodyRecomposition'])],
            'workoutPlanStatus' => ['required', Rule::in(['draft', 'active', 'completed'])],
            'workoutPlanBodyCompositionPhase' => [Rule::in(['', 'automatic', 'bulk', 'cut', 'maintain', 'recomposition'])],
            'workoutPlanMesocycleNumber' => ['nullable', 'integer', 'min:1'],
            'workoutPlanPhase' => [Rule::in(['', 'accumulation', 'intensification', 'deload'])],
            'workoutPlanPhaseWeek' => ['nullable', 'integer', 'min:1'],
            'workoutPlanCycleLength' => ['nullable', 'integer', 'min:2'],
        ]);

        $this->workoutPlan->forceFill([
            'goal' => $validated['workoutPlanGoal'],
            'status' => $validated['workoutPlanStatus'],
            'body_composition_phase' => $validated['workoutPlanBodyCompositionPhase'] ?: null,
            'mesocycle_number' => $validated['workoutPlanMesocycleNumber'] === '' ? null : $validated['workoutPlanMesocycleNumber'],
            'phase' => $validated['workoutPlanPhase'] ?: null,
            'phase_week' => $validated['workoutPlanPhaseWeek'] === '' ? null : $validated['workoutPlanPhaseWeek'],
            'cycle_length' => $validated['workoutPlanCycleLength'] === '' ? null : $validated['workoutPlanCycleLength'],
        ])->save();

        $this->showWorkoutPlanEditor = false;
        unset($this->isCompleted, $this->baseStartDate, $this->visibleStartDate, $this->workoutsByDate);
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
            ->with(['blocks.exercises.exerciseResults'])
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
            ->filter(fn (WorkoutDay $workoutDay): bool => $search === '' || Str::contains(Str::lower($workoutDay->title.' '.$workoutDay->focus->value), $search))
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

    public function openWorkoutDay(string $workoutDayId): void
    {
        Gate::authorize('view', $this->workoutPlan);

        $workoutDay = $this->workoutPlan->workoutDays()
            ->with(['blocks.exercises.exerciseResults'])
            ->whereKey($workoutDayId)
            ->first();

        if ($workoutDay === null) {
            return;
        }

        if (in_array($workoutDay->status, [WorkoutDayStatus::Completed->value, WorkoutDayStatus::InProgress->value])) {
            $this->openWorkoutDayCompleted($workoutDay);

            return;
        }

        $this->openWorkoutDayEditor($workoutDayId);
    }

    public function rescheduleWorkoutDay(string $workoutDayId, string $scheduledFor): void
    {
        Gate::authorize('update', $this->workoutPlan);

        $scheduledDate = $this->scheduledDateWithinVisibleWeeks($scheduledFor);

        if ($scheduledDate === null) {
            return;
        }

        $workoutDay = $this->workoutPlan->workoutDays()
            ->whereKey($workoutDayId)
            ->first();

        if ($workoutDay === null || $workoutDay->status !== WorkoutDayStatus::Planned->value) {
            return;
        }

        RescheduleWorkoutDay::dispatchSync($workoutDay, $scheduledDate);

        unset($this->workoutsByDate);
    }

    public function openWorkoutDayCompleted(WorkoutDay $workoutDay): void
    {
        Gate::authorize('view', $this->workoutPlan);

        $this->viewingWorkoutDay = $workoutDay;
        $this->showWorkoutDayCompleted = true;
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
        $this->fillWorkoutDayEditor($workoutDay);
        $this->showWorkoutDayEditor = true;
    }

    public function openWorkoutFromText(string $scheduledFor): void
    {
        Gate::authorize('update', $this->workoutPlan);

        $scheduledDate = $this->scheduledDateWithinVisibleWeeks($scheduledFor);

        if ($scheduledDate === null) {
            return;
        }

        $this->resetValidation();
        $this->selectedScheduledFor = $scheduledDate->toDateString();
        $this->workoutText = '';
        $this->showWorkoutFromText = true;
    }

    public function generateWorkoutFromText(SingleWorkoutFromTextGenerator $generator): void
    {
        Gate::authorize('update', $this->workoutPlan);

        $validated = $this->validate([
            'workoutText' => ['required', 'string', 'max:20000'],
        ]);

        $workoutDay = $generator->generate($this->workoutPlan->user, $validated['workoutText']);

        $this->editingWorkoutDayId = null;
        $this->fillWorkoutDayEditor($workoutDay);
        $this->workoutText = '';
        $this->showWorkoutFromText = false;
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
            'workoutDayFocus' => ['required', 'string', Rule::in(WorkoutFocus::allCasesArray())],
            'workoutDayEstimatedDurationMinutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'workoutDayBlocks' => ['required', 'array'],
            'workoutDayBlocks.*.id' => ['required', 'uuid'],
            'workoutDayBlocks.*.type' => ['required', 'string', 'max:255'],
            'workoutDayBlocks.*.notes' => ['nullable', 'string'],
            'workoutDayBlocks.*.exercises' => ['array'],
            'workoutDayBlocks.*.exercises.*.id' => ['required', 'uuid'],
            'workoutDayBlocks.*.exercises.*.exercise' => ['required', 'string', 'max:255'],
            'workoutDayBlocks.*.exercises.*.notes' => ['nullable', 'string'],
            'workoutDayBlocks.*.exercises.*.sets' => ['required', 'integer', 'min:1', 'max:100'],
            'workoutDayBlocks.*.exercises.*.reps_min' => ['required', 'integer', 'min:0', 'max:1000'],
            'workoutDayBlocks.*.exercises.*.reps_max' => ['required', 'integer', 'gte:workoutDayBlocks.*.exercises.*.reps_min', 'max:1000'],
            'workoutDayBlocks.*.exercises.*.target_weight_kg' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'workoutDayBlocks.*.exercises.*.rest_seconds' => ['required', 'integer', 'min:0', 'max:3600'],
        ]);
        $scheduledDate = $this->selectedScheduledFor === null ? null : $this->scheduledDateWithinVisibleWeeks($this->selectedScheduledFor);

        if ($scheduledDate === null) {
            return;
        }

        DB::transaction(function () use ($scheduledDate, $validated): void {
            $workoutDay = $this->editingWorkoutDayId === null
                ? new WorkoutDay
                : $this->workoutPlan->workoutDays()->with('blocks.exercises')->whereKey($this->editingWorkoutDayId)->first();

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

            $existingBlocks = $workoutDay->blocks->keyBy('id');
            $submittedBlockIds = collect($validated['workoutDayBlocks'])->pluck('id')->all();

            $existingBlocks
                ->reject(fn (WorkoutBlock $block): bool => in_array($block->id, $submittedBlockIds, true))
                ->each(function (WorkoutBlock $block): void {
                    $block->exercises->each->delete();
                    $block->delete();
                });

            foreach ($validated['workoutDayBlocks'] as $blockIndex => $blockData) {
                $workoutBlock = $existingBlocks->get($blockData['id']) ?? new WorkoutBlock;
                $workoutBlock->forceFill([
                    'workout_day_id' => $workoutDay->id,
                    'type' => $blockData['type'],
                    'order_index' => $blockIndex,
                    'rounds' => 1,
                    'notes' => $blockData['notes'] ?: null,
                ]);
                $workoutBlock->save();

                $existingExercises = $workoutBlock->exists && $existingBlocks->has($blockData['id'])
                    ? $existingBlocks->get($blockData['id'])->exercises->keyBy('id')
                    : collect();
                $submittedExerciseIds = collect($blockData['exercises'])->pluck('id')->all();

                $existingExercises
                    ->reject(fn (PlannedExercise $exercise): bool => in_array($exercise->id, $submittedExerciseIds, true))
                    ->each->delete();

                foreach ($blockData['exercises'] as $exerciseIndex => $exerciseData) {
                    $plannedExercise = $existingExercises->get($exerciseData['id']) ?? new PlannedExercise;
                    $plannedExercise->forceFill([
                        'workout_day_id' => $workoutDay->id,
                        'workout_block_id' => $workoutBlock->id,
                        'exercise' => $exerciseData['exercise'],
                        'order_index' => $exerciseIndex,
                        'sets' => $exerciseData['sets'],
                        'reps_min' => $exerciseData['reps_min'],
                        'reps_max' => $exerciseData['reps_max'],
                        'target_weight_kg' => $exerciseData['target_weight_kg'] === '' ? null : $exerciseData['target_weight_kg'],
                        'rest_seconds' => $exerciseData['rest_seconds'],
                        'notes' => $exerciseData['notes'] ?: null,
                    ]);
                    $plannedExercise->save();
                }
            }
        });

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

    private function fillWorkoutDayEditor(WorkoutDay $workoutDay): void
    {
        $names = collect(Arr::get($this->exerciseCatalog->all(), 'exercises', []))->keyBy('id');
        $this->workoutDayTitle = $workoutDay->title;
        $this->workoutDayNotes = $workoutDay->notes ?? '';
        $this->workoutDayFocus = $workoutDay->focus->value;
        $this->workoutDayEstimatedDurationMinutes = $workoutDay->estimated_duration_minutes === null ? '' : (string) $workoutDay->estimated_duration_minutes;
        $this->workoutDayBlocks = $workoutDay->blocks->map(fn (WorkoutBlock $block): array => ['id' => filled($block->id) ? $block->id : (string) Str::uuid(), 'type' => $block->type->value, 'notes' => $block->notes ?? '', 'exercises' => $block->exercises->map(fn (PlannedExercise $exercise): array => ['id' => filled($exercise->id) ? $exercise->id : (string) Str::uuid(), 'exercise' => $exercise->exercise, 'name' => (string) data_get($names->get($exercise->exercise), 'name', $exercise->exercise), 'notes' => $exercise->notes ?? '', 'sets' => $exercise->sets ?? 3, 'reps_min' => $exercise->reps_min ?? 8, 'reps_max' => $exercise->reps_max ?? 12, 'target_weight_kg' => $exercise->target_weight_kg ?? '', 'rest_seconds' => $exercise->rest_seconds ?? 90])->all()])->all();
        $this->selectedWorkoutBlockId = $this->workoutDayBlocks[0]['id'] ?? null;
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

<div
    x-data="workoutDayDragDrop()"
    x-on:workout-day-dropped="$wire.rescheduleWorkoutDay($event.detail.workoutDayId, $event.detail.scheduledFor)"
    class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl"
>

    <!-- Header -->
    @php($statusColor = match ($this->workoutPlan->status) { 'active' => 'green', 'completed' => 'indigo', default => 'zinc' })

    <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <flux:heading size="xl" level="1">{{ $this->workoutPlan->name }}</flux:heading>
                    <flux:badge :color="$statusColor">{{ str($this->workoutPlan->status)->headline() }}</flux:badge>
                </div>
                <flux:text variant="subtle" class="mt-1">{{ __('The next four weeks of this workout plan.') }}</flux:text>
            </div>

            <div class="flex items-center gap-2">
                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="openWorkoutPlanEditor">{{ __('Edit') }}</flux:button>
            </div>
        </div>

        <div class="mt-5 grid gap-4 lg:grid-cols-[minmax(0,1fr)_18rem]">
            <x-workout-plans.mesocycle-details
                :mesocycle-number="$this->workoutPlan->mesocycle_number"
                :phase="$this->workoutPlan->phase?->value"
                :phase-week="$this->workoutPlan->phase_week"
                :cycle-length="$this->workoutPlan->cycle_length"
            />

            <dl class="grid grid-cols-2 gap-3">
                <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800/60">
                    <dt class="text-xs font-medium tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Goal') }}</dt>
                    <dd class="mt-1 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ str($this->workoutPlan->goal)->headline() }}</dd>
                </div>
                <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800/60">
                    <dt class="text-xs font-medium tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Body composition phase') }}</dt>
                    <dd class="mt-1 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $this->workoutPlan->body_composition_phase ? str($this->workoutPlan->body_composition_phase)->headline() : __('Not set') }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <!-- Display options -->
    <div class="flex justify-end">
        <flux:button.group>
            <flux:button :variant="$viewMode === 'calendar' ? 'primary' : 'ghost'" size="sm" icon="calendar-days" wire:click="$set('viewMode', 'calendar')" :aria-label="__('Calendar view')" />
            <flux:button :variant="$viewMode === 'kanban' ? 'primary' : 'ghost'" size="sm" icon="kanban" wire:click="$set('viewMode', 'kanban')" :aria-label="__('Kanban view')" />
        </flux:button.group>
    </div>

    @if ($viewMode === 'kanban')
        <x-workout-plans.kanban-view :visible-start-date="$this->visibleStartDate" :workouts-by-date="$this->workoutsByDate" />
    @else
        <x-workout-plans.calendar-view :visible-start-date="$this->visibleStartDate" :workouts-by-date="$this->workoutsByDate" />
    @endif

    <!-- Modals -->
    <flux:modal wire:model="showWorkoutDayCompleted" class="w-full max-w-4xl p-0">
        @if ($viewingWorkoutDay)
            <x-dashboard.workout-day-completed :workout-day="$viewingWorkoutDay" />
        @endif
    </flux:modal>

    <flux:modal wire:model="showWorkoutDayEditor" :closable="false" class="w-full max-w-7xl p-0">
        <x-dashboard.workout-day-editor :blocks="$workoutDayBlocks" :available-exercises="$this->availableExercises" :categories="$this->categories" :muscle-groups="$this->muscleGroups" :selected-workout-block-id="$selectedWorkoutBlockId" />
    </flux:modal>

    <flux:modal wire:model="showTemplatePicker" class="w-full max-w-xl">
        <div class="space-y-4"><div><flux:heading size="lg">{{ __('Add workout from template') }}</flux:heading><flux:text variant="subtle">{{ __('Choose a template to schedule on this day.') }}</flux:text></div><flux:input wire:model.live.debounce.300ms="templateSearch" icon="magnifying-glass" placeholder="{{ __('Search templates') }}" />
            <div class="max-h-96 space-y-2 overflow-y-auto">@forelse ($this->filteredTemplateWorkouts as $templateWorkout)<button type="button" wire:key="picker-template-{{ $templateWorkout->id }}" wire:click="copyTemplateWorkout('{{ $templateWorkout->id }}')" class="flex w-full items-center justify-between rounded-lg border border-zinc-200 p-3 text-left hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800"><span><span class="block font-medium">{{ $templateWorkout->title }}</span><span class="text-sm text-zinc-500">{{ str($templateWorkout->focus->value)->headline() }}</span></span><flux:icon name="plus" class="size-5" /></button>@empty <p class="py-8 text-center text-sm text-zinc-500">{{ __('No templates match the search.') }}</p>@endforelse</div>
        </div>
    </flux:modal>

    <flux:modal wire:model="showWorkoutFromText" class="w-full max-w-2xl">
        <div class="space-y-4">
            <div><flux:heading size="lg">{{ __('Create workout from text') }}</flux:heading><flux:text variant="subtle">{{ __('Paste a workout and review it before saving.') }}</flux:text></div>
            <flux:field><flux:label>{{ __('Workout text') }}</flux:label><flux:textarea wire:model="workoutText" rows="12" placeholder="{{ __('1. Row — 59 kg — 3 sets') }}" /><flux:error name="workoutText" /></flux:field>
            <div class="flex justify-end"><flux:button variant="primary" wire:click="generateWorkoutFromText" wire:loading.attr="disabled">{{ __('Generate workout') }}</flux:button></div>
        </div>
    </flux:modal>

    <flux:modal wire:model="showWorkoutPlanEditor" class="w-full max-w-2xl">
        <form wire:submit="saveWorkoutPlan" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Edit workout plan') }}</flux:heading>
                <flux:text variant="subtle" class="mt-1">{{ __('Update the plan status, goal, and mesocycle details.') }}</flux:text>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Status') }}</flux:label>
                    <flux:select wire:model="workoutPlanStatus">
                        @foreach (['draft', 'active', 'completed'] as $status)
                            <option value="{{ $status }}">{{ str($status)->headline() }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="workoutPlanStatus" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Goal') }}</flux:label>
                    <flux:select wire:model="workoutPlanGoal">
                        @foreach (['generalFitness', 'loseWeight', 'buildMuscle', 'gainStrength', 'improveEndurance', 'improveMobility', 'bodyRecomposition'] as $goal)
                            <option value="{{ $goal }}">{{ str($goal)->headline() }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="workoutPlanGoal" />
                </flux:field>

                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('Body composition phase') }}</flux:label>
                    <flux:select wire:model="workoutPlanBodyCompositionPhase">
                        <option value="">{{ __('Not set') }}</option>
                        @foreach (['automatic', 'bulk', 'cut', 'maintain', 'recomposition'] as $bodyCompositionPhase)
                            <option value="{{ $bodyCompositionPhase }}">{{ str($bodyCompositionPhase)->headline() }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="workoutPlanBodyCompositionPhase" />
                </flux:field>
            </div>

            <div class="border-t border-zinc-200 pt-5 dark:border-zinc-700">
                <flux:heading size="sm">{{ __('Mesocycle') }}</flux:heading>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Mesocycle number') }}</flux:label>
                        <flux:input type="number" min="1" wire:model="workoutPlanMesocycleNumber" />
                        <flux:error name="workoutPlanMesocycleNumber" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Phase') }}</flux:label>
                        <flux:select wire:model="workoutPlanPhase">
                            <option value="">{{ __('Not set') }}</option>
                            @foreach (['accumulation', 'intensification', 'deload'] as $phase)
                                <option value="{{ $phase }}">{{ str($phase)->headline() }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="workoutPlanPhase" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Phase week') }}</flux:label>
                        <flux:input type="number" min="1" wire:model="workoutPlanPhaseWeek" />
                        <flux:error name="workoutPlanPhaseWeek" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Cycle length') }}</flux:label>
                        <flux:input type="number" min="2" wire:model="workoutPlanCycleLength" />
                        <flux:error name="workoutPlanCycleLength" />
                    </flux:field>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
