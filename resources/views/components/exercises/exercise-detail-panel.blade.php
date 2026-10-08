@props(['exercise', 'imageUrls', 'activeTab' => 'details', 'user' => null, 'exerciseProfile' => null, 'isCoach' => false, 'coachExerciseContent' => null])

<div class="flex min-h-dvh flex-col bg-white dark:bg-zinc-900">
    <header class="border-zinc-200 p-5 dark:border-zinc-700">
        <div class="flex items-start gap-3">
            <div class="min-w-0 flex-1">
                <flux:heading size="lg" class="truncate">{{ $exercise['name'] }}</flux:heading>
                <flux:text variant="subtle" class="font-mono text-xs">{{ $exercise['id'] }}</flux:text>
            </div>
            <flux:button variant="ghost" icon="x-mark" size="sm" wire:click="closeExerciseDetail" aria-label="{{ __('Close exercise details') }}" />
        </div>

        <div x-data="{ 
            gender: 'female', 
            imageUrls: {{ json_encode($imageUrls) }} 
        }" class="mt-5 relative">
            <img :src="imageUrls[gender]" alt="{{ $exercise['name'] }}" class="aspect-[4/5] w-full rounded-xl bg-zinc-100 object-cover dark:bg-zinc-800" />
            
            <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex items-center gap-2 bg-black/30 backdrop-blur-sm px-3 py-1.5 rounded-full">
                <button 
                    type="button" 
                    @click="gender = 'female'" 
                    :class="gender === 'female' ? 'bg-white scale-110' : 'bg-white/50'" 
                    class="h-2 w-2 rounded-full transition-all" 
                    aria-label="{{ __('Female') }}"
                ></button>
                <button 
                    type="button" 
                    @click="gender = 'male'" 
                    :class="gender === 'male' ? 'bg-white scale-110' : 'bg-white/50'" 
                    class="h-2 w-2 rounded-full transition-all" 
                    aria-label="{{ __('Male') }}"
                ></button>
            </div>
        </div>

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

            @if ($isCoach)
                <button
                    type="button"
                    role="tab"
                    wire:click="selectExerciseTab('coach')"
                    aria-selected="{{ $activeTab === 'coach' ? 'true' : 'false' }}"
                    class="shrink-0 border-b-2 px-3 py-2 text-sm font-medium transition {{ $activeTab === 'coach' ? 'border-zinc-950 text-zinc-950 dark:border-white dark:text-white' : 'border-transparent text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' }}"
                >
                    {{ __('Coach content') }}
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

                            <x-exercises.reference-detail-item :label="__('Movement patterns')" type="movementPatterns" :ids="$exercise['movementPatterns']" class="sm:col-span-2" />
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
                        <x-dashboard.detail-item :label="__('Typical duration')" :value="$exerciseProfile->typical_duration_minutes ? __(':count min', ['count' => $exerciseProfile->typical_duration_minutes]) : __('Not set')" />
                        <x-dashboard.detail-item :label="__('Typical distance')" :value="$exerciseProfile->typical_distance_km ? __(':distance km', ['distance' => $exerciseProfile->typical_distance_km]) : __('Not set')" />
                        <x-dashboard.detail-item :label="__('Typical pace')" :value="$exerciseProfile->typical_pace_seconds_per_km ? gmdate('i:s', $exerciseProfile->typical_pace_seconds_per_km).' / km' : __('Not set')" />
                    </dl>
                    @if ($exerciseProfile->notes)
                        <section class="mt-5 space-y-2">
                            <flux:heading size="sm">{{ __('Notes') }}</flux:heading>
                            <flux:text class="whitespace-pre-line">{{ $exerciseProfile->notes }}</flux:text>
                        </section>
                    @endif
                @else
                    <div class="rounded-lg border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                        {{ __('This user has not created a profile for this exercise yet.') }}
                    </div>
                @endif
                @break

            @case('coach')
                <form wire:submit="saveCoachExerciseContent" class="space-y-7">
                    <flux:text variant="subtle">{{ __('Your clients will see this image, video, and notes for this exercise.') }}</flux:text>

                    <section
                        class="space-y-3"
                        x-data="{
                            previewUrl: null,
                            uploading: false,
                            async upload(event) {
                                const file = event.target.files[0];
                                event.target.value = '';

                                if (! file) {
                                    return;
                                }

                                this.uploading = true;
                                const resized = await this.resize(file, 1080, 1350);

                                $wire.$upload(
                                    'coachExerciseImage',
                                    resized,
                                    () => {
                                        this.previewUrl = URL.createObjectURL(resized);
                                        this.uploading = false;
                                    },
                                    () => this.uploading = false,
                                );
                            },
                            async resize(file, maxWidth, maxHeight) {
                                const bitmap = await createImageBitmap(file);
                                const scale = Math.min(1, maxWidth / bitmap.width, maxHeight / bitmap.height);
                                const canvas = document.createElement('canvas');
                                canvas.width = Math.round(bitmap.width * scale);
                                canvas.height = Math.round(bitmap.height * scale);
                                canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
                                bitmap.close();

                                const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.85));

                                return new File([blob], 'exercise.jpg', { type: 'image/jpeg' });
                            },
                            get hasPendingImage() {
                                return this.previewUrl !== null && !! $wire.coachExerciseImage;
                            },
                        }"
                    >
                        <flux:heading size="sm">{{ __('Image') }}</flux:heading>

                        <div class="flex items-start gap-4">
                            <div class="aspect-[4/5] w-28 shrink-0 overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-800">
                                <img x-show="hasPendingImage" x-bind:src="previewUrl" alt="" class="h-full w-full object-cover" />
                                @if ($coachExerciseContent?->imageUrl())
                                    <img x-show="! hasPendingImage" src="{{ $coachExerciseContent->imageUrl() }}" alt="{{ $exercise['name'] }}" class="h-96 w-80 object-cover" />
                                @else
                                    <div x-show="! hasPendingImage" class="flex h-full items-center justify-center p-2 text-center text-xs text-zinc-500 dark:text-zinc-400">{{ __('Default image') }}</div>
                                @endif
                            </div>

                            <div class="flex flex-col items-start gap-2">
                                <input type="file" accept="image/*" class="hidden" x-ref="imageInput" x-on:change="upload($event)" />
                                <flux:button type="button" size="sm" icon="photo" x-on:click="$refs.imageInput.click()" x-bind:disabled="uploading">
                                    {{ __('Choose image') }}
                                </flux:button>
                                <flux:text size="sm" variant="subtle" x-show="uploading">{{ __('Uploading…') }}</flux:text>
                                <flux:text size="sm" variant="subtle" x-show="hasPendingImage">{{ __('Press save to apply the new image.') }}</flux:text>

                                @if ($coachExerciseContent?->image_path)
                                    <flux:button type="button" size="sm" variant="ghost" icon="trash" wire:click="removeCoachExerciseImage" wire:confirm="{{ __('Remove your image for this exercise?') }}">
                                        {{ __('Use default image') }}
                                    </flux:button>
                                @endif
                            </div>
                        </div>

                        <flux:error name="coachExerciseImage" />
                    </section>

                    <section class="space-y-3">
                        <flux:heading size="sm">{{ __('Video') }}</flux:heading>
                        <flux:input wire:model="coachExerciseYoutubeUrl" :label="__('YouTube link')" placeholder="https://www.youtube.com/watch?v=…" />

                        @if ($coachExerciseContent?->youtube_video_id)
                            <div class="aspect-video w-full overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-800">
                                <iframe src="https://www.youtube-nocookie.com/embed/{{ $coachExerciseContent->youtube_video_id }}" title="{{ $exercise['name'] }}" class="h-full w-full" allowfullscreen loading="lazy"></iframe>
                            </div>
                        @endif
                    </section>

                    <section class="space-y-3">
                        <flux:textarea wire:model="coachExerciseNotes" :label="__('Notes')" rows="4" :placeholder="__('Technique cues or general guidance for your clients.')" />
                        <flux:error name="coachExerciseNotes" />
                    </section>

                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </form>
                @break
        @endswitch
    </div>
</div>
