@props(['visibleStartDate', 'workoutsByDate'])

@use(App\Enums\WorkoutDayStatus)

<div class="overflow-x-auto pb-2">
    <div class="grid min-w-[52rem] grid-cols-[32px_repeat(7,minmax(0,1fr))] gap-2">
        @foreach (range(0, 3) as $weekOffset)
            @php($weekStart = $visibleStartDate->addWeeks($weekOffset))

            <div wire:key="workout-week-{{ $weekStart->toDateString() }}" class="group flex min-h-44 items-center justify-center rounded-xl bg-zinc-100 p-1 dark:bg-zinc-800">
                <span class="-rotate-90 whitespace-nowrap text-xs font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ __('Week') }} {{ $weekOffset + 1 }}</span>
            </div>

            @foreach (range(0, 6) as $dayOffset)
                @php($date = $weekStart->addDays($dayOffset))
                @php($dateKey = $date->toDateString())

                <div
                    wire:key="workout-day-cell-{{ $dateKey }}"
                    x-on:dragover.prevent="allowDrop($event, '{{ $dateKey }}')"
                    x-on:dragleave="leave($event, '{{ $dateKey }}')"
                    x-on:drop.prevent="drop($event, '{{ $dateKey }}')"
                    x-bind:class="{ 'ring-2 ring-indigo-500': isDropTarget('{{ $dateKey }}') }"
                    class="min-h-44 rounded-xl bg-zinc-50 p-2 transition-shadow dark:bg-zinc-950"
                >
                    <div class="mb-2 flex items-start justify-between gap-1">
                        <div class="min-w-0">
                            <p class="text-[10px] font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ $date->format('D') }}</p>
                            <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $date->format('j') }}</p>
                        </div>

                        <x-workout-plans.day-options-menu :date="$date" />
                    </div>

                    <div class="space-y-2">
                        @foreach ($workoutsByDate->get($dateKey, collect()) as $workoutDay)
                            @php($statusEnum = WorkoutDayStatus::tryFrom($workoutDay->status ?? ''))
                            <button
                                type="button"
                                wire:key="scheduled-workout-{{ $workoutDay->id }}"
                                wire:click="openWorkoutDay('{{ $workoutDay->id }}')"
                                @if ($statusEnum === WorkoutDayStatus::Planned)
                                    draggable="true"
                                    x-on:dragstart="start($event, '{{ $workoutDay->id }}')"
                                    x-on:dragend="end()"
                                    x-on:click.capture="suppressClickAfterDrag($event)"
                                    x-bind:class="{ 'cursor-grab active:cursor-grabbing opacity-50': isDragging('{{ $workoutDay->id }}') }"
                                @endif
                                class="w-full rounded-lg border border-zinc-200 bg-white p-2 text-left text-xs shadow-sm transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-800"
                            >
                                <div class="flex items-start justify-between gap-1">
                                    <p class="font-semibold text-zinc-900 dark:text-white">{{ $workoutDay->title }}</p>
                                    @if ($statusEnum && ! $statusEnum->isDefault())
                                        <flux:badge size="sm" :color="$statusEnum->color()" :icon="$statusEnum->icon()">
                                            {{ $statusEnum->label() }}
                                        </flux:badge>
                                    @endif
                                </div>
                                <p class="mt-1 text-zinc-500 dark:text-zinc-400">{{ str($workoutDay->focus)->headline() }}</p>
                                @if ($workoutDay->estimated_duration_minutes)
                                    <p class="mt-1 text-zinc-500 dark:text-zinc-400">{{ trans_choice(':count min', $workoutDay->estimated_duration_minutes, ['count' => $workoutDay->estimated_duration_minutes]) }}</p>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endforeach
    </div>
</div>
