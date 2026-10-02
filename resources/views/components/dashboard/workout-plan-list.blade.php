@props(['workoutPlans', 'editable' => false])

<section aria-labelledby="workout-plans-heading" class="space-y-4">
    <div>
        <flux:heading id="workout-plans-heading" size="sm">{{ __('Workout plans') }}</flux:heading>
    </div>

    <div class="space-y-3">
        <flux:table>
        @forelse ($workoutPlans as $workoutPlan)
            <flux:table.row class="text-xs">
                <flux:table.cell>
                    <div class="flex flex-col text-black">
                        {{$workoutPlan->name}}
                    </div>
                    <div>
                    <span class="text-xs text-neutral-500">{{ trans_choice(':count workout day|:count workout days', $workoutPlan->workoutDays->count(), ['count' => $workoutPlan->workoutDays->count()]) }}</span>
                    </div>
                </flux:table.cell>
                <flux:table.cell>
                    <flux:badge size="sm">{{ str($workoutPlan->status)->headline() }}</flux:badge>
                </flux:table.cell>
                <flux:table.cell>
                    <a href="{{route('workout-plans.show', $workoutPlan)}}">
                        <flux:icon.ellipsis-horizontal />
                    </a>
                </flux:table.cell>
            </flux:table.row>
        @empty
            <flux:table.row>
                <flux:table.cell>
                    {{ __('This user does not have any workout plans yet.') }}
                </flux:table.cell>
            </flux:table.row>
        @endforelse
        </flux:table>

    </div>
</section>
