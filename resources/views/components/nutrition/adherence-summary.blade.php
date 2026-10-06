@props(['summary'])

@php
    $calorieDifference = $summary->calorieDifferencePercentage();
    $proteinPercentage = $summary->proteinPercentage();
    $daysSinceLastLog = $summary->daysSinceLastLog();
    $loggedDays = $summary->loggedDays();
    $periodDays = $summary->periodDays();
    $loggedPercentage = $periodDays > 0 ? (int) round(($loggedDays / $periodDays) * 100) : 0;
    $calorieTolerance = (int) round(\App\Services\Nutrition\NutritionAdherenceCalculator::CalorieTolerance * 100);
    $minimumProtein = (int) round(\App\Services\Nutrition\NutritionAdherenceCalculator::MinimumProteinRatio * 100);
    $calorieClass = match (true) {
        $calorieDifference === null => 'text-zinc-500 dark:text-zinc-400',
        abs($calorieDifference) <= $calorieTolerance => 'text-green-600 dark:text-green-400',
        $calorieDifference > 0 => 'text-red-600 dark:text-red-400',
        default => 'text-amber-600 dark:text-amber-400',
    };
    $proteinClass = match (true) {
        $proteinPercentage === null => 'text-zinc-500 dark:text-zinc-400',
        $proteinPercentage >= $minimumProtein => 'text-green-600 dark:text-green-400',
        default => 'text-amber-600 dark:text-amber-400',
    };
    $lastLoggedLabel = match (true) {
        $daysSinceLastLog === null => __('Never'),
        $daysSinceLastLog === 0 => __('Today'),
        $daysSinceLastLog === 1 => __('Yesterday'),
        default => trans_choice(':count day ago|:count days ago', $daysSinceLastLog, ['count' => $daysSinceLastLog]),
    };
@endphp

<section aria-labelledby="nutrition-status-heading" class="space-y-3">
    <div>
        <flux:heading id="nutrition-status-heading" size="sm">{{ __('Nutrition status') }}</flux:heading>
        <flux:text variant="subtle">{{ trans_choice('Logged food compared with the plan targets over the last :count complete day.|Logged food compared with the plan targets over the last :count complete days.', $periodDays, ['count' => $periodDays]) }}</flux:text>
    </div>

    @if ($summary->activePlan === null)
        @if ($summary->latestPlan)
            @php($daysSincePlanEnded = (int) $summary->latestPlan->endDate()->diffInDays($summary->today))
            <flux:callout color="amber" icon="exclamation-triangle" class="p-3 [&_[data-slot=text]]:text-sm">
                {{ trans_choice('The last nutrition plan ended :count day ago.|The last nutrition plan ended :count days ago.', $daysSincePlanEnded, ['count' => $daysSincePlanEnded]) }}
            </flux:callout>
        @else
            <flux:callout color="zinc" icon="information-circle" class="p-3 [&_[data-slot=text]]:text-sm">
                {{ __('This user does not have a nutrition plan yet.') }}
            </flux:callout>
        @endif
    @endif

    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
        <flux:card class="space-y-2 p-3">
            <div class="flex items-center justify-between gap-2">
                <flux:text variant="subtle" class="text-xs">{{ __('Days logged') }}</flux:text>
                <span class="text-sm font-semibold">{{ $loggedDays }}/{{ $periodDays }}</span>
            </div>
            <flux:progress :value="$loggedPercentage" :color="$loggedPercentage >= 70 ? 'green' : ($loggedPercentage >= 40 ? 'amber' : 'red')" />
        </flux:card>
        <flux:card class="space-y-1 p-3">
            <flux:text variant="subtle" class="text-xs">{{ __('Calories vs target') }}</flux:text>
            <span class="block text-base font-semibold {{ $calorieClass }}">
                {{ $calorieDifference === null ? '—' : ($calorieDifference > 0 ? '+' : '').$calorieDifference.'%' }}
            </span>
        </flux:card>
        <flux:card class="space-y-1 p-3">
            <flux:text variant="subtle" class="text-xs">{{ __('Protein reached') }}</flux:text>
            <span class="block text-base font-semibold {{ $proteinClass }}">
                {{ $proteinPercentage === null ? '—' : $proteinPercentage.'%' }}
            </span>
        </flux:card>
        <flux:card class="space-y-1 p-3">
            <flux:text variant="subtle" class="text-xs">{{ __('Last logged') }}</flux:text>
            <span @class([
                'block text-base font-semibold',
                'text-red-600 dark:text-red-400' => $daysSinceLastLog === null || $daysSinceLastLog >= 3,
            ])>{{ $lastLoggedLabel }}</span>
        </flux:card>
    </div>
</section>
