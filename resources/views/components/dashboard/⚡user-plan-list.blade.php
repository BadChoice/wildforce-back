<?php

use App\Enums\CoachingEnrollmentStatus;
use App\Enums\Equipment;
use App\Enums\SubscriptionStatus;
use App\Models\CoachingEnrollment;
use App\Models\TrainingLocation;
use App\Models\User;
use App\Services\ExerciseCatalog\ExerciseCatalog;
use App\Services\Nutrition\NutritionPlanAIGenerator;
use App\Services\Workouts\WorkoutPlanAIGenerator;
use App\Services\Workouts\Progression\ProgressionAnalysis;
use App\Services\Workouts\Progression\TrainingHistory;
use App\Services\Workouts\Progression\WorkoutProgressionAnalyzer;
use App\ViewModels\PlanPromptPreview;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public ?string $selectedUserId = null;

    public string $selectedTab = 'training';

    public bool $showUserDetail = false;

    public bool $showProgressionAnalysis = false;

    public bool $showCoachingEnrollmentForm = false;

    public string $coachingEnrollmentType = '';

    public string $relatedUserId = '';

    public PlanPromptPreview $planPromptPreview;

    public bool $showTrainingLocationModal = false;

    public ?string $editingLocationId = null;

    public string $locationName = '';

    public bool $locationIsDefault = false;

    public string $locationLatitude = '';

    public string $locationLongitude = '';

    public int $locationSortOrder = 0;

    /**
     * @var list<string>
     */
    public array $selectedEquipment = [];

    protected ExerciseCatalog $exerciseCatalog;

    public function boot(ExerciseCatalog $exerciseCatalog): void
    {
        $this->exerciseCatalog = $exerciseCatalog;
    }

    public function mount(): void
    {
        $this->planPromptPreview = new PlanPromptPreview;
        Gate::authorize('viewDashboard');
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function users(): Collection
    {
        return User::query()
            ->select(['id', 'name', 'email'])
            ->with('subscription')
            ->withCount([
                'workoutPlans',
                'workoutDays as custom_workouts_count' => fn(Builder $query): Builder => $query->customWorkouts(),
                'nutritionPlans',
            ])
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function selectedUser(): ?User
    {
        if ($this->selectedUserId === null) {
            return null;
        }

        return User::query()
            ->with([
                'trainingPreferences',
                'trainingLocations' => fn(HasMany $query): HasMany => $query->orderBy('sort_order'),
                'exerciseProfiles' => fn(HasMany $query): HasMany => $query->orderBy('exercise'),
                'workoutPlans' => fn(HasMany $query): HasMany => $query
                    ->orderByDesc('updated_at')
                    ->with([
                        'workoutDays' => fn(HasMany $query): HasMany => $query->orderBy('order_index'),
                    ]),
                'bodyMetrics' => fn(HasMany $query): HasMany => $query
                    ->select(['id', 'user_id', 'type', 'value', 'recorded_at'])
                    ->orderBy('recorded_at'),
                'subscription',
            ])
            ->find($this->selectedUserId);
    }

    public function selectUser(string $userId): void
    {
        if (!User::query()->whereKey($userId)->exists()) {
            return;
        }

        $this->selectedUserId = $userId;
        $this->selectedTab = 'training';
        $this->showProgressionAnalysis = false;
        $this->resetNextPlanPromptPreview();
        $this->showUserDetail = true;
    }

    public function selectTab(string $tab): void
    {
        if (!in_array($tab, ['training', 'exercise-profiles', 'nutrition', 'body-metrics', 'subscription', 'coaching'], true)) {
            return;
        }

        $this->selectedTab = $tab;
    }

    public function closeUserDetail(): void
    {
        $this->showUserDetail = false;
        $this->showProgressionAnalysis = false;
        $this->resetNextPlanPromptPreview();
    }

    /**
     * @return Collection<int, CoachingEnrollment>
     */
    #[Computed]
    public function coaches(): Collection
    {
        if ($this->selectedUserId === null) {
            return new Collection;
        }

        return CoachingEnrollment::query()
            ->where('client_user_id', $this->selectedUserId)
            ->with('coach:id,name,email')
            ->latest('starts_at')
            ->get();
    }

    /**
     * @return Collection<int, CoachingEnrollment>
     */
    #[Computed]
    public function coaching(): Collection
    {
        if ($this->selectedUserId === null) {
            return new Collection;
        }

        return CoachingEnrollment::query()
            ->where('coach_user_id', $this->selectedUserId)
            ->with('client:id,name,email')
            ->latest('starts_at')
            ->get();
    }

    #[Computed]
    public function canAddCoach(): bool
    {
        return !$this->coaches
            ->contains(fn(CoachingEnrollment $enrollment): bool => in_array(
                $enrollment->status,
                [CoachingEnrollmentStatus::Active, CoachingEnrollmentStatus::Paused],
                true,
            ));
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function availableUsers(): Collection
    {
        if ($this->selectedUserId === null) {
            return new Collection;
        }

        return User::query()
            ->select(['id', 'name', 'email'])
            ->whereKeyNot($this->selectedUserId)
            ->whereHas('subscription', function (Builder $query): void {
                $query
                    ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::GracePeriod])
                    ->where(function (Builder $query): void {
                        $query
                            ->whereNull('renews_at')
                            ->orWhere('renews_at', '>', now());
                    });
            })
            ->orderBy('name')
            ->get();
    }

    public function openCoachingEnrollmentForm(string $relationship): void
    {
        Gate::authorize('viewDashboard');

        if (!in_array($relationship, ['coach', 'client'], true)) {
            return;
        }

        if ($relationship === 'coach' && !$this->canAddCoach) {
            return;
        }

        $this->resetValidation();
        $this->coachingEnrollmentType = $relationship;
        $this->relatedUserId = '';
        $this->showCoachingEnrollmentForm = true;
    }

    public function saveCoachingEnrollment(): void
    {
        Gate::authorize('viewDashboard');

        if (!in_array($this->coachingEnrollmentType, ['coach', 'client'], true)) {
            return;
        }

        $selectedUser = $this->selectedUser;

        if ($selectedUser === null) {
            return;
        }

        $this->validate([
            'relatedUserId' => ['required', 'uuid', Rule::exists(User::class, 'id')],
        ]);

        $isRelatedUserActive = User::query()
            ->whereKey($this->relatedUserId)
            ->whereHas('subscription', function (Builder $query): void {
                $query
                    ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::GracePeriod])
                    ->where(function (Builder $query): void {
                        $query
                            ->whereNull('renews_at')
                            ->orWhere('renews_at', '>', now());
                    });
            })
            ->exists();

        if (!$isRelatedUserActive || $selectedUser->getKey() === $this->relatedUserId) {
            $this->addError('relatedUserId', __('Select an active user.'));

            return;
        }

        [$coachUserId, $clientUserId] = $this->coachingEnrollmentType === 'coach'
            ? [$this->relatedUserId, $selectedUser->id]
            : [$selectedUser->id, $this->relatedUserId];

        $hasActiveRelationship = CoachingEnrollment::query()
            ->where('coach_user_id', $coachUserId)
            ->where('client_user_id', $clientUserId)
            ->whereIn('status', [CoachingEnrollmentStatus::Active, CoachingEnrollmentStatus::Paused])
            ->exists();

        if ($hasActiveRelationship) {
            $this->addError('relatedUserId', __('This coaching relationship is already active.'));

            return;
        }

        CoachingEnrollment::create([
            'coach_user_id' => $coachUserId,
            'client_user_id' => $clientUserId,
            'status' => CoachingEnrollmentStatus::Active,
            'starts_at' => now(),
        ]);

        $this->showCoachingEnrollmentForm = false;
        $this->coachingEnrollmentType = '';
        $this->relatedUserId = '';
    }

    public function pauseCoachingEnrollment(string $enrollmentId): void
    {
        $this->updateCoachingEnrollmentStatus($enrollmentId, CoachingEnrollmentStatus::Paused);
    }

    public function resumeCoachingEnrollment(string $enrollmentId): void
    {
        $this->updateCoachingEnrollmentStatus($enrollmentId, CoachingEnrollmentStatus::Active);
    }

    public function endCoachingEnrollment(string $enrollmentId): void
    {
        Gate::authorize('viewDashboard');

        $enrollment = $this->selectedCoachingEnrollment($enrollmentId);

        if ($enrollment === null || $enrollment->status === CoachingEnrollmentStatus::Ended) {
            return;
        }

        $enrollment->update([
            'status' => CoachingEnrollmentStatus::Ended,
            'ends_at' => now(),
        ]);
    }

    public function reactivateCoachingEnrollment(string $enrollmentId): void
    {
        Gate::authorize('viewDashboard');

        $enrollment = $this->selectedCoachingEnrollment($enrollmentId);

        if ($enrollment === null || $enrollment->status !== CoachingEnrollmentStatus::Ended) {
            return;
        }

        $clientAlreadyHasCoach = CoachingEnrollment::query()
            ->where('client_user_id', $enrollment->client_user_id)
            ->whereKeyNot($enrollment->id)
            ->whereIn('status', [CoachingEnrollmentStatus::Active, CoachingEnrollmentStatus::Paused])
            ->exists();

        if ($clientAlreadyHasCoach) {
            return;
        }

        $enrollment->update([
            'status' => CoachingEnrollmentStatus::Active,
            'ends_at' => null,
        ]);
    }

    private function updateCoachingEnrollmentStatus(string $enrollmentId, CoachingEnrollmentStatus $status): void
    {
        Gate::authorize('viewDashboard');

        $enrollment = $this->selectedCoachingEnrollment($enrollmentId);

        if ($enrollment === null || $enrollment->status === CoachingEnrollmentStatus::Ended) {
            return;
        }

        $enrollment->update([
            'status' => $status,
            'ends_at' => null,
        ]);
    }

    private function selectedCoachingEnrollment(string $enrollmentId): ?CoachingEnrollment
    {
        if ($this->selectedUserId === null) {
            return null;
        }

        return CoachingEnrollment::query()
            ->whereKey($enrollmentId)
            ->where(function (Builder $query): void {
                $query
                    ->where('client_user_id', $this->selectedUserId)
                    ->orWhere('coach_user_id', $this->selectedUserId);
            })
            ->first();
    }

    public function openProgressionAnalysis(): void
    {
        if ($this->selectedUser === null) {
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
        $user = $this->selectedUser;

        if ($user === null) {
            return;
        }

        try {
            $this->planPromptPreview->show(__('Next plan prompt'), $generator->preview($user));
        } catch (RuntimeException $exception) {
            $this->planPromptPreview->showError(__('Next plan prompt'), $exception->getMessage());
        }
    }

    public function openNutritionPlanPrompt(NutritionPlanAIGenerator $generator): void
    {
        $user = $this->selectedUser;

        if ($user === null) {
            return;
        }

        try {
            $this->planPromptPreview->show(__('Nutrition plan prompt'), $generator->preview($user));
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
        $user = $this->selectedUser;

        if ($user === null) {
            return null;
        }

        $analyzer = new WorkoutProgressionAnalyzer(
            new TrainingHistory($user),
            $this->exerciseCatalog,
        );

        return $analyzer->analyze();
    }

    public function createDefaultLocation(): void
    {
        if ($this->selectedUser) {
            $this->selectedUser->trainingLocations()->save(TrainingLocation::makeDefault());
            unset($this->selectedUser);
        }
    }

    public function openTrainingLocationModal(): void
    {
        $this->resetValidation();
        $this->editingLocationId = null;
        $this->locationName = '';
        $this->locationIsDefault = false;
        $this->locationLatitude = '';
        $this->locationLongitude = '';
        $this->locationSortOrder = 0;
        $this->selectedEquipment = [];
        $this->showTrainingLocationModal = true;
    }

    public function editTrainingLocation(string $locationId): void
    {
        $user = $this->selectedUser;
        if (! $user) {
            return;
        }

        $location = $user->trainingLocations()->whereKey($locationId)->first();
        if (! $location) {
            return;
        }

        $this->resetValidation();
        $this->editingLocationId = $location->id;
        $this->locationName = $location->name;
        $this->locationIsDefault = (bool) $location->is_default;
        $this->locationLatitude = $location->latitude === null ? '' : (string) $location->latitude;
        $this->locationLongitude = $location->longitude === null ? '' : (string) $location->longitude;
        $this->locationSortOrder = (int) $location->sort_order;
        $this->selectedEquipment = collect($location->equipment ?? [])
            ->map(fn (mixed $item): string => $item instanceof Equipment ? $item->value : (string) $item)
            ->all();
        $this->showTrainingLocationModal = true;
    }

    public function toggleEquipment(string $equipmentValue): void
    {
        if (in_array($equipmentValue, $this->selectedEquipment, true)) {
            $this->selectedEquipment = array_values(array_filter(
                $this->selectedEquipment,
                fn (string $item): bool => $item !== $equipmentValue,
            ));
        } else {
            $this->selectedEquipment[] = $equipmentValue;
        }
    }

    public function saveTrainingLocation(): void
    {
        $user = $this->selectedUser;
        if (! $user) {
            return;
        }

        $this->validate([
            'locationName' => ['required', 'string', 'max:255'],
            'locationIsDefault' => ['boolean'],
            'locationLatitude' => ['nullable', 'numeric', 'between:-90,90'],
            'locationLongitude' => ['nullable', 'numeric', 'between:-180,180'],
            'locationSortOrder' => ['required', 'integer', 'min:0'],
            'selectedEquipment' => ['array'],
            'selectedEquipment.*' => [Rule::enum(Equipment::class)],
        ]);

        $equipmentEnums = collect($this->selectedEquipment)
            ->map(fn (string $val): ?Equipment => Equipment::tryFrom($val))
            ->filter()
            ->values()
            ->all();

        if ($this->locationIsDefault) {
            $user->trainingLocations()->update(['is_default' => false]);
        }

        if ($this->editingLocationId) {
            $location = $user->trainingLocations()->whereKey($this->editingLocationId)->first();
            if ($location) {
                $location->update([
                    'name' => $this->locationName,
                    'is_default' => $this->locationIsDefault,
                    'latitude' => $this->locationLatitude === '' ? null : $this->locationLatitude,
                    'longitude' => $this->locationLongitude === '' ? null : $this->locationLongitude,
                    'sort_order' => $this->locationSortOrder,
                    'equipment' => $equipmentEnums,
                ]);
            }
        } else {
            $user->trainingLocations()->create([
                'name' => $this->locationName,
                'is_default' => $this->locationIsDefault,
                'latitude' => $this->locationLatitude === '' ? null : $this->locationLatitude,
                'longitude' => $this->locationLongitude === '' ? null : $this->locationLongitude,
                'sort_order' => $this->locationSortOrder,
                'equipment' => $equipmentEnums,
            ]);
        }

        unset($this->selectedUser);
        $this->showTrainingLocationModal = false;
    }
};
?>

<div>
    <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-900">
        <div class="mb-6">
            <flux:heading size="lg" level="2">{{ __('Users and plans') }}</flux:heading>
            <flux:text
                variant="subtle">{{ __('A quick overview of each user’s training and nutrition data.') }}</flux:text>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('User') }}</flux:table.column>
                <flux:table.column>{{ __('Email') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Workout plans') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Custom workouts') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Nutrition plans') }}</flux:table.column>
                <flux:table.column>{{ __('Subscription') }}</flux:table.column>
                <flux:table.column>{{ __('Access') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->users as $user)
                    <flux:table.row :key="$user->id">
                        <flux:table.cell variant="strong">
                            <div class="flex items-center gap-3">
                                <x-user-avatar :user="$user"/>
                                <flux:button variant="ghost" size="sm" wire:click="selectUser('{{ $user->id }}')"
                                             class="-ml-2 font-semibold">
                                    {{ $user->name }}
                                </flux:button>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $user->email }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:badge>{{ $user->workout_plans_count }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:badge>{{ $user->custom_workouts_count }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:badge>{{ $user->nutrition_plans_count }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $user->subscription?->plan?->value ? str($user->subscription->plan->value)->replace('_', ' ')->title() : __('None') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($user->subscription?->isActive())
                                <flux:badge color="green">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge color="zinc">{{ __('Inactive') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-8 text-center">
                            {{ __('No users found.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    @if ($this->selectedUser)
        <flux:modal wire:model="showUserDetail" flyout position="right" :closable="false"
                    class="w-full max-w-none p-0 sm:w-[34rem]">
            <x-dashboard.user-detail-panel
                :user="$this->selectedUser"
                :active-tab="$selectedTab"
                :progression-analysis="$this->showProgressionAnalysis ? $this->progressionAnalysis : null"
                :show-progression-analysis="$showProgressionAnalysis"
                :coaches="$selectedTab === 'coaching' ? $this->coaches : collect()"
                :coaching="$selectedTab === 'coaching' ? $this->coaching : collect()"
                :can-add-coach="$selectedTab === 'coaching' && $this->canAddCoach"
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

    @if ($showCoachingEnrollmentForm)
        <flux:modal wire:model="showCoachingEnrollmentForm" class="w-full max-w-lg">
            <form wire:submit="saveCoachingEnrollment" class="space-y-6">
                <div>
                    <flux:heading
                        size="lg">{{ $coachingEnrollmentType === 'coach' ? __('Add coach') : __('Add client') }}</flux:heading>
                    <flux:text variant="subtle" class="mt-2">
                        {{ $coachingEnrollmentType === 'coach' ? __('Assign a coach to this user.') : __('Assign a client to this coach.') }}
                    </flux:text>
                </div>

                <flux:field>
                    <flux:label>{{ $coachingEnrollmentType === 'coach' ? __('Coach') : __('Client') }}</flux:label>
                    <flux:select wire:model="relatedUserId">
                        <option value="">{{ __('Select a user') }}</option>
                        @foreach ($this->availableUsers as $availableUser)
                            <option value="{{ $availableUser->id }}">{{ $availableUser->name }}
                                · {{ $availableUser->email }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="relatedUserId"/>
                </flux:field>

                <div class="flex justify-end gap-3">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary"
                                 type="submit">{{ $coachingEnrollmentType === 'coach' ? __('Add coach') : __('Add client') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif

    @if ($showTrainingLocationModal)
        <flux:modal wire:model="showTrainingLocationModal" class="w-full max-w-2xl">
            <form wire:submit="saveTrainingLocation" class="space-y-6">
                <div class="flex items-center justify-between border-b border-zinc-200 pb-4 dark:border-zinc-700">
                    <div>
                        <flux:heading size="lg">
                            {{ $editingLocationId ? __('Edit training location') : __('Add training location') }}
                        </flux:heading>
                        <flux:text variant="subtle">
                            {{ __('Modify details and available equipment for this location.') }}
                        </flux:text>
                    </div>
                    <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field class="sm:col-span-2">
                        <flux:label>{{ __('Name') }}</flux:label>
                        <flux:input wire:model="locationName" placeholder="{{ __('e.g., Home Gym, Commercial Gym') }}" />
                        <flux:error name="locationName" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Latitude') }}</flux:label>
                        <flux:input wire:model="locationLatitude" type="number" step="any" placeholder="41.3851" />
                        <flux:error name="locationLatitude" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Longitude') }}</flux:label>
                        <flux:input wire:model="locationLongitude" type="number" step="any" placeholder="2.1734" />
                        <flux:error name="locationLongitude" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Sort order') }}</flux:label>
                        <flux:input wire:model="locationSortOrder" type="number" min="0" />
                        <flux:error name="locationSortOrder" />
                    </flux:field>

                    <flux:field class="flex items-center pt-6">
                        <flux:checkbox wire:model="locationIsDefault" :label="__('Set as default location')" />
                        <flux:error name="locationIsDefault" />
                    </flux:field>
                </div>

                <div class="space-y-3 pt-2">
                    <flux:label class="font-medium">{{ __('Equipment') }}</flux:label>
                    <flux:error name="selectedEquipment" />

                    <div class="grid grid-cols-4 gap-2 max-h-72 overflow-y-auto p-1">
                        @foreach (\App\Enums\Equipment::cases() as $equipment)
                            @php
                                $isSelected = in_array($equipment->value, $selectedEquipment, true);
                            @endphp
                            <button
                                type="button"
                                wire:click="toggleEquipment('{{ $equipment->value }}')"
                                class="flex flex-col items-center justify-center p-3 rounded-lg border text-xs font-medium text-center transition cursor-pointer {{ $isSelected ? 'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' : 'border-zinc-200 bg-zinc-50 text-zinc-700 hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' }}"
                            >
                                <x-exercises.equipment-icon :equipment="$equipment->value" class="h-6 w-6 mb-1.5" />
                                <span class="truncate w-full">{{ str($equipment->value)->headline() }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>
