@props(['days', 'selectedDate' => null])

@use('App\Enums\NutritionAdherenceStatus')
@use('App\Enums\NutritionDayType')

@php
    $cellClasses = fn (NutritionAdherenceStatus $status): string => match ($status) {
        NutritionAdherenceStatus::OnTarget => 'border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-300',
        NutritionAdherenceStatus::LowProtein, NutritionAdherenceStatus::Under => 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300',
        NutritionAdherenceStatus::Over => 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300',
        NutritionAdherenceStatus::InProgress => 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-300',
        default => 'border-zinc-200 bg-zinc-50 text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-400',
    };
    $legend = [
        NutritionAdherenceStatus::OnTarget,
        NutritionAdherenceStatus::LowProtein,
        NutritionAdherenceStatus::Under,
        NutritionAdherenceStatus::Over,
        NutritionAdherenceStatus::NotLogged,
        NutritionAdherenceStatus::NoPlan,
    ];
@endphp

<section aria-labelledby="nutrition-adherence-heading" class="space-y-3">
    <div>
        <flux:heading id="nutrition-adherence-heading" size="sm">{{ __('Daily adherence') }}</flux:heading>
        <flux:text variant="subtle">{{ __('Select a day to compare the planned meals with the logged food.') }}</flux:text>
    </div>

    <div class="grid grid-cols-7 gap-1.5">
        @foreach ($days as $day)
            @php($dayType = $day->planDay?->day_type)
            <button
                type="button"
                wire:key="adherence-day-{{ $day->date->toDateString() }}"
                wire:click="selectDay('{{ $day->date->toDateString() }}')"
                title="{{ $day->date->translatedFormat('l j F') }} · {{ $day->status->label() }}"
                @class([
                    'flex min-w-0 flex-col items-center gap-0.5 rounded-lg border px-1 py-2 text-center transition hover:brightness-95',
                    $cellClasses($day->status),
                    'ring-2 ring-zinc-900 dark:ring-white' => $selectedDate === $day->date->toDateString(),
                    'outline-2 outline-offset-1 outline-dashed outline-zinc-400' => $day->isToday,
                ])
            >
                <span class="text-[0.65rem] font-medium uppercase tracking-wide">{{ $day->date->translatedFormat('D') }}</span>
                <span class="text-sm font-semibold">{{ $day->date->format('j') }}</span>
                <span class="flex h-4 items-center">
                    @if ($dayType === NutritionDayType::Training)
                        <flux:icon.bolt variant="micro" />
                    @elseif ($dayType === NutritionDayType::Recovery)
                        <flux:icon.heart variant="micro" />
                    @elseif ($dayType)
                        <flux:icon.moon variant="micro" />
                    @endif
                </span>
                <span class="text-[0.65rem] tabular-nums">{{ $day->isLogged() ? number_format($day->calories) : '—' }}</span>
                <span class="sr-only">{{ $day->status->label() }}</span>
            </button>
        @endforeach
    </div>

    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
        @foreach ($legend as $status)
            <span class="flex items-center gap-1.5">
                <span class="size-2.5 rounded-sm border {{ $cellClasses($status) }}"></span>
                {{ $status->label() }}
            </span>
        @endforeach
        <span class="flex items-center gap-1"><flux:icon.bolt variant="micro" /> {{ __('Training') }}</span>
        <span class="flex items-center gap-1"><flux:icon.moon variant="micro" /> {{ __('Rest') }}</span>
    </div>
</section>
