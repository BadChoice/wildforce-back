@props(['day', 'meals', 'entries'])

@use('App\Enums\MealType')

@php
    $planDay = $day->planDay;
    $macros = [
        ['label' => __('Calories'), 'unit' => 'kcal', 'logged' => $day->calories, 'target' => (float) $planDay?->target_calories],
        ['label' => __('Protein'), 'unit' => 'g', 'logged' => $day->proteinGrams, 'target' => (float) $planDay?->target_protein_grams],
        ['label' => __('Carbs'), 'unit' => 'g', 'logged' => $day->carbsGrams, 'target' => (float) $planDay?->target_carbs_grams],
        ['label' => __('Fat'), 'unit' => 'g', 'logged' => $day->fatGrams, 'target' => (float) $planDay?->target_fat_grams],
    ];
    $mealTypeOrder = array_map(fn (MealType $mealType): string => $mealType->value, MealType::cases());
    $entriesByMealType = $entries
        ->groupBy(fn ($entry): string => $entry->meal_type?->value ?? 'other')
        ->sortBy(fn ($group, string $mealType): int => ($position = array_search($mealType, $mealTypeOrder, true)) === false ? count($mealTypeOrder) : $position);
    $imageUrl = fn ($entry): ?string => match (true) {
        $entry->media?->local_relative_path !== null => route('nutrition-log-entries.image', $entry),
        $entry->media?->remote_url !== null => $entry->media->remote_url,
        default => null,
    };
@endphp

<section aria-labelledby="nutrition-day-heading" class="space-y-6 text-sm">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <flux:heading id="nutrition-day-heading" size="sm" class="text-base">{{ $day->date->translatedFormat('l j F Y') }}</flux:heading>
            @if ($planDay?->planned_workout_title)
                <flux:text variant="subtle">{{ $planDay->planned_workout_title }}@if ($planDay->planned_workout_focus) · {{ str($planDay->planned_workout_focus)->headline() }}@endif</flux:text>
            @endif
        </div>
        <div class="flex flex-wrap gap-1.5">
            @if ($planDay)
                <flux:badge size="sm">{{ $planDay->day_type->label() }}</flux:badge>
                <flux:badge size="sm">{{ __(':demand demand', ['demand' => $planDay->energy_demand->label()]) }}</flux:badge>
            @endif
            <flux:badge size="sm" :color="$day->status->color()">{{ $day->status->label() }}</flux:badge>
        </div>
    </div>

    <div class="grid gap-2 sm:grid-cols-2">
        @foreach ($macros as $macro)
            @php
                $percentage = $macro['target'] > 0 ? (int) round(($macro['logged'] / $macro['target']) * 100) : null;
            @endphp
            <flux:card class="space-y-2 p-3">
                <div class="flex items-baseline justify-between gap-2">
                    <flux:text variant="subtle" class="text-xs">{{ $macro['label'] }}</flux:text>
                    <span class="tabular-nums">
                        <span class="font-semibold">{{ number_format($macro['logged']) }}</span>
                        @if ($macro['target'] > 0)
                            <span class="text-zinc-500 dark:text-zinc-400">/ {{ number_format($macro['target']) }} {{ $macro['unit'] }}</span>
                        @else
                            <span class="text-zinc-500 dark:text-zinc-400">{{ $macro['unit'] }}</span>
                        @endif
                    </span>
                </div>
                @if ($percentage !== null)
                    <flux:progress :value="min($percentage, 100)" :color="$percentage > 110 ? 'red' : ($percentage >= 90 ? 'green' : 'amber')" />
                @endif
            </flux:card>
        @endforeach
    </div>

    <div class="space-y-6">
        <div class="space-y-3">
            <flux:heading size="sm">{{ __('Logged food') }}</flux:heading>

            @forelse ($entriesByMealType as $mealType => $mealEntries)
                <div wire:key="logged-meal-type-{{ $mealType }}" class="space-y-2">
                    <flux:text variant="subtle" class="text-xs font-medium uppercase tracking-wide">{{ MealType::tryFrom($mealType)?->label() ?? str($mealType)->headline() }}</flux:text>

                    @foreach ($mealEntries as $entry)
                        @php
                            $entryImageUrl = $imageUrl($entry);
                        @endphp
                        <div wire:key="logged-entry-{{ $entry->id }}" class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <div class="flex items-start gap-3">
                                @if ($entryImageUrl)
                                    <a href="{{ $entryImageUrl }}" target="_blank" rel="noopener" class="shrink-0">
                                        <img src="{{ $entryImageUrl }}" alt="{{ $entry->title }}" loading="lazy" class="size-16 rounded-md object-cover" />
                                    </a>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <span class="truncate font-medium">{{ $entry->title }}</span>
                                        <span class="shrink-0 text-xs text-zinc-500 dark:text-zinc-400">{{ $entry->logged_at->setTimezone($day->date->getTimezone())->format('H:i') }}</span>
                                    </div>
                                    @if ($entry->notes)
                                        <p class="mt-1 text-zinc-600 dark:text-zinc-300">{{ $entry->notes }}</p>
                                    @endif
                                </div>
                            </div>

                            @if ($entry->items->isNotEmpty())
                                <ul class="mt-2 divide-y divide-zinc-100 text-xs dark:divide-zinc-800">
                                    @foreach ($entry->items as $item)
                                        <li wire:key="logged-item-{{ $item->id }}" class="flex items-baseline justify-between gap-2 py-1.5">
                                            <span class="min-w-0 truncate">
                                                {{ $item->name }}
                                                <span class="text-zinc-500 dark:text-zinc-400">
                                                    @if ($item->amount_grams)
                                                        · {{ number_format((float) $item->amount_grams) }} g
                                                    @elseif ($item->unit)
                                                        · {{ (float) $item->quantity }} {{ $item->unit }}
                                                    @endif
                                                </span>
                                            </span>
                                            <span class="shrink-0 tabular-nums text-zinc-500 dark:text-zinc-400">
                                                {{ number_format((float) $item->calories) }} kcal · P {{ number_format((float) $item->protein_grams) }} · C {{ number_format((float) $item->carbs_grams) }} · F {{ number_format((float) $item->fat_grams) }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                </div>
            @empty
                <div class="rounded-lg border border-dashed border-zinc-300 p-4 text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                    {{ __('Nothing was logged on this day.') }}
                </div>
            @endforelse
        </div>

        <div class="space-y-3 rounded-lg border border-dashed border-zinc-300 p-4 dark:border-zinc-700">
            <flux:heading size="sm">{{ __('Planned meals') }}</flux:heading>

            @if ($planDay?->pre_workout_guidance || $planDay?->post_workout_guidance)
                <dl class="grid gap-2">
                    @if ($planDay->pre_workout_guidance)
                        <x-dashboard.detail-item :label="__('Pre-workout')" :value="$planDay->pre_workout_guidance" />
                    @endif
                    @if ($planDay->post_workout_guidance)
                        <x-dashboard.detail-item :label="__('Post-workout')" :value="$planDay->post_workout_guidance" />
                    @endif
                </dl>
            @endif

            @forelse ($meals as $meal)
                <div wire:key="planned-meal-{{ $meal->id }}" class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="font-medium">{{ $meal->title }}</span>
                        <span class="text-xs tabular-nums text-zinc-500 dark:text-zinc-400">
                            {{ number_format((float) $meal->target_calories) }} kcal · P {{ number_format((float) $meal->target_protein_grams) }} · C {{ number_format((float) $meal->target_carbs_grams) }} · F {{ number_format((float) $meal->target_fat_grams) }}
                        </span>
                    </div>
                    @if ($meal->guidance)
                        <p class="mt-1 text-zinc-600 dark:text-zinc-300">{{ $meal->guidance }}</p>
                    @endif
                    @if (! empty($meal->example_foods))
                        <div class="mt-2 flex flex-wrap gap-1">
                            @foreach ($meal->example_foods as $food)
                                @php
                                    $foodName = is_array($food) ? ($food['name'] ?? null) : $food;
                                    $foodAmountGrams = is_array($food) ? ($food['amountGrams'] ?? $food['amount_grams'] ?? null) : null;
                                @endphp

                                @if ($foodName)
                                    <flux:badge size="sm" color="zinc">
                                        {{ str($foodName)->headline() }}
                                        @if ($foodAmountGrams)
                                            · {{ number_format((float) $foodAmountGrams) }} g
                                        @endif
                                    </flux:badge>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <div class="text-zinc-500 dark:text-zinc-400">
                    {{ $planDay ? __('This day has no planned meals.') : __('No nutrition plan covers this day.') }}
                </div>
            @endforelse
        </div>
    </div>
</section>
