@props(['workoutDay'])

@php
    $catalog = app(\App\Services\ExerciseCatalog\ExerciseCatalog::class);
    $statusEnum = \App\Enums\WorkoutDayStatus::tryFrom($workoutDay->status ?? '') ?? \App\Enums\WorkoutDayStatus::Completed;
@endphp

<div class="space-y-6 p-6">
    <!-- Header -->
    <div class="flex flex-wrap items-start justify-between gap-4 border-b border-zinc-200 pb-5 dark:border-zinc-700">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <flux:heading size="xl" level="2">{{ $workoutDay->title }}</flux:heading>
                <flux:badge :color="$statusEnum->color()" :icon="$statusEnum->icon()">
                    {{ $statusEnum->label() }}
                </flux:badge>
            </div>
            <div class="flex flex-wrap items-center gap-3 text-sm text-zinc-500 dark:text-zinc-400">
                <span>{{ __('Focus: :focus', ['focus' => str($workoutDay->focus)->headline()]) }}</span>
                @if ($workoutDay->scheduled_for)
                    <span>•</span>
                    <span>{{ __('Scheduled: :date', ['date' => $workoutDay->scheduled_for->toFormattedDateString()]) }}</span>
                @endif
                @if ($workoutDay->completed_at)
                    <span>•</span>
                    <span>{{ __('Completed: :date', ['date' => $workoutDay->completed_at->toDayDateTimeString()]) }}</span>
                @endif
            </div>
        </div>

        <flux:modal.close>
            <flux:button variant="ghost" icon="x-mark" size="sm" />
        </flux:modal.close>
    </div>

    <!-- Summary Metrics Grid -->
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 bg-zinc-50/50 p-3.5 dark:border-zinc-800 dark:bg-zinc-900/50">
            <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Duration') }}</span>
            <p class="mt-1 text-lg font-semibold text-zinc-900 dark:text-white">
                {{ $workoutDay->estimated_duration_minutes ? trans_choice(':count min', $workoutDay->estimated_duration_minutes, ['count' => $workoutDay->estimated_duration_minutes]) : '—' }}
            </p>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-zinc-50/50 p-3.5 dark:border-zinc-800 dark:bg-zinc-900/50">
            <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Total Volume') }}</span>
            <p class="mt-1 text-lg font-semibold text-zinc-900 dark:text-white">
                {{ $workoutDay->total_volume_kg ? number_format((float) $workoutDay->total_volume_kg, 1).' kg' : '—' }}
            </p>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-zinc-50/50 p-3.5 dark:border-zinc-800 dark:bg-zinc-900/50">
            <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Active Calories') }}</span>
            <p class="mt-1 text-lg font-semibold text-zinc-900 dark:text-white">
                {{ $workoutDay->active_calories_burned ? number_format((float) $workoutDay->active_calories_burned).' kcal' : '—' }}
            </p>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-zinc-50/50 p-3.5 dark:border-zinc-800 dark:bg-zinc-900/50">
            <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Avg / Max Heart Rate') }}</span>
            <p class="mt-1 text-lg font-semibold text-zinc-900 dark:text-white">
                @if ($workoutDay->average_heart_rate)
                    {{ round((float) $workoutDay->average_heart_rate) }} {{ $workoutDay->maximum_heart_rate ? '/ '.round((float) $workoutDay->maximum_heart_rate) : '' }} <span class="text-xs font-normal text-zinc-500">bpm</span>
                @else
                    —
                @endif
            </p>
        </div>
    </div>

    @if ($workoutDay->notes)
        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <h4 class="text-xs font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ __('Notes') }}</h4>
            <p class="mt-1 text-sm text-zinc-800 dark:text-zinc-200">{{ $workoutDay->notes }}</p>
        </div>
    @endif

    <!-- Exercises & Results -->
    <div class="space-y-6">
        <flux:heading size="lg">{{ __('Exercise Results') }}</flux:heading>

        @php
            $blocks = $workoutDay->blocks ?? collect();
            $directExercises = $workoutDay->directExercises ?? collect();
        @endphp

        @forelse ($blocks as $blockIndex => $block)
            <div wire:key="completed-block-{{ $block->id ?? $blockIndex }}" class="space-y-3">
                <div class="flex items-center gap-2 border-b border-zinc-200 pb-2 dark:border-zinc-800">
                    <span class="text-xs font-bold tracking-wider text-zinc-400 uppercase">{{ __('Block') }} {{ $blockIndex + 1 }}</span>
                    <span class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">{{ str($block->type ?? 'standard')->headline() }}</span>
                    @if ($block->notes)
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">— {{ $block->notes }}</span>
                    @endif
                </div>

                <div class="space-y-4">
                    @foreach ($block->exercises as $exercise)
                        @php
                            $exerciseImage = $catalog->imageUrl($exercise->exercise);
                            $results = $exercise->exerciseResults ?? collect();
                            $latestResult = $results->first();
                        @endphp

                        <div wire:key="completed-exercise-{{ $exercise->id }}" class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                            <div class="flex min-w-0 items-start gap-3">
                                <img src="{{ $exerciseImage }}" class="size-14 shrink-0 rounded-xl bg-zinc-100 object-cover dark:bg-zinc-800" alt="" loading="lazy" />
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <h4 class="truncate font-semibold text-zinc-900 dark:text-white">{{ str($exercise->exercise)->headline() }}</h4>
                                        @if ($latestResult?->feedback)
                                            <flux:badge size="sm" variant="subtle">{{ __('Feedback: :feedback', ['feedback' => str($latestResult->feedback)->headline()]) }}</flux:badge>
                                        @endif
                                    </div>

                                    <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                                        <span>{{ __('Target: :sets sets × :min-:max reps', ['sets' => $exercise->sets, 'min' => $exercise->reps_min, 'max' => $exercise->reps_max]) }}</span>
                                        @if ($exercise->target_weight_kg)
                                            <span>•</span>
                                            <span>{{ __('Target Weight: :weight kg', ['weight' => number_format((float) $exercise->target_weight_kg, 1)]) }}</span>
                                        @endif
                                    </div>

                                    @if ($exercise->notes)
                                        <p class="mt-1.5 text-xs text-zinc-600 dark:text-zinc-400">{{ $exercise->notes }}</p>
                                    @endif
                                </div>
                            </div>

                            <!-- Results breakdown -->
                            @if ($results->isNotEmpty())
                                <div class="mt-4 border-t border-zinc-100 pt-3 dark:border-zinc-800">
                                    <h5 class="mb-2 text-xs font-semibold text-zinc-500 uppercase dark:text-zinc-400">{{ __('Logged Results') }}</h5>

                                    @foreach ($results as $result)
                                        <div wire:key="result-{{ $result->id }}" class="space-y-2 rounded-lg bg-zinc-50 p-3 text-xs dark:bg-zinc-800/50">
                                            <div class="flex flex-wrap items-center justify-between gap-2 text-zinc-600 dark:text-zinc-300">
                                                <span>{{ __('Completed Sets: :sets', ['sets' => $result->completed_sets ?? (is_array($result->per_set_reps) ? count($result->per_set_reps) : '—')]) }}</span>
                                                @if ($result->completed_weight)
                                                    <span>{{ __('Weight: :weight kg', ['weight' => number_format((float) $result->completed_weight, 1)]) }}</span>
                                                @endif
                                                @if ($result->completed_distance_km)
                                                    <span>{{ __('Distance: :dist km', ['dist' => number_format((float) $result->completed_distance_km, 2)]) }}</span>
                                                @endif
                                                @if ($result->completed_at)
                                                    <span class="text-zinc-400">{{ $result->completed_at->format('H:i') }}</span>
                                                @endif
                                            </div>

                                            @if (is_array($result->per_set_reps) && count($result->per_set_reps) > 0)
                                                <div class="flex flex-wrap gap-2 pt-1">
                                                    @foreach ($result->per_set_reps as $idx => $reps)
                                                        @php
                                                            $weightKg = is_array($result->per_set_weights_kg) ? ($result->per_set_weights_kg[$idx] ?? null) : null;
                                                        @endphp
                                                        <span class="inline-flex items-center gap-1 rounded-md bg-white px-2 py-1 font-medium text-zinc-800 shadow-2xs dark:bg-zinc-800 dark:text-zinc-200 border border-zinc-200 dark:border-zinc-700">
                                                            <span class="text-zinc-400">#{{ $idx + 1 }}:</span>
                                                            <span>{{ $reps }} {{ __('reps') }}</span>
                                                            @if ($weightKg)
                                                                <span class="text-zinc-500">@ {{ number_format((float) $weightKg, 1) }}kg</span>
                                                            @endif
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="mt-3 text-xs italic text-zinc-400">
                                    {{ __('No logged results recorded for this exercise.') }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            @if ($directExercises->isEmpty())
                <p class="py-4 text-center text-sm text-zinc-500">{{ __('No exercises found for this workout day.') }}</p>
            @endif
        @endforelse

        @if ($directExercises->isNotEmpty())
            <div class="space-y-3">
                <div class="border-b border-zinc-200 pb-2 dark:border-zinc-800">
                    <span class="text-xs font-bold tracking-wider text-zinc-400 uppercase">{{ __('Direct Exercises') }}</span>
                </div>

                <div class="space-y-4">
                    @foreach ($directExercises as $exercise)
                        @php
                            $exerciseImage = $catalog->imageUrl($exercise->exercise);
                            $results = $exercise->exerciseResults ?? collect();
                            $latestResult = $results->first();
                        @endphp

                        <div wire:key="completed-direct-exercise-{{ $exercise->id }}" class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                            <div class="flex min-w-0 items-start gap-3">
                                <img src="{{ $exerciseImage }}" class="size-14 shrink-0 rounded-xl bg-zinc-100 object-cover dark:bg-zinc-800" alt="" loading="lazy" />
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <h4 class="truncate font-semibold text-zinc-900 dark:text-white">{{ str($exercise->exercise)->headline() }}</h4>
                                        @if ($latestResult?->feedback)
                                            <flux:badge size="sm" variant="subtle">{{ __('Feedback: :feedback', ['feedback' => str($latestResult->feedback)->headline()]) }}</flux:badge>
                                        @endif
                                    </div>

                                    <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                                        <span>{{ __('Target: :sets sets × :min-:max reps', ['sets' => $exercise->sets, 'min' => $exercise->reps_min, 'max' => $exercise->reps_max]) }}</span>
                                        @if ($exercise->target_weight_kg)
                                            <span>•</span>
                                            <span>{{ __('Target Weight: :weight kg', ['weight' => number_format((float) $exercise->target_weight_kg, 1)]) }}</span>
                                        @endif
                                    </div>

                                    @if ($exercise->notes)
                                        <p class="mt-1.5 text-xs text-zinc-600 dark:text-zinc-400">{{ $exercise->notes }}</p>
                                    @endif
                                </div>
                            </div>

                            @if ($results->isNotEmpty())
                                <div class="mt-4 border-t border-zinc-100 pt-3 dark:border-zinc-800">
                                    <h5 class="mb-2 text-xs font-semibold text-zinc-500 uppercase dark:text-zinc-400">{{ __('Logged Results') }}</h5>

                                    @foreach ($results as $result)
                                        <div wire:key="result-direct-{{ $result->id }}" class="space-y-2 rounded-lg bg-zinc-50 p-3 text-xs dark:bg-zinc-800/50">
                                            <div class="flex flex-wrap items-center justify-between gap-2 text-zinc-600 dark:text-zinc-300">
                                                <span>{{ __('Completed Sets: :sets', ['sets' => $result->completed_sets ?? (is_array($result->per_set_reps) ? count($result->per_set_reps) : '—')]) }}</span>
                                                @if ($result->completed_weight)
                                                    <span>{{ __('Weight: :weight kg', ['weight' => number_format((float) $result->completed_weight, 1)]) }}</span>
                                                @endif
                                                @if ($result->completed_distance_km)
                                                    <span>{{ __('Distance: :dist km', ['dist' => number_format((float) $result->completed_distance_km, 2)]) }}</span>
                                                @endif
                                                @if ($result->completed_at)
                                                    <span class="text-zinc-400">{{ $result->completed_at->format('H:i') }}</span>
                                                @endif
                                            </div>

                                            @if (is_array($result->per_set_reps) && count($result->per_set_reps) > 0)
                                                <div class="flex flex-wrap gap-2 pt-1">
                                                    @foreach ($result->per_set_reps as $idx => $reps)
                                                        @php
                                                            $weightKg = is_array($result->per_set_weights_kg) ? ($result->per_set_weights_kg[$idx] ?? null) : null;
                                                        @endphp
                                                        <span class="inline-flex items-center gap-1 rounded-md bg-white px-2 py-1 font-medium text-zinc-800 shadow-2xs dark:bg-zinc-800 dark:text-zinc-200 border border-zinc-200 dark:border-zinc-700">
                                                            <span class="text-zinc-400">#{{ $idx + 1 }}:</span>
                                                            <span>{{ $reps }} {{ __('reps') }}</span>
                                                            @if ($weightKg)
                                                                <span class="text-zinc-500">@ {{ number_format((float) $weightKg, 1) }}kg</span>
                                                            @endif
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
