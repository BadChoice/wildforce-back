<?php

use App\Models\WorkoutPlan;
use Livewire\Component;
use Livewire\Attributes\On;

new class extends Component {
    public ?string $clientId = null;

    public bool $show = false;

    public string $workoutPlanName = '';

    public string $workoutPlanGoal = 'generalFitness';

    public string $workoutPlanNotes = '';

    #[On('open-create-workout-plan-modal')]
    public function open(?string $clientId = null): void
    {
        $this->clientId = $clientId;
        $this->resetValidation();
        $this->workoutPlanName = '';
        $this->workoutPlanGoal = 'generalFitness';
        $this->workoutPlanNotes = '';
        $this->show = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'workoutPlanName' => ['required', 'string', 'max:255'],
            'workoutPlanGoal' => ['required', 'string', 'max:255'],
            'workoutPlanNotes' => ['nullable', 'string'],
        ]);

        $userId = $this->clientId ?? auth()->id();

        if (!$userId) {
            $this->addError('clientId', __('A user ID is required to create a plan.'));
            return;
        }

        $workoutPlan = new WorkoutPlan;
        $workoutPlan->forceFill([
            'user_id' => $userId,
            'name' => $validated['workoutPlanName'],
            'goal' => $validated['workoutPlanGoal'],
            'notes' => $validated['workoutPlanNotes'] ?: null,
            'status' => 'draft',
        ]);
        $workoutPlan->save();

        $this->show = false;

        $this->dispatch('workout-plan-created', workoutPlanId: $workoutPlan->id);

        $this->redirect(route('workout-plans.show', $workoutPlan));
    }
};
?>

<flux:modal wire:model="show" class="w-full max-w-lg">
    <form wire:submit="save" class="space-y-5">
        <div class="mb-6">
            <flux:heading size="lg">{{ __('New workout plan') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Create a draft plan.') }}</flux:text>
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
