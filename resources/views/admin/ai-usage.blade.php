<x-layouts::app :title="__('AI usage')">
    <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 rounded-xl">
        <div class="flex flex-col gap-2">
            <flux:heading size="xl">{{ __('AI usage') }}</flux:heading>
            <flux:text>{{ __('Current-month AI usage, ordered by cost.') }}</flux:text>
        </div>

        <flux:card class="flex items-center justify-between gap-4">
            <flux:text>{{ __('Total this month') }}</flux:text>
            <flux:heading size="lg">${{ number_format($totalCostInMicros / 1_000_000, 4) }}</flux:heading>
        </flux:card>

        @if ($unpricedRequests > 0)
            <flux:callout variant="warning" icon="exclamation-triangle">
                {{ trans_choice(':count request has no cost because its model has no price.|:count requests have no cost because their models have no price.', $unpricedRequests, ['count' => $unpricedRequests]) }}
            </flux:callout>
        @endif

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('User') }}</flux:table.column>
                <flux:table.column>{{ __('Requests') }}</flux:table.column>
                <flux:table.column>{{ __('Tokens') }}</flux:table.column>
                <flux:table.column>{{ __('Cost') }}</flux:table.column>
                <flux:table.column>{{ __('Budget') }}</flux:table.column>
                <flux:table.column>{{ __('Blocked') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($totals as $total)
                    @php($user = $users->get($total->user_id))

                    <flux:table.row :key="$total->user_id">
                        <flux:table.cell variant="strong">{{ $user?->email ?? $total->user_id }}</flux:table.cell>
                        <flux:table.cell>{{ number_format((int) $total->requests) }}</flux:table.cell>
                        <flux:table.cell>{{ number_format((int) $total->tokens) }}</flux:table.cell>
                        <flux:table.cell>${{ number_format((int) $total->cost_micros / 1_000_000, 4) }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $user === null ? '—' : '$'.number_format($user->monthlyAiBudgetInMicros() / 1_000_000, 4) }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($user?->isAiBlocked())
                                <flux:badge color="red">{{ __('Yes') }}</flux:badge>
                            @else
                                <span class="text-zinc-500 dark:text-zinc-400">—</span>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-8 text-center text-sm text-zinc-500">
                            {{ __('No AI usage has been recorded this month.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</x-layouts::app>
