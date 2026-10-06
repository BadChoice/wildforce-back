@props(['plans', 'today'])

<section aria-labelledby="nutrition-plans-heading" class="space-y-4">
    <div>
        <flux:heading id="nutrition-plans-heading" size="sm">{{ __('Nutrition plans') }}</flux:heading>
    </div>

    <flux:table>
        @forelse ($plans as $plan)
            @php($status = $plan->statusOn($today))
            <flux:table.row :key="$plan->id" class="text-xs">
                <flux:table.cell>
                    <a href="{{ route('nutrition-plans.show', $plan) }}" class="font-medium text-zinc-900 hover:underline dark:text-white">
                        {{ $plan->startDate()->translatedFormat('j M') }} – {{ $plan->endDate()->translatedFormat('j M Y') }}
                    </a>
                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ str($plan->goal)->headline() }}@if ($plan->body_composition_phase) · {{ str($plan->body_composition_phase)->headline() }}@endif
                        · {{ __(':calories kcal/day', ['calories' => number_format((float) $plan->daily_calorie_average)]) }}
                    </div>
                    @if ($plan->sourceWorkoutPlan)
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Based on :plan', ['plan' => $plan->sourceWorkoutPlan->name]) }}</div>
                    @endif
                </flux:table.cell>
                <flux:table.cell>
                    <flux:badge size="sm" :color="$status->color()">{{ $status->label() }}</flux:badge>
                </flux:table.cell>
                <flux:table.cell>
                    <a href="{{ route('nutrition-plans.show', $plan) }}" aria-label="{{ __('Open nutrition plan') }}">
                        <flux:icon.ellipsis-horizontal />
                    </a>
                </flux:table.cell>
            </flux:table.row>
        @empty
            <flux:table.row>
                <flux:table.cell>
                    {{ __('This user does not have any nutrition plans yet.') }}
                </flux:table.cell>
            </flux:table.row>
        @endforelse
    </flux:table>
</section>
