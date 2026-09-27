@props(['exercise', 'imageUrl'])

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
    </header>

    <div class="flex-1 space-y-7 overflow-y-auto p-5">
        <section class="space-y-3">
            <img src="{{ $imageUrl }}" alt="{{ $exercise['name'] }}" class="aspect-[4/5] w-full rounded-xl bg-zinc-100 object-cover dark:bg-zinc-800" />
            <p class="text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $exercise['description'] }}</p>
        </section>

        <section class="space-y-3">
            <flux:heading size="sm">{{ __('Exercise details') }}</flux:heading>
            <dl class="grid gap-3 sm:grid-cols-2">
                <x-dashboard.detail-item :label="__('Category')" :value="str($exercise['category'])->headline()" />
                <x-dashboard.detail-item :label="__('Difficulty')" :value="str($exercise['difficulty'])->headline()" />
                <x-dashboard.detail-item :label="__('Tracking')" :value="str($exercise['trackingMode'])->headline()" />
                <x-dashboard.detail-item :label="__('MET value')" :value="number_format($exercise['metValue'], 1)" />
                <div class="rounded-lg bg-zinc-50 p-3 sm:col-span-2 dark:bg-zinc-800/60">
                    <dt class="text-xs font-medium tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Primary muscles') }}</dt>
                    <dd class="mt-2 flex flex-wrap gap-x-3 gap-y-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                        @foreach ($exercise['primaryMuscles'] as $muscle)
                            <x-exercises.reference-icon :id="$muscle" type="muscle-group" />
                        @endforeach
                    </dd>
                </div>
                @if ($exercise['secondaryMuscles'] !== [])
                    <div class="rounded-lg bg-zinc-50 p-3 sm:col-span-2 dark:bg-zinc-800/60">
                        <dt class="text-xs font-medium tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Secondary muscles') }}</dt>
                        <dd class="mt-2 flex flex-wrap gap-x-3 gap-y-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                            @foreach ($exercise['secondaryMuscles'] as $muscle)
                                <x-exercises.reference-icon :id="$muscle" type="muscle-group" />
                            @endforeach
                        </dd>
                    </div>
                @endif
            </dl>
        </section>

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

        <section class="space-y-3">
            <flux:heading size="sm">{{ __('Programming metadata') }}</flux:heading>
            <dl class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800/60">
                    <dt class="text-xs font-medium tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Equipment') }}</dt>
                    <dd class="mt-2 flex flex-wrap gap-x-3 gap-y-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                        @forelse ($exercise['requiredEquipment'] as $equipment)
                            <x-exercises.reference-icon :id="$equipment" type="equipment" />
                        @empty
                            <span>{{ __('None') }}</span>
                        @endforelse
                    </dd>
                </div>
                <x-dashboard.detail-item :label="__('Movement patterns')" :value="collect($exercise['movementPatterns'])->map(fn (string $pattern): string => str($pattern)->headline())->join(', ')" />
                <x-dashboard.detail-item :label="__('Goals')" :value="collect($exercise['compatibleGoals'])->map(fn (string $goal): string => str($goal)->headline())->join(', ')" class="sm:col-span-2" />
                <x-dashboard.detail-item :label="__('Target metrics')" :value="collect($exercise['targetMetrics'])->map(fn (string $metric): string => str($metric)->headline())->join(', ')" class="sm:col-span-2" />
                @if ($exercise['contraindicatedRestrictions'] !== [])
                    <x-dashboard.detail-item :label="__('Contraindications')" :value="collect($exercise['contraindicatedRestrictions'])->map(fn (string $restriction): string => str($restriction)->headline())->join(', ')" class="sm:col-span-2" />
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
</div>
