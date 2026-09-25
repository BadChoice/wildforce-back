<?php

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function users(): Collection
    {
        return User::query()
            ->select(['id', 'name', 'email'])
            ->withCount([
                'workoutPlans',
                'workoutDays as custom_workouts_count' => fn (Builder $query): Builder => $query->whereNull('workout_plan_id'),
                'nutritionPlans',
            ])
            ->orderBy('name')
            ->get();
    }
};
?>

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
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->users as $user)
                <flux:table.row :key="$user->id">
                    <flux:table.cell variant="strong">{{ $user->name }}</flux:table.cell>
                    <flux:table.cell>{{ $user->email }}</flux:table.cell>
                    <flux:table.cell align="end"><flux:badge>{{ $user->workout_plans_count }}</flux:badge></flux:table.cell>
                    <flux:table.cell align="end"><flux:badge>{{ $user->custom_workouts_count }}</flux:badge></flux:table.cell>
                    <flux:table.cell align="end"><flux:badge>{{ $user->nutrition_plans_count }}</flux:badge></flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="py-8 text-center">
                        {{ __('No users found.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
