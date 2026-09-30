@props(['type', 'entries'])

@php
    $points = $entries->sortBy('recorded_at')->values();
    $values = $points->map(fn ($entry): float => (float) $entry->value);
    $minimum = $values->min();
    $maximum = $values->max();
    $range = max($maximum - $minimum, 1);
    $chartWidth = 320;
    $chartHeight = 160;
    $padding = 16;
    $plotWidth = $chartWidth - ($padding * 2);
    $plotHeight = $chartHeight - ($padding * 2);
    $lastIndex = max($points->count() - 1, 1);
    $coordinates = $points->map(function ($entry, int $index) use ($minimum, $range, $padding, $plotHeight, $plotWidth, $lastIndex): array {
        return [
            'x' => $padding + (($plotWidth / $lastIndex) * $index),
            'y' => $padding + ($plotHeight * (1 - (((float) $entry->value - $minimum) / $range))),
        ];
    });
    $linePoints = $coordinates->map(fn (array $coordinate): string => $coordinate['x'].','.$coordinate['y'])->implode(' ');
    $latestEntry = $points->last();
@endphp

<article {{ $attributes->class('rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800') }}>
    <div class="flex items-start justify-between gap-3">
        <div>
            <flux:heading size="sm">{{ str($type)->headline() }}</flux:heading>
            <flux:text variant="subtle">{{ __('Latest: :value', ['value' => $latestEntry->value]) }}</flux:text>
        </div>
        <flux:text variant="subtle" class="shrink-0 text-xs">{{ $latestEntry->recorded_at->format('d/m/Y') }}</flux:text>
    </div>

    <svg viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" role="img" aria-label="{{ __(':metric over time', ['metric' => str($type)->headline()]) }}" class="mt-4 h-40 w-full overflow-visible">
        <line x1="{{ $padding }}" x2="{{ $chartWidth - $padding }}" y1="{{ $padding }}" y2="{{ $padding }}" class="stroke-zinc-200 dark:stroke-zinc-700" />
        <line x1="{{ $padding }}" x2="{{ $chartWidth - $padding }}" y1="{{ $chartHeight / 2 }}" y2="{{ $chartHeight / 2 }}" class="stroke-zinc-200 dark:stroke-zinc-700" />
        <line x1="{{ $padding }}" x2="{{ $chartWidth - $padding }}" y1="{{ $chartHeight - $padding }}" y2="{{ $chartHeight - $padding }}" class="stroke-zinc-200 dark:stroke-zinc-700" />
        @if ($points->count() > 1)
            <polyline points="{{ $linePoints }}" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="3" class="text-zinc-950 dark:text-white" />
        @endif
        @foreach ($coordinates as $coordinate)
            <circle cx="{{ $coordinate['x'] }}" cy="{{ $coordinate['y'] }}" r="4" class="fill-zinc-950 dark:fill-white" />
        @endforeach
    </svg>

    <div class="mt-2 flex justify-between text-xs text-zinc-500 dark:text-zinc-400">
        <span>{{ $points->first()->recorded_at->format('d/m/Y') }}</span>
        <span>{{ __('Range: :minimum–:maximum', ['minimum' => $minimum, 'maximum' => $maximum]) }}</span>
        <span>{{ $latestEntry->recorded_at->format('d/m/Y') }}</span>
    </div>
</article>
