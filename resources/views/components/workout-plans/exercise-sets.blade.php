@php
    $setConfiguration = $plannedExercise->set_style_configuration?->toArray() ?? [];
    $configuredSets = is_array($setConfiguration) && array_is_list($setConfiguration)
        ? $setConfiguration
        : data_get($setConfiguration, 'sets', []);
    $setCount = max(1, $plannedExercise->sets ?? 0, $perSetCount($plannedExercise->target_reps), $perSetCount($plannedExercise->target_weights_kg), $perSetCount($configuredSets));
    $defaultReps = $plannedExercise->target_reps ?? ['min' => $plannedExercise->reps_min, 'max' => $plannedExercise->reps_max];
@endphp

<article class="p-4 sm:p-5">
    <div class="flex min-w-0 items-start gap-3">
        <img src="{{ $plannedExercise->imageUrl() }}" class="size-12 shrink-0 rounded-xl bg-zinc-100 object-cover dark:bg-zinc-800" alt="" loading="lazy" />
        <div class="min-w-0 flex-1">
            <h5 class="truncate font-semibold text-zinc-950 dark:text-white">{{ str($plannedExercise->exercise)->headline() }}</h5>
            @if ($plannedExercise->notes)
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $plannedExercise->notes }}</p>
            @endif
        </div>
    </div>

    <div class="mt-4 overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700" role="table" aria-label="{{ __('Sets for :exercise', ['exercise' => str($plannedExercise->exercise)->headline()]) }}">
        <div class="hidden grid-cols-[3.25rem_minmax(6rem,1.4fr)_minmax(5rem,1fr)_minmax(5rem,1fr)_minmax(4.25rem,.75fr)_minmax(4.5rem,.75fr)] gap-3 bg-zinc-50 px-3 py-2 text-xs font-medium uppercase tracking-wide text-zinc-500 dark:bg-zinc-800/60 dark:text-zinc-400 md:grid" role="row">
            <span>{{ __('Set') }}</span><span>{{ __('Type') }}</span><span>{{ __('Weight') }}</span><span>{{ __('Reps') }}</span><span>{{ __('RIR') }}</span><span>{{ __('Rest') }}</span>
        </div>

        <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @for ($setIndex = 0; $setIndex < $setCount; $setIndex++)
                @php
                    $setDetails = data_get($configuredSets, $setIndex, []);
                    $setDetails = is_array($setDetails) ? $setDetails : [];
                    $setType = $setDetails['type'] ?? $valueForSet(data_get($setConfiguration, 'style') ?? data_get($setConfiguration, 'set_types') ?? data_get($setConfiguration, 'types') ?? data_get($setConfiguration, 'set_type') ?? data_get($setConfiguration, 'type'), $setIndex) ?? 'standard';
                    $rir = $setDetails['rir'] ?? $valueForSet(data_get($setConfiguration, 'target_rir') ?? data_get($setConfiguration, 'rirs') ?? data_get($setConfiguration, 'rir') ?? data_get($setConfiguration, 'rir_targets'), $setIndex);
                    $weight = $setDetails['weight_kg'] ?? $setDetails['weight'] ?? $valueForSet($plannedExercise->target_weights_kg, $setIndex) ?? $plannedExercise->target_weight_kg;
                    $reps = $formatReps($setDetails['reps'] ?? $valueForSet($defaultReps, $setIndex));
                    $rest = $formatRest($setDetails['rest_seconds'] ?? $plannedExercise->rest_seconds);
                @endphp

                <div class="grid grid-cols-2 gap-x-4 gap-y-3 px-3 py-3 text-sm md:grid-cols-[3.25rem_minmax(6rem,1.4fr)_minmax(5rem,1fr)_minmax(5rem,1fr)_minmax(4.25rem,.75fr)_minmax(4.5rem,.75fr)] md:items-center md:gap-3" role="row">
                    <div class="col-span-2 flex items-center gap-2 md:col-span-1"><span class="flex size-7 items-center justify-center rounded-lg bg-zinc-100 text-xs font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $setIndex + 1 }}</span><span class="text-xs font-medium uppercase tracking-wide text-zinc-400 md:hidden">{{ __('Set') }}</span></div>
                    <div><span class="mb-1 block text-xs font-medium uppercase tracking-wide text-zinc-400 md:hidden">{{ __('Type') }}</span><span class="inline-flex rounded-full bg-zinc-100 px-2 py-1 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">{{ str($setType)->headline() }}</span></div>
                    <div><span class="mb-1 block text-xs font-medium uppercase tracking-wide text-zinc-400 md:hidden">{{ __('Weight') }}</span><span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $formatWeight($weight) ?? '—' }}</span></div>
                    <div><span class="mb-1 block text-xs font-medium uppercase tracking-wide text-zinc-400 md:hidden">{{ __('Reps') }}</span><span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $reps ?? '—' }}</span></div>
                    <div><span class="mb-1 block text-xs font-medium uppercase tracking-wide text-zinc-400 md:hidden">{{ __('RIR') }}</span><span class="font-medium text-zinc-800 dark:text-zinc-200">{{ is_numeric($rir) ? $rir : '—' }}</span></div>
                    <div><span class="mb-1 block text-xs font-medium uppercase tracking-wide text-zinc-400 md:hidden">{{ __('Rest') }}</span><span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $rest ?? '—' }}</span></div>
                </div>
            @endfor
        </div>
    </div>
</article>
