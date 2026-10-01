<?php

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
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?string $selectedUserId = null;

    public string $selectedTab = 'training';

    public bool $showUserDetail = false;

    public bool $showProgressionAnalysis = false;

    public PlanPromptPreview $planPromptPreview;

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
                'workoutDays as custom_workouts_count' => fn (Builder $query): Builder => $query->customWorkouts(),
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
                'exerciseProfiles' => fn (HasMany $query): HasMany => $query->orderBy('exercise'),
                'workoutPlans' => fn (HasMany $query): HasMany => $query
                    ->orderByDesc('updated_at')
                    ->with([
                        'workoutDays' => fn (HasMany $query): HasMany => $query->orderBy('order_index'),
                    ]),
                'bodyMetrics' => fn (HasMany $query): HasMany => $query
                    ->select(['id', 'user_id', 'type', 'value', 'recorded_at'])
                    ->orderBy('recorded_at'),
                'subscription',
            ])
            ->find($this->selectedUserId);
    }

    public function selectUser(string $userId): void
    {
        if (! User::query()->whereKey($userId)->exists()) {
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
        if (! in_array($tab, ['training', 'exercise-profiles', 'nutrition', 'body-metrics', 'subscription'], true)) {
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

        return new WorkoutProgressionAnalyzer(
            new TrainingHistory($user),
            $this->exerciseCatalog,
        )->analyze();
    }
};
?>

<div>
    <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-900">
        <div class="mb-6">
            <flux:heading size="lg" level="2">{{ __('Users and plans') }}</flux:heading>
            <flux:text variant="subtle">{{ __('A quick overview of each user’s training and nutrition data.') }}</flux:text>
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
                                    <x-user-avatar :user="$user" />
                                    <flux:button variant="ghost" size="sm" wire:click="selectUser('{{ $user->id }}')" class="-ml-2 font-semibold">
                                        {{ $user->name }}
                                    </flux:button>
                                </div>
                            </flux:table.cell>
                        <flux:table.cell>{{ $user->email }}</flux:table.cell>
                        <flux:table.cell align="end"><flux:badge>{{ $user->workout_plans_count }}</flux:badge></flux:table.cell>
                        <flux:table.cell align="end"><flux:badge>{{ $user->custom_workouts_count }}</flux:badge></flux:table.cell>
                        <flux:table.cell align="end"><flux:badge>{{ $user->nutrition_plans_count }}</flux:badge></flux:table.cell>
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
        <flux:modal wire:model="showUserDetail" flyout position="right" :closable="false" class="w-full max-w-none p-0 sm:w-[34rem]">
            <x-dashboard.user-detail-panel
                :user="$this->selectedUser"
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
</div>
