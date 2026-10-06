@props(['visibleStartDate', 'workoutsByDate'])

@use(App\Enums\WorkoutDayStatus)

<div class="overflow-x-auto pb-2">
    <div class="flex gap-3">
        @foreach (range(0, 27) as $dayOffset)
            @php($date = $visibleStartDate->addDays($dayOffset))
            @php($dateKey = $date->toDateString())

            <div
                wire:key="kanban-day-column-{{ $dateKey }}"
                x-on:dragover.prevent="allowDrop($event, '{{ $dateKey }}')"
                x-on:dragleave="leave($event, '{{ $dateKey }}')"
                x-on:drop.prevent="drop($event, '{{ $dateKey }}')"
                x-bind:class="{ 'ring-2 ring-indigo-500': isDropTarget('{{ $dateKey }}') }"
                class="flex w-72 shrink-0 flex-col rounded-xl bg-zinc-50 p-2 transition-shadow dark:bg-zinc-950"
            >
                <div class="mb-2 flex items-start justify-between gap-1 px-1">
                    <div class="min-w-0">
                        <p class="text-[10px] font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ __('Week') }} {{ intdiv($dayOffset, 7) + 1 }} · {{ $date->format('D') }}</p>
                        <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ $date->format('j M') }}</p>
                    </div>

                    <x-workout-plans.day-options-menu :date="$date" />
                </div>

                <div class="space-y-2">
                    @foreach ($workoutsByDate->get($dateKey, collect()) as $workoutDay)
                        @php($statusEnum = WorkoutDayStatus::tryFrom($workoutDay->status ?? ''))

                        <div
                            wire:key="kanban-workout-{{ $workoutDay->id }}"
                            wire:click="openWorkoutDay('{{ $workoutDay->id }}')"
                            role="button"
                            tabindex="0"
                            @if ($statusEnum === WorkoutDayStatus::Planned)
                                draggable="true"
                                x-on:dragstart="start($event, '{{ $workoutDay->id }}')"
                                x-on:dragend="end()"
                                x-on:click.capture="suppressClickAfterDrag($event)"
                                x-bind:class="{ 'cursor-grab active:cursor-grabbing opacity-50': isDragging('{{ $workoutDay->id }}') }"
                            @endif
                            class="cursor-pointer rounded-lg border border-zinc-200 bg-white p-3 text-xs shadow-sm transition-colors hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:bg-zinc-800"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="font-semibold text-zinc-900 dark:text-white">{{ $workoutDay->title }}</p>
                                    @if ($statusEnum && ! $statusEnum->isDefault())
                                        <flux:badge size="sm" class="mt-1" :color="$statusEnum->color()" :icon="$statusEnum->icon()">
                                            {{ $statusEnum->label() }}
                                        </flux:badge>
                                    @endif
                                </div>

                                <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click.stop="openWorkoutDayEditor('{{ $workoutDay->id }}')" :aria-label="__('Edit :title', ['title' => $workoutDay->title])" />
                            </div>

                            <div class="mt-3 space-y-2">
                                @foreach ($workoutDay->blocks->sortBy('order_index') as $block)
                                    <div wire:key="kanban-block-{{ $block->id }}">
                                        <p class="font-medium text-zinc-700 dark:text-zinc-300">{{ str($block->type)->headline() }}</p>
                                        <ul class="mt-1 space-y-1.5">
                                            @forelse ($block->exercises->sortBy('order_index') as $plannedExercise)
                                                <x-workout-plans.exercise-summary :planned-exercise="$plannedExercise" wire:key="kanban-exercise-{{ $plannedExercise->id }}" />
                                            @empty
                                                <li class="text-zinc-400 dark:text-zinc-500">{{ __('No exercises') }}</li>
                                            @endforelse
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
