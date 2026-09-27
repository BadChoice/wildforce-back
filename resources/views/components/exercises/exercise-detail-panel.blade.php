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
                <x-dashboard.detail-item :label="__('Primary muscles')" :value="collect($exercise['primaryMuscles'])->map(fn (string $muscle): string => str($muscle)->headline())->join(', ')" class="sm:col-span-2" />
                @if ($exercise['secondaryMuscles'] !== [])
                    <x-dashboard.detail-item :label="__('Secondary muscles')" :value="collect($exercise['secondaryMuscles'])->map(fn (string $muscle): string => str($muscle)->headline())->join(', ')" class="sm:col-span-2" />
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
                <x-dashboard.detail-item :label="__('Equipment')" :value="collect($exercise['requiredEquipment'])->map(fn (string $equipment): string => str($equipment)->headline())->join(', ') ?: __('None')" />
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
