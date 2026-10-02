<?php

use App\Enums\CoachingEnrollmentStatus;
use App\Enums\Generated\MajorMuscleGroup;
use App\Enums\Generated\MuscleGroup;
use App\Models\PlannedExercise;
use App\Models\User;
use App\Models\WorkoutBlock;
use App\Models\WorkoutDay;
use App\Models\WorkoutPlan;
use App\Services\ExerciseCatalog\ExerciseCatalog;
use App\Services\Nutrition\NutritionPlanAIGenerator;
use App\Services\Workouts\WorkoutPlanAIGenerator;
use App\Services\Workouts\Progression\ProgressionAnalysis;
use App\Services\Workouts\Progression\TrainingHistory;
use App\Services\Workouts\Progression\WorkoutProgressionAnalyzer;
use App\ViewModels\PlanPromptPreview;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?string $selectedClientId = null;

    public string $selectedTab = 'info';

    public bool $showClientDetail = false;

    public bool $showWorkoutPlanForm = false;

    public bool $showProgressionAnalysis = false;

    public PlanPromptPreview $planPromptPreview;

    public string $workoutPlanName = '';

    public string $workoutPlanGoal = 'generalFitness';

    public string $workoutPlanNotes = '';

    public bool $showWorkoutDayEditor = false;

    public ?string $selectedWorkoutPlanId = null;

    public ?string $selectedWorkoutDayId = null;

    public string $workoutDayTitle = '';

    public string $workoutDayNotes = '';

    public string $workoutDayFocus = 'fullBody';

    public string $workoutDayEstimatedDurationMinutes = '';

    public ?string $selectedWorkoutBlockId = null;

    public string $exerciseSearch = '';

    public string $exerciseCategory = '';

    public string $exerciseMuscle = '';

    /**
     * @var list<array{id: string, type: string, notes: string, exercises: list<array{id: string, exercise: string, name: string, notes: string, sets: int, reps_min: int, reps_max: int, target_weight_kg: string, rest_seconds: int}>}>
     */
    public array $workoutDayBlocks = [];

    protected ExerciseCatalog $exerciseCatalog;

    public function mount(): void
    {
        $this->planPromptPreview = new PlanPromptPreview;
    }

    public function boot(ExerciseCatalog $exerciseCatalog): void
    {
        $this->exerciseCatalog = $exerciseCatalog;
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function clients(): Collection
    {
        return auth()->user()
            ->clients(CoachingEnrollmentStatus::Active)
            ->orderBy('users.name')
            ->get();
    }

    #[Computed]
    public function selectedClient(): ?User
    {
        if ($this->selectedClientId === null) {
            return null;
        }

        return auth()->user()
            ->clients(CoachingEnrollmentStatus::Active)
            ->with([
                'workoutPlans' => fn (HasMany $query): HasMany => $query
                    ->orderByDesc('starts_on')
                    ->orderByDesc('created_at')
                    ->with([
                        'workoutDays' => fn (HasMany $query): HasMany => $query->orderBy('order_index'),
                    ]),
                'bodyMetrics' => fn (HasMany $query): HasMany => $query
                    ->select(['id', 'user_id', 'type', 'value', 'recorded_at'])
                    ->orderBy('recorded_at'),
            ])
            ->whereKey($this->selectedClientId)
            ->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
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

            $matchesSearch = $search === '' || Str::contains(
                Str::lower(implode(' ', [(string) ($exercise['id'] ?? ''), (string) ($exercise['name'] ?? '')])),
                $search,
            );
            $matchesCategory = $this->exerciseCategory === '' || ($exercise['category'] ?? null) === $this->exerciseCategory;
            $muscles = array_merge($exercise['primaryMuscles'] ?? [], $exercise['secondaryMuscles'] ?? []);
            $selectedMajorMuscleGroup = MajorMuscleGroup::tryFrom($this->exerciseMuscle);
            $matchesMuscle = $this->exerciseMuscle === '' || in_array($this->exerciseMuscle, $muscles, true)
                || ($selectedMajorMuscleGroup !== null && collect($muscles)->map(fn (string $muscle): ?MuscleGroup => MuscleGroup::tryFrom($muscle))->contains(fn (?MuscleGroup $muscle): bool => $muscle?->majorGroup() === $selectedMajorMuscleGroup));

            return $matchesSearch && $matchesCategory && $matchesMuscle;
        }));
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function categories(): array
    {
        /** @var list<array<string, mixed>> $categories */
        $categories = Arr::get($this->exerciseCatalog->all(), 'referenceData.exerciseCategories', []);

        return $categories;
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function muscleGroups(): array
    {
        /** @var list<array<string, mixed>> $muscleGroups */
        $muscleGroups = array_merge(Arr::get($this->exerciseCatalog->all(), 'referenceData.majorMuscleGroups', []), Arr::get($this->exerciseCatalog->all(), 'referenceData.muscleGroups', []));

        return $muscleGroups;
    }

    public function selectClient(string $clientId): void
    {
        if (! auth()->user()->clients(CoachingEnrollmentStatus::Active)
            ->whereKey($clientId)
            ->exists()) {
            return;
        }

        $this->selectedClientId = $clientId;
        $this->selectedTab = 'info';
        $this->showProgressionAnalysis = false;
        $this->resetNextPlanPromptPreview();
        $this->showClientDetail = true;
    }

    public function selectTab(string $tab): void
    {
        if (! in_array($tab, ['info', 'training', 'nutrition', 'body-metrics'], true)) {
            return;
        }

        $this->selectedTab = $tab;
    }

    public function closeClientDetail(): void
    {
        $this->showClientDetail = false;
        $this->showProgressionAnalysis = false;
        $this->resetNextPlanPromptPreview();
    }

    public function openProgressionAnalysis(): void
    {
        if ($this->selectedClient === null) {
            return;
        }

        $this->showProgressionAnalysis = true;
    }

    public function closeProgressionAnalysis(): void
    {
        $this->showProgressionAnalysis = false;
    }

    public function openNextPlanPrompt(WorkoutPlanAIGenerator $generator): void
    {
        $client = $this->selectedClient;

        if ($client === null) {
            return;
        }

        try {
            $this->planPromptPreview->show(__('Next plan prompt'), $generator->preview($client));
        } catch (RuntimeException $exception) {
            $this->planPromptPreview->showError(__('Next plan prompt'), $exception->getMessage());
        }
    }

    public function openNutritionPlanPrompt(NutritionPlanAIGenerator $generator): void
    {
        $client = $this->selectedClient;

        if ($client === null) {
            return;
        }

        try {
            $this->planPromptPreview->show(__('Nutrition plan prompt'), $generator->preview($client));
        } catch (RuntimeException $exception) {
            $this->planPromptPreview->showError(__('Nutrition plan prompt'), $exception->getMessage());
        }
    }

    private function resetNextPlanPromptPreview(): void
    {
        $this->planPromptPreview->reset();
    }

    #[Computed]
    public function progressionAnalysis(): ?ProgressionAnalysis
    {
        $client = $this->selectedClient;

        if ($client === null) {
            return null;
        }

        return new WorkoutProgressionAnalyzer(
            new TrainingHistory($client),
            $this->exerciseCatalog,
        )->analyze();
    }

    public function openWorkoutPlanForm(): void
    {
        if ($this->selectedClient === null) {
            return;
        }

        $this->resetValidation();
        $this->workoutPlanName = '';
        $this->workoutPlanGoal = 'generalFitness';
        $this->workoutPlanNotes = '';
        $this->showWorkoutPlanForm = true;
    }

    public function saveWorkoutPlan(): void
    {
        $validated = $this->validate([
            'workoutPlanName' => ['required', 'string', 'max:255'],
            'workoutPlanGoal' => ['required', 'string', 'max:255'],
            'workoutPlanNotes' => ['nullable', 'string'],
        ]);
        $client = $this->selectedClient;

        if ($client === null) {
            return;
        }

        $workoutPlan = new WorkoutPlan;
        $workoutPlan->forceFill([
            'user_id' => $client->id,
            'name' => $validated['workoutPlanName'],
            'goal' => $validated['workoutPlanGoal'],
            'notes' => $validated['workoutPlanNotes'] ?: null,
            'status' => 'draft',
        ]);
        $workoutPlan->save();

        unset($this->selectedClient);

        $this->showWorkoutPlanForm = false;
    }

    public function openWorkoutDayEditor(string $workoutPlanId): void
    {
        $workoutPlan = $this->selectedClient?->workoutPlans()->whereKey($workoutPlanId)->first();

        if ($workoutPlan === null) {
            return;
        }

        $this->resetValidation();
        $this->selectedWorkoutPlanId = $workoutPlan->id;
        $this->selectedWorkoutDayId = null;
        $this->workoutDayTitle = '';
        $this->workoutDayNotes = '';
        $this->workoutDayFocus = 'fullBody';
        $this->workoutDayEstimatedDurationMinutes = '';
        $this->workoutDayBlocks = [];
        $this->addWorkoutBlock();
        $this->exerciseSearch = '';
        $this->exerciseCategory = '';
        $this->exerciseMuscle = '';
        $this->showWorkoutDayEditor = true;
    }

    public function openExistingWorkoutDayEditor(string $workoutPlanId, string $workoutDayId): void
    {
        $workoutPlan = $this->selectedClient?->workoutPlans()->whereKey($workoutPlanId)->first();

        if ($workoutPlan === null) {
            return;
        }

        $workoutDay = $workoutPlan->workoutDays()
            ->with(['blocks' => fn (HasMany $query): HasMany => $query->orderBy('order_index')->with([
                'exercises' => fn (HasMany $query): HasMany => $query->orderBy('order_index'),
            ])])
            ->whereKey($workoutDayId)
            ->first();

        if ($workoutDay === null) {
            return;
        }

        $exerciseNames = collect(Arr::get($this->exerciseCatalog->all(), 'exercises', []))
            ->filter(fn (mixed $exercise): bool => is_array($exercise))
            ->keyBy('id');

        $this->resetValidation();
        $this->selectedWorkoutPlanId = $workoutPlan->id;
        $this->selectedWorkoutDayId = $workoutDay->id;
        $this->workoutDayTitle = $workoutDay->title;
        $this->workoutDayNotes = $workoutDay->notes ?? '';
        $this->workoutDayFocus = $workoutDay->focus;
        $this->workoutDayEstimatedDurationMinutes = $workoutDay->estimated_duration_minutes === null ? '' : (string) $workoutDay->estimated_duration_minutes;
        $this->workoutDayBlocks = $workoutDay->blocks->map(fn (WorkoutBlock $block): array => [
            'id' => $block->id,
            'type' => $block->type,
            'notes' => $block->notes ?? '',
            'exercises' => $block->exercises->map(fn (PlannedExercise $exercise): array => [
                'id' => $exercise->id,
                'exercise' => $exercise->exercise,
                'name' => (string) data_get($exerciseNames->get($exercise->exercise), 'name', $exercise->exercise),
                'notes' => $exercise->notes ?? '',
                'sets' => $exercise->sets ?? 3,
                'reps_min' => $exercise->reps_min ?? 8,
                'reps_max' => $exercise->reps_max ?? 12,
                'target_weight_kg' => $exercise->target_weight_kg ?? '',
                'rest_seconds' => $exercise->rest_seconds ?? 90,
            ])->all(),
        ])->all();
        $this->selectedWorkoutBlockId = $this->workoutDayBlocks[0]['id'] ?? null;
        $this->exerciseSearch = '';
        $this->exerciseCategory = '';
        $this->exerciseMuscle = '';
        $this->showWorkoutDayEditor = true;
    }

    public function closeWorkoutDayEditor(): void
    {
        $this->showWorkoutDayEditor = false;
    }

    public function addWorkoutBlock(): void
    {
        $blockId = (string) Str::uuid();
        $this->workoutDayBlocks[] = [
            'id' => $blockId,
            'type' => 'standard',
            'notes' => '',
            'exercises' => [],
        ];
        $this->selectedWorkoutBlockId = $blockId;
    }

    public function selectWorkoutBlock(string $blockId): void
    {
        if (collect($this->workoutDayBlocks)->contains('id', $blockId)) {
            $this->selectedWorkoutBlockId = $blockId;
        }
    }

    public function removeWorkoutBlock(string $blockId): void
    {
        $this->workoutDayBlocks = array_values(array_filter(
            $this->workoutDayBlocks,
            fn (array $block): bool => $block['id'] !== $blockId,
        ));

        if ($this->selectedWorkoutBlockId === $blockId) {
            $this->selectedWorkoutBlockId = $this->workoutDayBlocks[0]['id'] ?? null;
        }
    }

    public function addExercise(string $exerciseId): void
    {
        if ($this->selectedWorkoutBlockId === null) {
            return;
        }

        $exercise = collect($this->availableExercises)->firstWhere('id', $exerciseId);

        if (! is_array($exercise)) {
            return;
        }

        foreach ($this->workoutDayBlocks as $blockIndex => $block) {
            if ($block['id'] !== $this->selectedWorkoutBlockId) {
                continue;
            }

            $this->workoutDayBlocks[$blockIndex]['exercises'][] = [
                'id' => (string) Str::uuid(),
                'exercise' => $exerciseId,
                'name' => (string) $exercise['name'],
                'notes' => '',
                'sets' => 3,
                'reps_min' => 8,
                'reps_max' => 12,
                'target_weight_kg' => '',
                'rest_seconds' => 90,
            ];

            return;
        }
    }

    public function removeExercise(string $blockId, string $exerciseId): void
    {
        foreach ($this->workoutDayBlocks as $blockIndex => $block) {
            if ($block['id'] !== $blockId) {
                continue;
            }

            $this->workoutDayBlocks[$blockIndex]['exercises'] = array_values(array_filter(
                $block['exercises'],
                fn (array $exercise): bool => $exercise['id'] !== $exerciseId,
            ));

            return;
        }
    }

    public function saveWorkoutDay(): void
    {
        $validated = $this->validate([
            'workoutDayTitle' => ['required', 'string', 'max:255'],
            'workoutDayNotes' => ['nullable', 'string'],
            'workoutDayFocus' => ['required', 'string', 'max:255'],
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
        $client = $this->selectedClient;
        $workoutPlan = $client?->workoutPlans()->whereKey($this->selectedWorkoutPlanId)->first();

        if ($client === null || $workoutPlan === null) {
            return;
        }

        DB::transaction(function () use ($client, $workoutPlan, $validated): void {
            $workoutDay = $this->selectedWorkoutDayId === null
                ? new WorkoutDay
                : $workoutPlan->workoutDays()->with('blocks.exercises')->whereKey($this->selectedWorkoutDayId)->first();

            if ($workoutDay === null) {
                return;
            }

            $workoutDay->forceFill([
                'user_id' => $client->id,
                'workout_plan_id' => $workoutPlan->id,
                'title' => $validated['workoutDayTitle'],
                'focus' => $validated['workoutDayFocus'],
                'status' => 'planned',
                'estimated_duration_minutes' => $validated['workoutDayEstimatedDurationMinutes'] === '' ? null : $validated['workoutDayEstimatedDurationMinutes'],
                'notes' => $validated['workoutDayNotes'] ?: null,
            ]);

            if (! $workoutDay->exists) {
                $workoutDay->order_index = ((int) $workoutPlan->workoutDays()->max('order_index')) + 1;
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

                $existingExercises = $workoutBlock->exists && $existingBlocks->has($workoutBlock->id)
                    ? $existingBlocks->get($workoutBlock->id)->exercises->keyBy('id')
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

        unset($this->selectedClient);

        $this->showWorkoutDayEditor = false;
    }
};
?>

<div>
    <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-900">
        <div class="mb-6">
            <flux:heading size="lg" level="1">{{ __('Clients') }}</flux:heading>
            <flux:text variant="subtle">{{ __('Clients enrolled in your coaching service.') }}</flux:text>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Client') }}</flux:table.column>
                <flux:table.column>{{ __('Email') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->clients as $client)
                    <flux:table.row :key="$client->id" wire:click="selectClient('{{ $client->id }}')" class="cursor-pointer">
                        <flux:table.cell variant="strong">
                            <div class="flex items-center gap-3">
                                <x-user-avatar :user="$client" />
                                <span>{{ $client->name }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $client->email }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="2" class="py-8 text-center">{{ __('No clients have been added yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    @if ($this->selectedClient)
        <flux:modal wire:model="showClientDetail" :closable="false" class="w-full max-w-3xl">
            <x-dashboard.client-detail-panel
                :client="$this->selectedClient"
                :active-tab="$selectedTab"
                :progression-analysis="$this->showProgressionAnalysis ? $this->progressionAnalysis : null"
                :show-progression-analysis="$showProgressionAnalysis"
            />
        </flux:modal>
    @endif

    <x-dashboard.next-plan-prompt-preview
        wire:model="planPromptPreview.isOpen"
        :title="$planPromptPreview->title"
        :instructions="$planPromptPreview->instructions"
        :prompt="$planPromptPreview->prompt"
        :schema="$planPromptPreview->schema"
        :error="$planPromptPreview->error"
    />

    <flux:modal wire:model="showWorkoutPlanForm" class="w-full max-w-lg">
        <form wire:submit="saveWorkoutPlan" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('New workout plan') }}</flux:heading>
                <flux:text variant="subtle" class="mt-1">{{ __('Create a draft plan for this client.') }}</flux:text>
            </div>

            <flux:field>
                <flux:label>{{ __('Name') }}</flux:label>
                <flux:input wire:model="workoutPlanName" autofocus />
                <flux:error name="workoutPlanName" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Goal') }}</flux:label>
                <flux:select wire:model="workoutPlanGoal">
                    @foreach (\App\Enums\Goal::allCasesArray() as $goal)
                        <option value="{{ $goal }}">{{ str($goal)->headline() }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="workoutPlanGoal" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Notes') }}</flux:label>
                <flux:textarea wire:model="workoutPlanNotes" rows="4" />
                <flux:error name="workoutPlanNotes" />
            </flux:field>

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Create workout plan') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showWorkoutDayEditor" :closable="false" class="w-full max-w-7xl p-0">
        <x-dashboard.workout-day-editor
            :blocks="$workoutDayBlocks"
            :available-exercises="$this->availableExercises"
            :categories="$this->categories"
            :muscle-groups="$this->muscleGroups"
            :selected-workout-block-id="$selectedWorkoutBlockId"
        />
    </flux:modal>
</div>
