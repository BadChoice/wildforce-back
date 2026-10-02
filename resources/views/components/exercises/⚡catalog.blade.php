<?php

use App\Models\ExerciseProfile;
use App\Models\User;
use App\Enums\Generated\MajorMuscleGroup;
use App\Enums\Generated\MuscleGroup;
use App\Services\ExerciseCatalog\ExerciseCatalog;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public ?string $userId = null;

    public string $search = '';

    public string $category = '';

    public string $muscle = '';

    public ?string $selectedExerciseId = null;

    public bool $showExerciseDetail = false;

    public string $selectedExerciseTab = 'details';

    protected ExerciseCatalog $exerciseCatalog;

    public function boot(ExerciseCatalog $exerciseCatalog): void
    {
        $this->exerciseCatalog = $exerciseCatalog;
    }

    public function mount(?User $user = null): void
    {
        $this->userId = $user?->id;
    }

    #[Computed]
    public function user(): ?User
    {
        if ($this->userId === null) {
            return null;
        }

        return User::query()->find($this->userId);
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function catalog(): array
    {
        return $this->exerciseCatalog->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function exercises(): array
    {
        $exercises = Arr::get($this->catalog, 'exercises', []);
        if (! is_array($exercises)) {
            return [];
        }

        $exercises = array_values(array_filter($exercises, function (mixed $exercise): bool {
            if (! is_array($exercise)) {
                return false;
            }

            $matchesSearch = $this->search === '' || Str::contains(
                Str::lower(implode(' ', [
                    (string) ($exercise['id'] ?? ''),
                    (string) ($exercise['name'] ?? ''),
                    (string) ($exercise['description'] ?? ''),
                ])),
                Str::lower($this->search),
            );
            $matchesCategory = $this->category === '' || ($exercise['category'] ?? null) === $this->category;
            $muscles = array_merge($exercise['primaryMuscles'] ?? [], $exercise['secondaryMuscles'] ?? []);
            $selectedMajorMuscleGroup = MajorMuscleGroup::tryFrom($this->muscle);
            $matchesMuscle = $this->muscle === ''
                || in_array($this->muscle, $muscles, true)
                || ($selectedMajorMuscleGroup !== null && collect($muscles)
                    ->map(fn (string $muscle): ?MuscleGroup => MuscleGroup::tryFrom($muscle))
                    ->contains(fn (?MuscleGroup $muscle): bool => $muscle?->majorGroup() === $selectedMajorMuscleGroup));

            return $matchesSearch && $matchesCategory && $matchesMuscle;
        }));
        usort($exercises, fn (array $left, array $right): int => ($left['name'] ?? '') <=> ($right['name'] ?? ''));

        return $exercises;
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function categories(): array
    {
        /** @var list<array<string, mixed>> $categories */
        $categories = Arr::get($this->catalog, 'referenceData.exerciseCategories', []);

        return $categories;
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function muscleGroups(): array
    {
        /** @var list<array<string, mixed>> $muscleGroups */
        $muscleGroups = array_merge(
            Arr::get($this->catalog, 'referenceData.majorMuscleGroups', []),
            Arr::get($this->catalog, 'referenceData.muscleGroups', []),
        );

        return $muscleGroups;
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function selectedExercise(): ?array
    {
        if ($this->selectedExerciseId === null) {
            return null;
        }

        foreach (Arr::get($this->catalog, 'exercises', []) as $exercise) {
            if (is_array($exercise) && ($exercise['id'] ?? null) === $this->selectedExerciseId) {
                return $exercise;
            }
        }

        return null;
    }

    #[Computed]
    public function selectedExerciseProfile(): ?ExerciseProfile
    {
        if ($this->user === null || $this->selectedExerciseId === null) {
            return null;
        }

        return $this->user->exerciseProfiles()
            ->where('exercise', $this->selectedExerciseId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $exercise
     */
    public function imageUrl(array $exercise, string $gender = 'female'): string
    {
        return $this->exerciseCatalog->imageUrl($exercise['id'], $gender);
    }

    public function selectExercise(string $exerciseId): void
    {
        foreach (Arr::get($this->catalog, 'exercises', []) as $exercise) {
            if (is_array($exercise) && ($exercise['id'] ?? null) === $exerciseId) {
                $this->selectedExerciseId = $exerciseId;
                $this->selectedExerciseTab = 'details';
                $this->showExerciseDetail = true;

                return;
            }
        }
    }

    public function closeExerciseDetail(): void
    {
        $this->showExerciseDetail = false;
    }

    public function selectExerciseTab(string $tab): void
    {
        $tabs = ['details', 'instructions'];

        if ($this->user !== null) {
            $tabs[] = 'profile';
        }

        if (! in_array($tab, $tabs, true)) {
            return;
        }

        $this->selectedExerciseTab = $tab;
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'category', 'muscle');
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
    <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-900">
        <div class="flex flex-col gap-1">
            <flux:heading size="lg" level="1">{{ __('Exercise catalog') }}</flux:heading>
            <flux:text variant="subtle">
                {{ trans_choice(':count exercise · catalog v:version', $this->catalog['metadata']['exerciseCount'], ['count' => $this->catalog['metadata']['exerciseCount'], 'version' => $this->catalog['metadata']['schemaVersion']]) }}
            </flux:text>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-4">
            <flux:field class="md:col-span-2">
                <flux:label>{{ __('Search') }}</flux:label>
                <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Name, identifier, or description') }}" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Category') }}</flux:label>
                <flux:select wire:model.live="category">
                    <option value="">{{ __('All categories') }}</option>
                    @foreach ($this->categories as $catalogCategory)
                        <option value="{{ $catalogCategory['id'] }}">{{ $catalogCategory['name'] }}</option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Muscle group') }}</flux:label>
                <flux:select wire:model.live="muscle">
                    <option value="">{{ __('All muscle groups') }}</option>
                    @foreach ($this->muscleGroups as $muscleGroup)
                        <option value="{{ $muscleGroup['id'] }}">{{ $muscleGroup['name'] }}</option>
                    @endforeach
                </flux:select>
            </flux:field>

            <div class="md:col-span-4">
                <flux:button wire:click="resetFilters" variant="ghost">{{ __('Reset') }}</flux:button>
            </div>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border p-2 border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Exercise') }}</flux:table.column>
                <flux:table.column>{{ __('Category') }}</flux:table.column>
                <flux:table.column>{{ __('Muscles') }}</flux:table.column>
                <flux:table.column>{{ __('Equipment') }}</flux:table.column>
                <flux:table.column>{{ __('Tracking') }}</flux:table.column>
                <flux:table.column>{{ __('MET') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->exercises as $exercise)
                    <flux:table.row :key="$exercise['id']">
                        <flux:table.cell variant="strong" wire:click="selectExercise('{{ $exercise['id'] }}')">
                            <div class="flex items-center gap-3">
                                <img src="{{ $this->imageUrl($exercise) }}" alt="" class="h-14 w-10 rounded-lg bg-zinc-100 object-cover dark:bg-zinc-800" loading="lazy" />
                                <div class="flex min-w-0 flex-col gap-0 items-start">
                                    <flux:button variant="ghost" size="sm"  class="-ml-2 w-fit px-2 font-semibold">
                                        {{ $exercise['name'] }}
                                    </flux:button>
                                    <span class="font-mono text-xs ml-1 font-normal text-zinc-500 dark:text-zinc-400">{{ $exercise['id'] }}</span>
                                </div>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <x-exercises.reference-icon :id="$exercise['category']" type="exerciseCategories" />
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap gap-x-2 gap-y-1 mt-1">
                                @foreach ($exercise['primaryMuscles'] as $muscle)
                                    <x-exercises.muscle-icon :muscle="$muscle" />
                                @endforeach
                                @if (count($exercise['secondaryMuscles']))
                                    <flux:separator vertical />
                                    @foreach ($exercise['secondaryMuscles'] as $muscle)
                                        <x-exercises.muscle-icon :muscle="$muscle" />
                                    @endforeach
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($exercise['requiredEquipment'] === [])
                                <span class="text-zinc-500 dark:text-zinc-400">{{ __('None') }}</span>
                            @else
                                <div class="flex flex-wrap gap-x-3 gap-y-1">
                                    @foreach ($exercise['requiredEquipment'] as $equipment)
                                        <x-exercises.equipment-icon :equipment="$equipment" />
                                    @endforeach
                                </div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $exercise['trackingMode'] }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($exercise['metValue'], 1) }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-8 text-center">{{ __('No exercises match the selected filters.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    @if ($this->selectedExercise)
        <flux:modal wire:model="showExerciseDetail" flyout position="right" :closable="false" class="w-full max-w-none p-0 sm:w-[34rem]">
            <x-exercises.exercise-detail-panel
                :exercise="$this->selectedExercise"
                :image-urls="[
                    'female' => $this->imageUrl($this->selectedExercise, 'female'),
                    'male' => $this->imageUrl($this->selectedExercise, 'male'),
                ]"
                :active-tab="$selectedExerciseTab"
                :user="$this->user"
                :exercise-profile="$this->selectedExerciseProfile"
            />
        </flux:modal>
    @endif
</div>
