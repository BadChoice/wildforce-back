@props(['profile'])

@php
    $headlineList = fn (?array $values): string => collect($values)->map(fn (string $value): string => str($value)->headline())->join(', ') ?: __('None');
    $formatHour = fn (?int $hour): ?string => $hour === null ? null : sprintf('%02d:00', $hour);
@endphp

<section aria-labelledby="nutrition-profile-heading" class="space-y-4">
    <div class="space-y-4">
        <div>
            <flux:heading id="nutrition-profile-heading" size="sm">{{ __('Nutrition profile') }}</flux:heading>
            <flux:text variant="subtle">{{ __('The preferences used to personalise the user’s nutrition.') }}</flux:text>
        </div>

        @if ($profile)
            <dl class="grid gap-3 sm:grid-cols-2">
                <x-dashboard.detail-item :label="__('Dietary style')" :value="$profile->dietary_style->label()" />
                <x-dashboard.detail-item :label="__('Meals per day')" :value="$profile->meals_per_day_preference" />
                <x-dashboard.detail-item
                    :label="__('Eating window')"
                    :value="$profile->preferred_eating_window_start_hour !== null && $profile->preferred_eating_window_end_hour !== null
                        ? $formatHour($profile->preferred_eating_window_start_hour).' – '.$formatHour($profile->preferred_eating_window_end_hour)
                        : __('Not set')"
                />
                <x-dashboard.detail-item :label="__('Meal suggestions')" :value="$profile->wants_meal_suggestions ? __('Yes') : __('No')" />
                <x-dashboard.detail-item :label="__('Cooking effort')" :value="$profile->cooking_effort->label()" />
                <x-dashboard.detail-item :label="__('Budget sensitivity')" :value="$profile->budget_sensitivity->label()" />
                <x-dashboard.detail-item :label="__('Preferred protein sources')" :value="$headlineList($profile->preferred_protein_sources)" class="sm:col-span-2" />
                <x-dashboard.detail-item :label="__('Allergies & intolerances')" :value="$headlineList($profile->allergies_and_intolerances)" class="sm:col-span-2" />
                <x-dashboard.detail-item :label="__('Excluded foods')" :value="$headlineList($profile->excluded_foods)" class="sm:col-span-2" />
                <x-dashboard.detail-item :label="__('Dislikes')" :value="$headlineList($profile->dislikes)" class="sm:col-span-2" />
                @if ($profile->notes)
                    <x-dashboard.detail-item :label="__('Notes')" :value="$profile->notes" class="sm:col-span-2" />
                @endif
            </dl>
        @else
            <div class="rounded-lg border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                {{ __('This user has not configured a nutrition profile yet.') }}
            </div>
        @endif
    </div>
</section>
