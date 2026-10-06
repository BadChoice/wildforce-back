<?php

use App\Services\Nutrition\NutritionAdherenceCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function plans(): Collection
    {
        return auth()->user()->nutritionPlans()
            ->withCount('days')
            ->orderByDesc('starts_on')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();
    }

    #[Computed]
    public function today(): CarbonImmutable
    {
        return app(NutritionAdherenceCalculator::class)->today(auth()->user());
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
    <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-900">
        <div class="mb-6">
            <flux:heading size="lg" level="1">{{ __('Nutrition plans') }}</flux:heading>
            <flux:text variant="subtle">{{ __('The list of nutrition plans.') }}</flux:text>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Period') }}</flux:table.column>
                <flux:table.column>{{ __('Goal') }}</flux:table.column>
                <flux:table.column>{{ __('Daily average') }}</flux:table.column>
                <flux:table.column>{{ __('Days') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            @forelse ($this->plans as $plan)
                @php($status = $plan->statusOn($this->today))
                <flux:table.row :key="$plan->id">
                    <flux:table.cell>
                        <a href="{{ route('nutrition-plans.show', $plan) }}" class="font-bold text-black dark:text-white">
                            {{ $plan->startDate()->format('d/m/Y') }} – {{ $plan->endDate()->format('d/m/Y') }}
                        </a>
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ str($plan->goal)->headline() }}@if ($plan->body_composition_phase) · {{ str($plan->body_composition_phase)->headline() }}@endif
                    </flux:table.cell>
                    <flux:table.cell>{{ __(':calories kcal', ['calories' => number_format((float) $plan->daily_calorie_average)]) }}</flux:table.cell>
                    <flux:table.cell>{{ trans_choice(':count day|:count days', $plan->days_count, ['count' => $plan->days_count]) }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$status->color()">{{ $status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <a href="{{ route('nutrition-plans.show', $plan) }}" aria-label="{{ __('Open nutrition plan') }}">
                            <flux:icon.ellipsis-horizontal variant="mini" />
                        </a>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="py-8 text-center text-sm text-zinc-500">{{ __('No nutrition plans found.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table>
    </div>
</div>
