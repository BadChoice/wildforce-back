<?php

use App\Enums\CoachingEnrollmentStatus;
use App\Enums\Generated\MajorMuscleGroup;
use App\Enums\Generated\MuscleGroup;
use App\Enums\WorkoutFocus;
use App\Enums\WorkoutKind;
use App\Models\PlannedExercise;
use App\Models\WorkoutBlock;
use App\Models\WorkoutDay;
use App\Services\ExerciseCatalog\ExerciseCatalog;
use App\Services\Workouts\SingleWorkoutFromTextGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public bool $showWorkoutDayEditor = false;
    public bool $showTemplateFromText = false;

    public ?string $selectedWorkoutDayId = null;

    public string $workoutDayTitle = '';

    public string $workoutDayNotes = '';

    public string $workoutDayFocus = 'fullBody';

    public string $workoutDayEstimatedDurationMinutes = '';
    public string $workoutText = '';

    public ?string $selectedWorkoutBlockId = null;

    public string $exerciseSearch = '';

    public string $exerciseCategory = '';

    public string $exerciseMuscle = '';

    /**
     * @var list<array{id: string, type: string, notes: string, exercises: list<array{id: string, exercise: string, name: string, notes: string, sets: int, reps_min: int, reps_max: int, target_weight_kg: string, rest_seconds: int}>}>
     */
    public array $workoutDayBlocks = [];

    protected ExerciseCatalog $exerciseCatalog;

    public function boot(ExerciseCatalog $exerciseCatalog): void
    {
        $this->exerciseCatalog = $exerciseCatalog;
    }

    public function mount(): void
    {
        abort_unless(auth()->user()->clients(CoachingEnrollmentStatus::Active)->exists(), 403);
    }

    /**
     * @return Collection<int, WorkoutDay>
     */
    #[Computed]
    public function templates(): Collection
    {
        return auth()->user()->workoutDays()
            ->templates()
            ->with([
                'blocks' => fn (HasMany $query): HasMany => $query->orderBy('order_index'),
                'blocks.exercises' => fn (HasMany $query): HasMany => $query->orderBy('order_index'),
            ])
            ->orderByDesc('updated_at')
            ->get();
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

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function exerciseNames(): array
    {
        return collect(Arr::get($this->exerciseCatalog->all(), 'exercises', []))
            ->filter(fn (mixed $exercise): bool => is_array($exercise))
            ->mapWithKeys(fn (array $exercise): array => [(string) $exercise['id'] => (string) $exercise['name']])
            ->all();
    }

    public function openWorkoutDayEditor(): void
    {
        $this->resetValidation();
        $this->workoutDayTitle = '';
        $this->workoutDayNotes = '';
        $this->workoutDayFocus = 'fullBody';
        $this->workoutDayEstimatedDurationMinutes = '';
        $this->selectedWorkoutDayId = null;
        $this->workoutDayBlocks = [];
        $this->addWorkoutBlock();
        $this->exerciseSearch = '';
        $this->exerciseCategory = '';
        $this->exerciseMuscle = '';
        $this->showWorkoutDayEditor = true;
    }

    public function createTemplateFromScratch(): void
    {
        $this->openWorkoutDayEditor();
    }

    public function openTemplateFromText(): void
    {
        $this->resetValidation();
        $this->workoutText = '';
        $this->showTemplateFromText = true;
    }

    public function generateTemplateFromText(SingleWorkoutFromTextGenerator $generator): void
    {
        $validated = $this->validate([
            'workoutText' => ['required', 'string', 'max:20000'],
        ]);

        if (! auth()->user()->canUseAi()) {
            $this->addError('workoutText', __('The monthly AI usage limit has been reached.'));

            return;
        }

        $workoutDay = $generator->generate(auth()->user(), $validated['workoutText']);

        $this->selectedWorkoutDayId = null;
        $this->fillWorkoutDayEditor($workoutDay);
        $this->workoutText = '';
        $this->showTemplateFromText = false;
        $this->showWorkoutDayEditor = true;
    }

    public function openExistingWorkoutDayEditor(string $templateId): void
    {
        $template = auth()->user()->workoutDays()
            ->where('kind', WorkoutKind::Template)
            ->with(['blocks' => fn (HasMany $query): HasMany => $query->orderBy('order_index')->with([
                'exercises' => fn (HasMany $query): HasMany => $query->orderBy('order_index'),
            ])])
            ->whereKey($templateId)
            ->first();

        if ($template === null) {
            return;
        }

        $exerciseNames = collect(Arr::get($this->exerciseCatalog->all(), 'exercises', []))
            ->filter(fn (mixed $exercise): bool => is_array($exercise))
            ->keyBy('id');

        $this->resetValidation();
        $this->selectedWorkoutDayId = $template->id;
        $this->fillWorkoutDayEditor($template, $exerciseNames);
        $this->exerciseSearch = '';
        $this->exerciseCategory = '';
        $this->exerciseMuscle = '';
        $this->showWorkoutDayEditor = true;
    }

    private function formatSetStyleConfiguration(mixed $config): ?array
    {
        if (! is_array($config) || empty($config['style'])) {
            return null;
        }

        $style = \App\Enums\ExerciseSetStyle::tryFrom($config['style']);
        if ($style === null) {
            return null;
        }

        $allowed = $style->allowedFields();
        $filtered = ['style' => $style->value];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $config) && $config[$field] !== '' && $config[$field] !== null) {
                if ($field === 'applies_to_final_set_only') {
                    $filtered[$field] = (bool) $config[$field];
                } elseif (in_array($field, ['drop_count', 'backoff_set_count', 'intra_set_rest_seconds', 'target_rir'], true)) {
                    $filtered[$field] = (int) $config[$field];
                } elseif (in_array($field, ['drop_weight_percent', 'backoff_weight_percent'], true)) {
                    $filtered[$field] = (float) $config[$field];
                } else {
                    $filtered[$field] = (string) $config[$field];
                }
            }
        }

        return $filtered;
    }

    /** @param \Illuminate\Support\Collection<string, array<string, mixed>>|null $exerciseNames */
    private function fillWorkoutDayEditor(WorkoutDay $workoutDay, ?\Illuminate\Support\Collection $exerciseNames = null): void
    {
        $exerciseNames ??= collect(Arr::get($this->exerciseCatalog->all(), 'exercises', []))
            ->filter(fn (mixed $exercise): bool => is_array($exercise))
            ->keyBy('id');
        $this->workoutDayTitle = $workoutDay->title;
        $this->workoutDayNotes = $workoutDay->notes ?? '';
        $this->workoutDayFocus = $workoutDay->focus->value;
        $this->workoutDayEstimatedDurationMinutes = $workoutDay->estimated_duration_minutes === null ? '' : (string) $workoutDay->estimated_duration_minutes;
        $this->workoutDayBlocks = $workoutDay->blocks->map(fn (WorkoutBlock $block): array => [
            'id' => filled($block->id) ? $block->id : (string) Str::uuid(),
            'type' => $block->type->value,
            'notes' => $block->notes ?? '',
            'exercises' => $block->exercises->map(fn (PlannedExercise $exercise): array => [
                'id' => filled($exercise->id) ? $exercise->id : (string) Str::uuid(),
                'exercise' => $exercise->exercise,
                'name' => (string) data_get($exerciseNames->get($exercise->exercise), 'name', $exercise->exercise),
                'notes' => $exercise->notes ?? '',
                'sets' => $exercise->sets ?? 3,
                'reps_min' => $exercise->reps_min ?? 8,
                'reps_max' => $exercise->reps_max ?? 12,
                'target_weight_kg' => $exercise->target_weight_kg ?? '',
                'rest_seconds' => $exercise->rest_seconds ?? 90,
                'set_style_configuration' => $exercise->set_style_configuration?->toArray(),
            ])->all(),
        ])->all();
        $this->selectedWorkoutBlockId = $this->workoutDayBlocks[0]['id'] ?? null;
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
                'set_style_configuration' => null,
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
            'workoutDayBlocks.*.exercises.*.set_style_configuration' => ['nullable', 'array'],
        ]);

        DB::transaction(function () use ($validated): void {
            $workoutDay = $this->selectedWorkoutDayId === null
                ? new WorkoutDay
                : auth()->user()->workoutDays()
                    ->where('kind', WorkoutKind::Template)
                    ->with('blocks.exercises')
                    ->whereKey($this->selectedWorkoutDayId)
                    ->first();

            if ($workoutDay === null) {
                return;
            }

            $workoutDay->forceFill([
                'title' => $validated['workoutDayTitle'],
                'focus' => $validated['workoutDayFocus'],
                'estimated_duration_minutes' => $validated['workoutDayEstimatedDurationMinutes'] === '' ? null : $validated['workoutDayEstimatedDurationMinutes'],
                'notes' => $validated['workoutDayNotes'] ?: null,
            ]);

            if (! $workoutDay->exists) {
                $workoutDay->forceFill([
                    'user_id' => auth()->id(),
                    'kind' => WorkoutKind::Template,
                    'status' => 'draft',
                    'creation_source' => 'manual',
                ]);
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

                $existingExercises = $existingBlocks->has($workoutBlock->id)
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
                        'set_style_configuration' => $this->formatSetStyleConfiguration($exerciseData['set_style_configuration'] ?? null),
                        'notes' => $exerciseData['notes'] ?: null,
                    ]);
                    $plannedExercise->save();
                }
            }
        });

        $this->showWorkoutDayEditor = false;
    }

    public function deleteTemplate(string $templateId): void
    {
        auth()->user()->workoutDays()
            ->where('kind', WorkoutKind::Template)
            ->whereKey($templateId)
            ->first()?->delete();
    }
};
?>

<div class="space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="lg" level="1">{{ __('Workout templates') }}</flux:heading>
            <flux:text variant="subtle">{{ __('Reusable workout days for your clients’ plans.') }}</flux:text>
        </div>
        <flux:dropdown position="bottom" align="end">
            <flux:button variant="primary" icon="plus">{{ __('New template') }}</flux:button>

            <flux:menu>
                <flux:menu.item icon="file-pen-line" wire:click="createTemplateFromScratch">{{ __('From scratch') }}</flux:menu.item>
                <flux:menu.item icon="sparkles" wire:click="openTemplateFromText">{{ __('From text') }}</flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    </div>

    <div class="space-y-4">
        @forelse ($this->templates as $template)
            <section wire:key="workout-template-{{ $template->id }}" class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <flux:heading size="sm">{{ $template->title }}</flux:heading>
                        <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-sm text-zinc-500 dark:text-zinc-400">
                            <span>{{ str($template->focus->value)->headline() }}</span>

                            <flux:separator vertical="true" />

                            @if ($template->estimated_duration_minutes)
                                <span>{{ trans_choice(':count min', $template->estimated_duration_minutes, ['count' => $template->estimated_duration_minutes]) }}</span>
                            @endif

                            <flux:separator vertical="true" />
                            {{ $template->exercisesCount() }} {{ __('Exercises') }}
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="openExistingWorkoutDayEditor('{{ $template->id }}')">{{ __('Edit') }}</flux:button>
                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="deleteTemplate('{{ $template->id }}')" wire:confirm="{{ __('Delete this workout template?') }}" aria-label="{{ __('Delete :template', ['template' => $template->title]) }}" />
                    </div>
                </div>

                <div class="mt-5 flex gap-3 overflow-x-auto pb-1">
                    @forelse ($template->blocks as $blockIndex => $block)
                        <section class="min-w-64 rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/60">
                            <p class="mb-3 text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Block :number', ['number' => $blockIndex + 1]) }}</p>
                            <div class="flex gap-3 overflow-x-auto">
                                @forelse ($block->exercises as $exercise)
                                    <div class="w-20 shrink-0 text-left" title="{{ $exercise->exercise }}">
                                        <img src="{{ $exercise->imageUrl() }}" alt="" class="h-16 w-14 rounded-md bg-zinc-200 object-cover dark:bg-zinc-700" loading="lazy" />
                                        <p class="mt-1 line-clamp-2 text-xs font-medium text-zinc-700 dark:text-zinc-200">{{ $this->exerciseNames[$exercise->exercise] ?? $exercise->exercise }}</p>
                                    </div>
                                @empty
                                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No exercises yet.') }}</p>
                                @endforelse
                            </div>
                        </section>
                    @empty
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No blocks have been added yet.') }}</p>
                    @endforelse
                </div>
            </section>
        @empty
            <div class="rounded-2xl border border-dashed border-zinc-300 px-4 py-10 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                {{ __('Create a workout template to reuse it in a client plan.') }}
            </div>
        @endforelse
    </div>

    <flux:modal wire:model="showWorkoutDayEditor" class="w-full max-w-6xl p-0">
        <x-dashboard.workout-day-editor
            :blocks="$workoutDayBlocks"
            :available-exercises="$this->availableExercises"
            :categories="$this->categories"
            :muscle-groups="$this->muscleGroups"
            :selected-workout-block-id="$selectedWorkoutBlockId"
        />
    </flux:modal>

    <flux:modal wire:model="showTemplateFromText" class="w-full max-w-2xl">
        <div class="space-y-4"><div><flux:heading size="lg">{{ __('Create template from text') }}</flux:heading><flux:text variant="subtle">{{ __('Paste a workout and review it before saving.') }}</flux:text></div><flux:field><flux:label>{{ __('Workout text') }}</flux:label><flux:textarea wire:model="workoutText" rows="12" placeholder="{{ __('1. Row — 59 kg — 3 sets') }}" /><flux:error name="workoutText" /></flux:field><div class="flex justify-end"><flux:button variant="primary" wire:click="generateTemplateFromText" wire:loading.attr="disabled">{{ __('Generate template') }}</flux:button></div></div>
    </flux:modal>
</div>
