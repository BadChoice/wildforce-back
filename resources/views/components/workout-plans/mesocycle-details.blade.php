@props(['mesocycleNumber' => null, 'phase' => null, 'phaseWeek' => null, 'cycleLength' => null])

<section aria-label="{{ __('Mesocycle') }}" {{ $attributes->class('rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800/60') }}>
    <flux:heading size="sm">{{ __('Mesocycle') }}</flux:heading>

    <dl class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div>
            <dt class="text-xs font-medium tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Number') }}</dt>
            <dd class="mt-1 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $mesocycleNumber ?? __('Not set') }}</dd>
        </div>
        <div>
            <dt class="text-xs font-medium tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Phase') }}</dt>
            <dd class="mt-1 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $phase ? str($phase)->headline() : __('Not set') }}</dd>
        </div>
        <div>
            <dt class="text-xs font-medium tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Phase week') }}</dt>
            <dd class="mt-1 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $phaseWeek ?? __('Not set') }}</dd>
        </div>
        <div>
            <dt class="text-xs font-medium tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Cycle length') }}</dt>
            <dd class="mt-1 text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $cycleLength ? trans_choice(':count week|:count weeks', $cycleLength, ['count' => $cycleLength]) : __('Not set') }}</dd>
        </div>
    </dl>
</section>
