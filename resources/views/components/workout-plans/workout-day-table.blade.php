@props(['workout'])

@php
    $formatWeight = static function (mixed $weight): ?string {
        if (! is_numeric($weight)) {
            return null;
        }

        return rtrim(rtrim(number_format((float) $weight, 2, '.', ''), '0'), '.').' kg';
    };

    $formatRest = static function (mixed $seconds): ?string {
        if (! is_numeric($seconds) || (int) $seconds === 0) {
            return null;
        }

        $minutes = intdiv((int) $seconds, 60);
        $remainingSeconds = (int) $seconds % 60;

        return $minutes > 0 ? sprintf('%d:%02d', $minutes, $remainingSeconds) : sprintf('%ds', $remainingSeconds);
    };

    $perSetCount = static fn (mixed $value): int => is_array($value) && array_is_list($value) ? count($value) : 0;
    $valueForSet = static fn (mixed $value, int $setIndex): mixed => ! is_array($value) ? $value : (array_is_list($value) ? ($value[$setIndex] ?? null) : $value);

    $formatReps = static function (mixed $reps): ?string {
        if (is_numeric($reps)) {
            return (string) $reps;
        }

        if (! is_array($reps)) {
            return null;
        }

        $minimum = $reps['min'] ?? $reps['minimum'] ?? null;
        $maximum = $reps['max'] ?? $reps['maximum'] ?? null;

        if (is_numeric($minimum) && is_numeric($maximum)) {
            return $minimum === $maximum ? (string) $minimum : "{$minimum}–{$maximum}";
        }

        return is_numeric($reps['reps'] ?? null) ? (string) $reps['reps'] : null;
    };

    $blocks = $workout->blocks->sortBy(['order_index', 'id']);
@endphp

<div {{ $attributes->merge(['class' => 'space-y-4']) }}>
    @forelse ($blocks as $block)
        @php($blockRest = $formatRest($block->rest_after_block_seconds))

        <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900" aria-labelledby="block-{{ $block->id }}">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-100 bg-zinc-50/80 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-800/40">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-zinc-900 text-sm font-semibold text-white dark:bg-white dark:text-zinc-900">{{ $loop->iteration }}</div>
                    <div class="min-w-0">
                        <h4 id="block-{{ $block->id }}" class="font-semibold text-zinc-950 dark:text-white">{{ str($block->type->value)->headline() }}</h4>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ trans_choice(':count round|:count rounds', $block->rounds, ['count' => $block->rounds]) }}
                            @if ($blockRest)
                                <span aria-hidden="true">·</span> {{ __('Rest :rest', ['rest' => $blockRest]) }}
                            @endif
                        </p>
                    </div>
                </div>

                <span class="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-zinc-600 ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-zinc-300 dark:ring-zinc-700">{{ trans_choice(':count exercise|:count exercises', $block->exercises->count(), ['count' => $block->exercises->count()]) }}</span>
            </div>

            <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($block->exercises->sortBy(['order_index', 'id']) as $plannedExercise)
                    @include('components.workout-plans.exercise-sets', ['plannedExercise' => $plannedExercise, 'formatWeight' => $formatWeight, 'formatRest' => $formatRest, 'formatReps' => $formatReps, 'perSetCount' => $perSetCount, 'valueForSet' => $valueForSet])
                @empty
                    <p class="px-4 py-5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No exercises have been added to this block yet.') }}</p>
                @endforelse
            </div>
        </section>
    @empty
        <div class="rounded-2xl border border-dashed border-zinc-300 px-4 py-8 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">{{ __('No exercises have been added to this workout day yet.') }}</div>
    @endforelse
</div>
