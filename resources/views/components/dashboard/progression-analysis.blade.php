@props(['analysis', 'userId'])

@php
    $readiness = match ($analysis->readinessLevel) {
        'high' => ['label' => __('Ready to progress'), 'color' => 'green'],
        'low' => ['label' => __('Reduce the load'), 'color' => 'amber'],
        default => ['label' => __('Moderate readiness'), 'color' => 'indigo'],
    };
    $completionPercentage = (int) round($analysis->completionRate * 100);
    $volume = match ($analysis->overallVolumeTrend) {
        'increasing' => ['label' => __('Increasing'), 'color' => 'green', 'icon' => 'arrow-trending-up'],
        'decreasing' => ['label' => __('Decreasing'), 'color' => 'amber', 'icon' => 'arrow-trending-down'],
        'stable' => ['label' => __('Stable'), 'color' => 'zinc', 'icon' => 'equals'],
        default => ['label' => __('Not enough data'), 'color' => 'zinc', 'icon' => 'minus'],
    };
    $trendIndicators = [
        'improving' => ['color' => 'green', 'icon' => 'arrow-trending-up', 'class' => 'text-green-600 dark:text-green-400'],
        'plateau' => ['color' => 'amber', 'icon' => 'equals', 'class' => 'text-amber-600 dark:text-amber-400'],
        'regressing' => ['color' => 'red', 'icon' => 'arrow-trending-down', 'class' => 'text-red-600 dark:text-red-400'],
        'insufficient' => ['color' => 'zinc', 'icon' => 'minus', 'class' => 'text-zinc-500 dark:text-zinc-400'],
    ];
    $phaseIndicators = [
        'accumulation' => ['icon' => 'circle-stack', 'class' => 'text-indigo-600 dark:text-indigo-400'],
        'intensification' => ['icon' => 'arrow-trending-up', 'class' => 'text-emerald-600 dark:text-emerald-400'],
        'deload' => ['icon' => 'arrow-trending-down', 'class' => 'text-amber-600 dark:text-amber-400'],
    ];
@endphp

<section aria-labelledby="progression-analysis-heading" class="space-y-5 text-sm">
    <div>
        <flux:heading id="progression-analysis-heading" size="sm" class="text-base">{{ __('Progression analysis') }}</flux:heading>
        <flux:text variant="subtle" class="text-sm">{{ __('A concise summary to guide the next workout plan.') }}</flux:text>
    </div>

    <flux:callout :color="$readiness['color']" icon="sparkles" :heading="$readiness['label']" class="p-3 [&_[data-slot=text]]:text-sm">
        {{ $analysis->readinessLevel === 'medium' ? __('The client can progress, but recent adherence and feedback should be monitored.') : __('Based on the client’s recent adherence, training feedback, and current streak.') }}
    </flux:callout>

    <div class="grid grid-cols-2 gap-2">
        <flux:card class="space-y-1 p-3">
            <flux:text variant="subtle" class="text-xs">{{ __('Readiness') }}</flux:text>
            <div class="flex flex-wrap items-center gap-1.5"><span class="text-base font-semibold">{{ str($analysis->readinessLevel)->headline() }}</span><flux:badge size="sm" :color="$readiness['color']">{{ $readiness['label'] }}</flux:badge></div>
        </flux:card>
        <flux:card class="space-y-2 p-3">
            <div class="flex items-center justify-between gap-2"><flux:text variant="subtle" class="text-xs">{{ __('Completion') }}</flux:text><span class="font-semibold">{{ $completionPercentage }}%</span></div>
            <flux:progress :value="$completionPercentage" :color="$completionPercentage >= 80 ? 'green' : ($completionPercentage >= 50 ? 'amber' : 'red')" />
        </flux:card>
        <flux:card class="space-y-1 p-3">
            <flux:text variant="subtle" class="text-xs">{{ __('Completed workouts') }}</flux:text>
            <span class="text-base font-semibold">{{ $analysis->recentCompletedWorkouts }}</span>
        </flux:card>
        <flux:card class="space-y-1 p-3">
            <flux:text variant="subtle" class="text-xs">{{ __('Next mesocycle') }}</flux:text>
            <span class="text-base font-semibold">{{ $analysis->mesocycleNumber }}</span>
        </flux:card>
    </div>

    <div class="space-y-3">
        <div>
            <flux:heading size="sm" class="text-base">{{ __('Mesocycle timeline') }}</flux:heading>
            <flux:text variant="subtle" class="text-sm">{{ __('Recent training blocks and the next proposed mesocycle.') }}</flux:text>
        </div>
        <div class="flex gap-2 overflow-x-auto pb-1">
            @forelse ($analysis->recentMesocycles as $mesocycle)
                @php($phase = $phaseIndicators[$mesocycle['phase']] ?? ['icon' => 'chart-bar', 'class' => 'text-zinc-500 dark:text-zinc-400'])
                <div class="w-36 shrink-0 rounded-lg border border-zinc-200 p-2.5 dark:border-zinc-700">
                    <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Mesocycle :number', ['number' => $mesocycle['number']]) }}</span>
                    <div class="mt-1 flex items-center gap-1.5 text-sm font-medium"><flux:icon :name="$phase['icon']" class="size-4 {{ $phase['class'] }}" />{{ $mesocycle['phase'] ? str($mesocycle['phase'])->headline() : __('Training block') }}</div>
                    <flux:badge size="sm" class="mt-2" :color="$mesocycle['completionRate'] >= 0.8 ? 'green' : 'amber'">{{ __(':rate% complete', ['rate' => (int) round($mesocycle['completionRate'] * 100)]) }}</flux:badge>
                </div>
            @empty
                <div class="rounded-lg border border-dashed border-zinc-300 p-3 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                    {{ __('No completed training blocks yet.') }}
                </div>
            @endforelse
            <div class="w-36 shrink-0 rounded-lg border border-indigo-300 bg-indigo-50 p-2.5 dark:border-indigo-700 dark:bg-indigo-950/30">
                <span class="text-xs text-indigo-700 dark:text-indigo-300">{{ __('Next') }}</span>
                <div class="mt-1 text-sm font-medium text-indigo-950 dark:text-indigo-100">{{ __('Mesocycle :number', ['number' => $analysis->mesocycleNumber]) }}</div>
                <flux:badge size="sm" color="indigo" class="mt-2">{{ __('To plan') }}</flux:badge>
            </div>
        </div>
    </div>

    <div class="space-y-3">
        <div>
            <flux:heading size="sm" class="text-base">{{ __('What to change next') }}</flux:heading>
            <flux:text variant="subtle" class="text-sm">{{ __('The highest-impact adjustments suggested by the recent data.') }}</flux:text>
        </div>
        <div class="space-y-2">
            @foreach ($analysis->recommendations as $recommendation)
                <flux:card class="flex items-start gap-3 p-3">
                    <flux:badge :color="$recommendation['tone']" rounded>{{ $loop->iteration }}</flux:badge>
                    <div class="min-w-0"><div class="text-sm font-medium">{{ __($recommendation['title']) }}</div><flux:text variant="subtle" class="text-sm">{{ __($recommendation['description']) }}</flux:text></div>
                </flux:card>
            @endforeach
        </div>
    </div>

    <div class="space-y-3">
        <div>
            <flux:heading size="sm" class="text-base">{{ __('Supporting evidence') }}</flux:heading>
            <flux:text variant="subtle" class="text-sm">{{ __('The training signals behind the recommendation.') }}</flux:text>
        </div>

        <details class="group rounded-xl border border-zinc-200 dark:border-zinc-700">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 p-3 marker:hidden"><div><div class="text-sm font-medium">{{ __('Training volume trend') }}</div><flux:text variant="subtle" class="text-sm">{{ __('Volume across recent non-deload plans.') }}</flux:text></div><flux:badge size="sm" :color="$volume['color']" :icon="$volume['icon']">{{ $volume['label'] }}</flux:badge></summary>
        </details>

        <details class="group rounded-xl border border-zinc-200 dark:border-zinc-700">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 p-3 marker:hidden"><div><div class="text-sm font-medium">{{ __('Exercise trends') }}</div><flux:text variant="subtle" class="text-sm">{{ __('Progression status across tracked exercises.') }}</flux:text></div><flux:icon name="chevron-down" class="size-4 transition-transform group-open:rotate-180" /></summary>
            <div class="space-y-2 border-t border-zinc-200 p-3 dark:border-zinc-700">
                @forelse ($analysis->exerciseTrends as $exercise => $trend)
                    @php($indicator = $trendIndicators[$trend] ?? $trendIndicators['insufficient'])
                    <div class="flex items-center justify-between gap-3"><span>{{ str($exercise)->headline() }}</span><div class="flex items-center gap-1.5"><flux:icon :name="$indicator['icon']" class="size-4 {{ $indicator['class'] }}" /><flux:badge size="sm" :color="$indicator['color']">{{ str($trend)->headline() }}</flux:badge></div></div>
                @empty
                    <flux:text variant="subtle">{{ __('No exercise history is available yet.') }}</flux:text>
                @endforelse
            </div>
        </details>

        <details class="group rounded-xl border border-zinc-200 dark:border-zinc-700">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 p-3 marker:hidden"><div><div class="text-sm font-medium">{{ __('Muscle balance') }}</div><flux:text variant="subtle" class="text-sm">{{ __('Muscle groups that may need more or less attention.') }}</flux:text></div><flux:icon name="chevron-down" class="size-4 transition-transform group-open:rotate-180" /></summary>
            <div class="grid gap-4 border-t border-zinc-200 p-3 sm:grid-cols-2 dark:border-zinc-700"><div><div class="text-sm font-medium">{{ __('Neglected') }}</div><div class="mt-2 flex flex-wrap gap-2">@forelse ($analysis->neglectedMuscleGroups as $group)<flux:badge size="sm" color="amber">{{ str($group)->headline() }}</flux:badge>@empty<flux:text variant="subtle" class="text-sm">{{ __('None detected') }}</flux:text>@endforelse</div></div><div><div class="text-sm font-medium">{{ __('Overworked') }}</div><div class="mt-2 flex flex-wrap gap-2">@forelse ($analysis->overworkedMuscleGroups as $group)<flux:badge size="sm" color="red">{{ str($group)->headline() }}</flux:badge>@empty<flux:text variant="subtle" class="text-sm">{{ __('None detected') }}</flux:text>@endforelse</div></div></div>
        </details>

        <details class="group rounded-xl border border-zinc-200 dark:border-zinc-700">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 p-3 marker:hidden"><div><div class="text-sm font-medium">{{ __('Watch-outs') }}</div><flux:text variant="subtle" class="text-sm">{{ __('Stale exercises and recent effort feedback.') }}</flux:text></div><flux:icon name="chevron-down" class="size-4 transition-transform group-open:rotate-180" /></summary>
            <div class="space-y-4 border-t border-zinc-200 p-3 dark:border-zinc-700"><div><div class="text-sm font-medium">{{ __('Stale exercises') }}</div><div class="mt-2 flex flex-wrap gap-2">@forelse ($analysis->staleExercises as $exercise)<flux:badge size="sm" color="amber">{{ str($exercise)->headline() }}</flux:badge>@empty<flux:text variant="subtle" class="text-sm">{{ __('None detected') }}</flux:text>@endforelse</div></div><div><div class="text-sm font-medium">{{ __('Recent feedback') }}</div><div class="mt-2 flex flex-wrap gap-2">@forelse ($analysis->recentFeedbackBreakdown as $feedback => $count)<flux:badge size="sm">{{ str($feedback)->headline() }} · {{ $count }}</flux:badge>@empty<flux:text variant="subtle" class="text-sm">{{ __('No feedback has been recorded yet.') }}</flux:text>@endforelse</div></div></div>
        </details>
    </div>

    <div class="flex justify-end gap-3 border-t border-zinc-200 pt-5 dark:border-zinc-700">
        <flux:button variant="ghost" wire:click="closeProgressionAnalysis">{{ __('Back to training') }}</flux:button>
        <flux:button variant="primary" icon="plus" wire:click="$dispatch('open-create-workout-plan-modal', { clientId: '{{ $userId }}' })">{{ __('Create workout plan') }}</flux:button>
    </div>
</section>
