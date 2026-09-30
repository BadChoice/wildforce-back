@props(['blocks', 'availableExercises', 'categories', 'muscleGroups', 'selectedWorkoutBlockId'])

<div class="flex max-h-[90vh] min-h-[40rem] flex-col overflow-hidden bg-white dark:bg-zinc-900">
    <header class="flex items-center justify-between gap-4 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
        <div class="min-w-0 flex-1">
            <flux:input wire:model="workoutDayTitle" placeholder="{{ __('Workout day name') }}" class="text-lg font-semibold" />
            <flux:error name="workoutDayTitle" />
        </div>
        <flux:button variant="ghost" icon="x-mark" wire:click="closeWorkoutDayEditor" aria-label="{{ __('Close workout day editor') }}" />
    </header>

    <div class="grid min-h-0 flex-1 lg:grid-cols-[19rem_minmax(0,1fr)]">
        <aside class="min-h-0 border-b border-zinc-200 p-4 dark:border-zinc-700 lg:overflow-y-auto lg:border-e lg:border-b-0">
            <flux:heading size="sm">{{ __('Exercises') }}</flux:heading>
            <flux:input wire:model.live.debounce.300ms="exerciseSearch" class="mt-3" icon="magnifying-glass" placeholder="{{ __('Search exercises') }}" />

            <div class="mt-3 grid gap-3">
                <flux:select wire:model.live="exerciseCategory" size="sm" aria-label="{{ __('Category') }}">
                    <option value="">{{ __('All categories') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category['id'] }}">{{ $category['name'] }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model.live="exerciseMuscle" size="sm" aria-label="{{ __('Muscle group') }}">
                    <option value="">{{ __('All muscle groups') }}</option>
                    @foreach ($muscleGroups as $muscleGroup)
                        <option value="{{ $muscleGroup['id'] }}">{{ $muscleGroup['name'] }}</option>
                    @endforeach
                </flux:select>
            </div>

            <div class="mt-4 space-y-2">
                @forelse (array_slice($availableExercises, 0, 30) as $exercise)
                    <div wire:key="catalog-exercise-{{ $exercise['id'] }}" class="flex items-center gap-3 rounded-lg border border-zinc-200 p-2 dark:border-zinc-700">
                        <img src="{{ app(\App\Services\ExerciseCatalog\ExerciseCatalog::class)->imageUrl($exercise['id']) }}" alt="" class="h-10 w-8 rounded object-cover" />
                        <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ $exercise['name'] }}</span>
                        <flux:button size="sm" variant="ghost" icon="plus" wire:click="addExercise('{{ $exercise['id'] }}')" aria-label="{{ __('Add :exercise', ['exercise' => $exercise['name']]) }}" />
                    </div>
                @empty
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No exercises match the search.') }}</p>
                @endforelse
            </div>
        </aside>

        <section class="min-h-0 overflow-y-auto p-5">
            <div class="mb-5 grid gap-4 sm:grid-cols-[minmax(0,1fr)_10rem_10rem]">
                <flux:field>
                    <flux:label>{{ __('Notes') }}</flux:label>
                    <flux:textarea wire:model="workoutDayNotes" rows="2" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Duration (minutes)') }}</flux:label>
                    <flux:input type="number" min="1" max="1440" wire:model="workoutDayEstimatedDurationMinutes" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Focus') }}</flux:label>
                    <flux:select wire:model="workoutDayFocus">
                        <option value="fullBody">{{ __('Full body') }}</option>
                        <option value="upperBody">{{ __('Upper body') }}</option>
                        <option value="lowerBody">{{ __('Lower body') }}</option>
                    </flux:select>
                </flux:field>
            </div>

            <div class="space-y-5">
                @foreach ($blocks as $blockIndex => $block)
                    <section wire:key="workout-block-{{ $block['id'] }}" class="overflow-hidden rounded-xl border {{ $selectedWorkoutBlockId === $block['id'] ? 'border-zinc-950 ring-1 ring-zinc-950 dark:border-white dark:ring-white' : 'border-zinc-200 dark:border-zinc-700' }}">
                        <div class="flex items-center justify-between gap-3 bg-zinc-50 px-4 py-3 dark:bg-zinc-800/60">
                            <button type="button" wire:click="selectWorkoutBlock('{{ $block['id'] }}')" class="min-w-0 flex-1 text-left">
                                <span class="font-medium">{{ __('Block :number', ['number' => $blockIndex + 1]) }}</span>
                                <span class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">{{ $selectedWorkoutBlockId === $block['id'] ? __('Selected') : __('Select to add exercises') }}</span>
                            </button>
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeWorkoutBlock('{{ $block['id'] }}')" aria-label="{{ __('Remove block :number', ['number' => $blockIndex + 1]) }}" />
                        </div>

                        <div class="space-y-3 p-4">
                            <flux:field>
                                <flux:label>{{ __('Block notes') }}</flux:label>
                                <flux:input wire:model="workoutDayBlocks.{{ $blockIndex }}.notes" />
                            </flux:field>

                            @forelse ($block['exercises'] as $exerciseIndex => $exercise)
                                <article wire:key="planned-exercise-{{ $exercise['id'] }}" class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                    <div class="flex items-center gap-3">
                                        <img src="{{ app(\App\Services\ExerciseCatalog\ExerciseCatalog::class)->imageUrl($exercise['exercise']) }}" alt="" class="h-16 w-12 rounded-md bg-zinc-100 object-cover dark:bg-zinc-800" loading="lazy" />
                                        <div class="min-w-0 flex-1 font-medium">{{ $exercise['name'] }}</div>
                                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeExercise('{{ $block['id'] }}', '{{ $exercise['id'] }}')" aria-label="{{ __('Remove :exercise', ['exercise' => $exercise['name']]) }}" />
                                    </div>
                                    <flux:textarea wire:model="workoutDayBlocks.{{ $blockIndex }}.exercises.{{ $exerciseIndex }}.notes" rows="2" class="mt-3" placeholder="{{ __('Exercise notes') }}" />
                                    <div class="mt-3 grid gap-3 sm:grid-cols-4">
                                        <flux:field><flux:label>{{ __('Sets') }}</flux:label><flux:input type="number" min="1" wire:model="workoutDayBlocks.{{ $blockIndex }}.exercises.{{ $exerciseIndex }}.sets" /></flux:field>
                                        <flux:field><flux:label>{{ __('Min reps') }}</flux:label><flux:input type="number" min="0" wire:model="workoutDayBlocks.{{ $blockIndex }}.exercises.{{ $exerciseIndex }}.reps_min" /></flux:field>
                                        <flux:field><flux:label>{{ __('Max reps') }}</flux:label><flux:input type="number" min="0" wire:model="workoutDayBlocks.{{ $blockIndex }}.exercises.{{ $exerciseIndex }}.reps_max" /></flux:field>
                                        <flux:field><flux:label>{{ __('Weight (kg)') }}</flux:label><flux:input type="number" min="0" step="0.5" wire:model="workoutDayBlocks.{{ $blockIndex }}.exercises.{{ $exerciseIndex }}.target_weight_kg" /></flux:field>
                                    </div>
                                    <flux:field class="mt-3"><flux:label>{{ __('Rest (seconds)') }}</flux:label><flux:input type="number" min="0" wire:model="workoutDayBlocks.{{ $blockIndex }}.exercises.{{ $exerciseIndex }}.rest_seconds" /></flux:field>
                                </article>
                            @empty
                                <p class="rounded-lg border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">{{ __('Select this block, then add exercises from the catalog.') }}</p>
                            @endforelse
                        </div>
                    </section>
                @endforeach
            </div>

            <flux:button class="mt-5" variant="ghost" icon="plus" wire:click="addWorkoutBlock">{{ __('Add block') }}</flux:button>
        </section>
    </div>

    <footer class="flex items-center justify-between gap-3 border-t border-zinc-200 px-5 py-4 dark:border-zinc-700">
        <flux:button variant="ghost" wire:click="closeWorkoutDayEditor">{{ __('Cancel') }}</flux:button>
        <flux:button variant="primary" wire:click="saveWorkoutDay">{{ __('Save workout day') }}</flux:button>
    </footer>
</div>
