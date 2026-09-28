@props(['exercise', 'imageUrl', 'activeTab' => 'details', 'user' => null, 'exerciseProfile' => null])

<div class="flex min-h-dvh flex-col bg-white dark:bg-zinc-900">
    <header class="border-b border-zinc-200 p-5 dark:border-zinc-700">
        <div class="flex items-start gap-3">
            <img src="{{ $imageUrl }}" alt="" class="size-16 rounded-lg object-cover" />
            <div class="min-w-0 flex-1">
                <flux:heading size="lg" class="truncate">{{ $exercise['name'] }}</flux:heading>
                <flux:text variant="subtle" class="font-mono text-xs">{{ $exercise['id'] }}</flux:text>
            </div>
            <flux:button variant="ghost" icon="x-mark" size="sm" wire:click="closeExerciseDetail" aria-label="{{ __('Close exercise details') }}" />
        </div>

        <img src="{{ $imageUrl }}" alt="{{ $exercise['name'] }}" class="mt-5 aspect-[4/5] w-full rounded-xl bg-zinc-100 object-cover dark:bg-zinc-800" />

        <nav aria-label="{{ __('Exercise details') }}" role="tablist" class="mt-5 flex gap-1 overflow-x-auto border-b border-zinc-200 dark:border-zinc-700">
            @foreach (['details' => __('Details and metadata'), 'instructions' => __('Instructions')] as $tab => $label)
                <button
                    type="button"
                    role="tab"
                    wire:click="selectExerciseTab('{{ $tab }}')"
                    aria-selected="{{ $activeTab === $tab ? 'true' : 'false' }}"
                    class="shrink-0 border-b-2 px-3 py-2 text-sm font-medium transition {{ $activeTab === $tab ? 'border-zinc-950 text-zinc-950 dark:border-white dark:text-white' : 'border-transparent text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' }}"
                >
                    {{ $label }}
                </button>
            @endforeach

            @if ($user)
                <button
                    type="button"
                    role="tab"
                    wire:click="selectExerciseTab('profile')"
                    aria-selected="{{ $activeTab === 'profile' ? 'true' : 'false' }}"
                    class="shrink-0 border-b-2 px-3 py-2 text-sm font-medium transition {{ $activeTab === 'profile' ? 'border-zinc-950 text-zinc-950 dark:border-white dark:text-white' : 'border-transparent text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' }}"
                >
                    {{ __('Profile') }}
                </button>
            @endif
        </nav>
    </header>

    <div class="flex-1 overflow-y-auto p-5">
        @switch($activeTab)
            @case('details')
                <div class="space-y-7">
                    <p class="text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $exercise['description'] }}</p>

                    <section class="space-y-3">
                        <flux:heading size="sm">{{ __('Exercise details') }}</flux:heading>
                        <dl class="grid gap-3 sm:grid-cols-2">
                            <x-exercises.reference-detail-item :label="__('Category')" type="exerciseCategories" :ids="[$exercise['category']]" />
                            <x-exercises.reference-detail-item :label="__('Difficulty')" type="trainingLevels" :ids="[$exercise['difficulty']]" />
                            <x-exercises.reference-detail-item :label="__('Tracking')" type="trackingModes" :ids="[$exercise['trackingMode']]" />
                            <x-dashboard.detail-item :label="__('MET value')" :value="number_format($exercise['metValue'], 1)" />

                            <x-ui.reference-box :label="__('Primary muscles')">
                                @foreach($exercise['primaryMuscles'] as $muscle)
                                    <x-exercises.muscle-icon :muscle="$muscle" />
                                @endforeach
                            </x-ui.reference-box>

                            @if ($exercise['secondaryMuscles'] !== [])
                                <x-ui.reference-box :label="__('Secondary muscles')">
                                    @foreach($exercise['secondaryMuscles'] as $muscle)
                                        <x-exercises.muscle-icon :muscle="$muscle" />
                                    @endforeach
                                </x-ui.reference-box>
                            @endif
                        </dl>
                    </section>

                    <section class="space-y-3">
                        <flux:heading size="sm">{{ __('Programming metadata') }}</flux:heading>
                        <dl class="grid gap-3 sm:grid-cols-2">
                            <x-ui.reference-box :label="__('Equipment')">
                                @forelse($exercise['requiredEquipment'] as $equipment)
                                    <x-exercises.equipment-icon :equipment="$equipment" />
                                @empty
                                    {{ __('None') }}
                                @endforelse
                            </x-ui.reference-box>

                            <x-exercises.reference-detail-item :label="__('Movement patterns')" type="movementPatterns" :ids="$exercise['movementPatterns']" />
                            <x-exercises.reference-detail-item :label="__('Goals')" type="goals" :ids="$exercise['compatibleGoals']" class="sm:col-span-2" />
                            <x-exercises.reference-detail-item :label="__('Target metrics')" type="targetMetrics" :ids="$exercise['targetMetrics']" class="sm:col-span-2" />
                            @if ($exercise['contraindicatedRestrictions'] !== [])
                                <x-exercises.reference-detail-item :label="__('Contraindications')" type="movementRestrictions" :ids="$exercise['contraindicatedRestrictions']" class="sm:col-span-2" />
                            @endif
                            @if ($exercise['substitutionCandidates'] !== [])
                                <x-dashboard.detail-item :label="__('Substitutions')" :value="collect($exercise['substitutionCandidates'])->map(fn (string $candidate): string => str($candidate)->headline())->join(', ')" class="sm:col-span-2" />
                            @endif
                        </dl>
                    </section>

                    @if ($exercise['notesForLlm'])
                        <section class="space-y-2">
                            <flux:heading size="sm">{{ __('Notes for planning') }}</flux:heading>
                            <flux:callout variant="secondary" icon="sparkles">{{ $exercise['notesForLlm'] }}</flux:callout>
                        </section>
                    @endif
                </div>
                @break

            @case('instructions')
                <div class="space-y-7">
                    <section class="space-y-3">
                        <flux:heading size="sm">{{ __('Instructions') }}</flux:heading>
                        <ol class="list-decimal space-y-2 pl-5 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                            @foreach ($exercise['instructions'] as $instruction)
                                <li>{{ $instruction }}</li>
                            @endforeach
                        </ol>
                    </section>

                    @if ($exercise['tips'] !== [])
                        <section class="space-y-3">
                            <flux:heading size="sm">{{ __('Tips') }}</flux:heading>
                            <ul class="list-disc space-y-2 pl-5 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                                @foreach ($exercise['tips'] as $tip)
                                    <li>{{ $tip }}</li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                </div>
                @break

            @case('profile')
                @if ($exerciseProfile)
                    <dl class="grid gap-3 sm:grid-cols-2">
                        <x-dashboard.detail-item :label="__('Level')" :value="str($exerciseProfile->level)->headline()" />
                        <x-dashboard.detail-item :label="__('Working weight')" :value="$exerciseProfile->working_weight ? __(':weight kg', ['weight' => $exerciseProfile->working_weight]) : __('Not set')" />
                        <x-dashboard.detail-item :label="__('Estimated one-rep max')" :value="$exerciseProfile->estimated_one_rep_max ? __(':weight kg', ['weight' => $exerciseProfile->estimated_one_rep_max]) : __('Not set')" />
                        <x-dashboard.detail-item :label="__('Maximum reps')" :value="$exerciseProfile->max_reps ?? __('Not set')" />
                        <x-dashboard.detail-item :label="__('Preferred rep range')" :value="$exerciseProfile->preferred_rep_range_min && $exerciseProfile->preferred_rep_range_max ? __(':min–:max reps', ['min' => $exerciseProfile->preferred_rep_range_min, 'max' => $exerciseProfile->preferred_rep_range_max]) : __('Not set')" />
                        <x-dashboard.detail-item :label="__('Typical duration')" :value="$exerciseProfile->typical_duration_minutes ? trans_choice(':count min', $exerciseProfile->typical_duration_minutes, ['count' => $exerciseProfile->typical_duration_minutes]) : __('Not set')" />
                        <x-dashboard.detail-item :label="__('Typical distance')" :value="$exerciseProfile->typical_distance_km ? __(':distance km', ['distance' => $exerciseProfile->typical_distance_km]) : __('Not set')" />
                        <x-dashboard.detail-item :label="__('Typical pace')" :value="$exerciseProfile->typical_pace_seconds_per_km ? gmdate('i:s', $exerciseProfile->typical_pace_seconds_per_km).' / km' : __('Not set')" />
                    </dl>
                @else
                    <div class="rounded-lg border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                        {{ __('This user has not created a profile for this exercise yet.') }}
                    </div>
                @endif
                @break
        @endswitch
    </div>
</div>
