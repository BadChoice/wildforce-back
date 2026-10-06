@props(['logging'])

@php
    $lastLoggedAt = $logging['lastLoggedAt'] ?? null;
    $daysSinceLastLog = $lastLoggedAt === null ? null : (int) $lastLoggedAt->startOfDay()->diffInDays(now($lastLoggedAt->getTimezone())->startOfDay());
    $loggedRatio = ($logging['periodDays'] ?? 0) > 0 ? $logging['loggedDays'] / $logging['periodDays'] : 0;
@endphp

@if ($lastLoggedAt === null)
    <flux:badge size="sm" color="zinc">{{ __('Never logged') }}</flux:badge>
@elseif ($daysSinceLastLog >= 3)
    <flux:badge size="sm" color="red" icon="exclamation-triangle">{{ trans_choice('No logs for :count day|No logs for :count days', $daysSinceLastLog, ['count' => $daysSinceLastLog]) }}</flux:badge>
@else
    <flux:badge size="sm" :color="$loggedRatio >= 0.7 ? 'green' : ($loggedRatio >= 0.4 ? 'amber' : 'red')">
        {{ __(':logged/:period days logged', ['logged' => $logging['loggedDays'], 'period' => $logging['periodDays']]) }}
    </flux:badge>
@endif
