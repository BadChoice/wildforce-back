<x-layouts::app :title="__('Exercises')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-900">
            <div class="flex flex-col gap-1">
                <flux:heading size="lg" level="1">{{ __('Exercise catalog') }}</flux:heading>
                <flux:text variant="subtle">
                    {{ trans_choice(':count exercise · catalog v:version', $catalog['metadata']['exerciseCount'], ['count' => $catalog['metadata']['exerciseCount'], 'version' => $catalog['metadata']['schemaVersion']]) }}
                </flux:text>
            </div>

            <form method="GET" class="mt-6 grid gap-4 md:grid-cols-4">
                <label class="grid gap-1.5 md:col-span-2">
                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Search') }}</span>
                    <input name="search" value="{{ $filters['search'] }}" placeholder="{{ __('Name, identifier, or description') }}" class="rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm outline-none ring-zinc-950/10 focus:ring-2 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:ring-white/20" />
                </label>

                <label class="grid gap-1.5">
                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Category') }}</span>
                    <select name="category" class="rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm outline-none ring-zinc-950/10 focus:ring-2 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:ring-white/20">
                        <option value="">{{ __('All categories') }}</option>
                        @foreach ($categories as $catalogCategory)
                            <option value="{{ $catalogCategory['id'] }}" @selected($filters['category'] === $catalogCategory['id'])>{{ $catalogCategory['name'] }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="grid gap-1.5">
                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Muscle group') }}</span>
                    <select name="muscle" class="rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm outline-none ring-zinc-950/10 focus:ring-2 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:ring-white/20">
                        <option value="">{{ __('All muscle groups') }}</option>
                        @foreach ($muscleGroups as $muscleGroup)
                            <option value="{{ $muscleGroup['id'] }}" @selected($filters['muscle'] === $muscleGroup['id'])>{{ $muscleGroup['name'] }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="md:col-span-4">
                    <flux:button type="submit" variant="primary">{{ __('Apply filters') }}</flux:button>
                    <flux:button :href="route('exercises.index')" variant="ghost" wire:navigate>{{ __('Reset') }}</flux:button>
                </div>
            </form>
        </div>

        <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Exercise') }}</flux:table.column>
                    <flux:table.column>{{ __('Category') }}</flux:table.column>
                    <flux:table.column>{{ __('Primary muscles') }}</flux:table.column>
                    <flux:table.column>{{ __('Tracking') }}</flux:table.column>
                    <flux:table.column>{{ __('MET') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($exercises as $exercise)
                        <flux:table.row :key="$exercise['id']">
                            <flux:table.cell variant="strong">
                                <div class="flex flex-col gap-1">
                                    <span>{{ $exercise['name'] }}</span>
                                    <span class="font-mono text-xs font-normal text-zinc-500 dark:text-zinc-400">{{ $exercise['id'] }}</span>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell><flux:badge>{{ $exercise['category'] }}</flux:badge></flux:table.cell>
                            <flux:table.cell>{{ implode(', ', $exercise['primaryMuscles']) }}</flux:table.cell>
                            <flux:table.cell>{{ $exercise['trackingMode'] }}</flux:table.cell>
                            <flux:table.cell>{{ number_format($exercise['metValue'], 1) }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="py-8 text-center">{{ __('No exercises match the selected filters.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </div>
</x-layouts::app>
