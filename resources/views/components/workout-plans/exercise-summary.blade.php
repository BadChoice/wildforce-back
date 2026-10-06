@props(['plannedExercise'])

@php
    $reps = match (true) {
        $plannedExercise->reps_min !== null && $plannedExercise->reps_max !== null && $plannedExercise->reps_min !== $plannedExercise->reps_max => "{$plannedExercise->reps_min}–{$plannedExercise->reps_max}",
        $plannedExercise->reps_min !== null => (string) $plannedExercise->reps_min,
        $plannedExercise->reps_max !== null => (string) $plannedExercise->reps_max,
        default => null,
    };
    $summary = collect([$plannedExercise->sets, $reps])->filter(fn (mixed $value): bool => filled($value))->implode(' × ');
@endphp

<li {{ $attributes->merge(['class' => 'flex items-center gap-2']) }}>
    <img src="{{ $plannedExercise->imageUrl() }}" class="size-8 shrink-0 rounded-md bg-zinc-100 object-cover dark:bg-zinc-800" alt="" loading="lazy" />
    <div class="min-w-0">
        <p class="truncate font-medium text-zinc-700 dark:text-zinc-300">{{ str($plannedExercise->exercise)->headline() }}</p>
        @if ($summary !== '')
            <p class="tabular-nums text-zinc-500 dark:text-zinc-400">{{ $summary }}</p>
        @endif
    </div>
</li>
