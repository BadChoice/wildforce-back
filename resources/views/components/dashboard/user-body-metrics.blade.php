@props(['user'])

@php
    $metricsByType = $user->bodyMetrics->groupBy('type');
@endphp

<section aria-labelledby="body-metrics-heading">
    <div class="mb-5">
        <flux:heading id="body-metrics-heading" size="sm">{{ __('Body metrics') }}</flux:heading>
        <flux:text variant="subtle">{{ __('Track each measurement over time.') }}</flux:text>
    </div>

    @if ($metricsByType->isEmpty())
        <x-dashboard.placeholder-tab :title="__('No body metrics yet')" :description="__('Measurements recorded by this user will appear here.')" />
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($metricsByType as $type => $entries)
                <x-dashboard.body-metric-chart :type="$type" :entries="$entries" wire:key="body-metric-{{ $user->id }}-{{ $type }}" />
            @endforeach
        </div>
    @endif
</section>
