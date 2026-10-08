<?php

use App\Enums\Generated\MajorMuscleGroup;
use App\Enums\Generated\MuscleGroup;
use App\Models\CoachExerciseContent;
use App\Models\ExerciseProfile;
use App\Models\User;
use App\Services\ExerciseCatalog\ExerciseCatalog;
use App\Support\YouTubeVideoId;
use Flux\Flux;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?string $userId = null;

    public string $search = '';

    public string $category = '';

    public string $muscle = '';

    public ?string $selectedExerciseId = null;

    public bool $showExerciseDetail = false;

    public string $selectedExerciseTab = 'details';

    public ?TemporaryUploadedFile $coachExerciseImage = null;

    public string $coachExerciseYoutubeUrl = '';

    public string $coachExerciseNotes = '';

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

    #[Computed]
    public function isCoach(): bool
    {
        return auth()->user()?->isCoach() ?? false;
    }

    /**
     * @return Collection<string, CoachExerciseContent>
     */
    #[Computed]
    public function coachExerciseContentByExercise(): Collection
    {
        if (! $this->isCoach) {
            return collect();
        }

        return auth()->user()->coachExerciseContent()
            ->get(['exercise', 'image_path', 'youtube_video_id'])
            ->keyBy('exercise');
    }

    #[Computed]
    public function selectedCoachExerciseContent(): ?CoachExerciseContent
    {
        if (! $this->isCoach || $this->selectedExerciseId === null) {
            return null;
        }

        return auth()->user()->coachExerciseContent()
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
                $this->resetCoachExerciseContentForm();

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

        if ($this->isCoach) {
            $tabs[] = 'coach';
        }

        if (! in_array($tab, $tabs, true)) {
            return;
        }

        $this->selectedExerciseTab = $tab;
    }

    public function saveCoachExerciseContent(): void
    {
        abort_unless($this->isCoach && $this->selectedExercise !== null, 403);

        $this->validate([
            'coachExerciseImage' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=2160,max_height=2700'],
            'coachExerciseYoutubeUrl' => ['nullable', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail): void {
                if (YouTubeVideoId::fromUrl((string) $value) === null) {
                    $fail(__('Enter a valid YouTube video URL.'));
                }
            }],
            'coachExerciseNotes' => ['nullable', 'string', 'max:5000'],
        ]);

        /** @var User $coach */
        $coach = auth()->user();
        $media = CoachExerciseContent::withTrashed()->firstOrNew([
            'coach_user_id' => $coach->id,
            'exercise' => $this->selectedExerciseId,
        ]);

        if ($this->coachExerciseImage !== null) {
            $previousImagePath = $media->image_path;
            $media->image_path = $this->coachExerciseImage->storePubliclyAs(
                'coach-exercise-images/'.Str::lower($coach->id),
                $this->selectedExerciseId.'-'.Str::lower(Str::random(8)).'.'.$this->coachExerciseImage->guessExtension(),
                (string) config('filesystems.public_storage_disc'),
            );

            if ($previousImagePath !== null) {
                Storage::disk((string) config('filesystems.public_storage_disc'))->delete($previousImagePath);
            }
        }

        $media->youtube_video_id = $this->coachExerciseYoutubeUrl === ''
            ? null
            : YouTubeVideoId::fromUrl($this->coachExerciseYoutubeUrl);
        $media->notes = $this->coachExerciseNotes === '' ? null : $this->coachExerciseNotes;

        $this->persistCoachExerciseContent($media);
    }

    public function removeCoachExerciseImage(): void
    {
        abort_unless($this->isCoach, 403);

        $media = $this->selectedCoachExerciseContent;

        if ($media?->image_path === null) {
            return;
        }

        Storage::disk((string) config('filesystems.public_storage_disc'))->delete($media->image_path);
        $media->image_path = null;

        $this->persistCoachExerciseContent($media);
    }

    /**
     * Save the content, soft deleting it once it no longer overrides anything so clients sync the removal.
     */
    private function persistCoachExerciseContent(CoachExerciseContent $media): void
    {
        if ($media->image_path === null && $media->youtube_video_id === null && $media->notes === null) {
            if ($media->exists && ! $media->trashed()) {
                $media->delete();
            }
        } else {
            $media->deleted_at = null;
            $media->save();
        }

        unset($this->selectedCoachExerciseContent, $this->coachExerciseContentByExercise);
        $this->resetCoachExerciseContentForm();

        Flux::toast(variant: 'success', text: __('Exercise content saved.'));
    }

    private function resetCoachExerciseContentForm(): void
    {
        $this->reset('coachExerciseImage');
        $this->resetValidation();
        $this->coachExerciseYoutubeUrl = $this->selectedCoachExerciseContent?->youtubeUrl() ?? '';
        $this->coachExerciseNotes = $this->selectedCoachExerciseContent?->notes ?? '';
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
                                @if ($coachMedia = $this->coachExerciseContentByExercise->get($exercise['id']))
                                    <flux:badge size="sm" color="lime" :title="__('You uploaded custom content for this exercise')">
                                        @if ($coachMedia->image_path)
                                            <flux:icon.photo variant="micro" />
                                        @endif
                                        @if ($coachMedia->youtube_video_id)
                                            <flux:icon.play variant="micro" />
                                        @endif
                                        <span class="ml-1">{{ __('Custom') }}</span>
                                    </flux:badge>
                                @endif
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
                :is-coach="$this->isCoach"
                :coach-exercise-content="$this->selectedCoachExerciseContent"
            />
        </flux:modal>
    @endif
</div>
