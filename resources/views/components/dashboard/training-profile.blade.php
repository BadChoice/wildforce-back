@props(['profile'])

<section aria-labelledby="training-profile-heading" class="space-y-4">
    <div>
        <flux:heading id="training-profile-heading" size="sm">{{ __('Training profile') }}</flux:heading>
        <flux:text variant="subtle">{{ __('The preferences used to personalise the user’s training.') }}</flux:text>
    </div>

    @if ($profile)
        <dl class="grid gap-3 sm:grid-cols-2">
            <x-dashboard.detail-item :label="__('Goal')" :value="str($profile->goal)->headline()" />
            <x-dashboard.detail-item :label="__('Training level')" :value="str($profile->general_training_level)->headline()" />
            <x-dashboard.detail-item :label="__('Lifestyle')" :value="str($profile->lifestyle)->headline()" />
            <x-dashboard.detail-item :label="__('Gym type')" :value="str($profile->gym_type)->headline()" />
            <x-dashboard.detail-item :label="__('Split preference')" :value="str($profile->training_split_preference)->headline()" />
            <x-dashboard.detail-item :label="__('Preferred duration')" :value="trans_choice(':count min', $profile->preferred_workout_duration_minutes, ['count' => $profile->preferred_workout_duration_minutes])" />
            <x-dashboard.detail-item :label="__('Training days')" :value="collect($profile->workout_days)->map(fn (string $day): string => str($day)->headline())->join(', ') ?: __('Not set')" class="sm:col-span-2" />
        </dl>
    @else
        <div class="rounded-lg border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
            {{ __('This user has not configured a training profile yet.') }}
        </div>
    @endif
</section>
