@props(['profile', 'user'])

<section aria-labelledby="training-profile-heading" class="space-y-4">
    <div class="space-y-4">
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
    </div>

    <div class="pt-4 border-t border-zinc-200 dark:border-zinc-700">
        <flux:heading size="sm" class="mb-2">{{ __('Training locations') }}</flux:heading>
        
        @if ($user && $user->trainingLocations && $user->trainingLocations->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-zinc-500 dark:text-zinc-400">
                    <thead class="text-xs text-zinc-700 uppercase bg-zinc-50 dark:bg-zinc-800 dark:text-zinc-400">
                        <tr>
                            <th class="px-4 py-2">{{ __('Location') }}</th>
                            <th class="px-4 py-2 text-right">{{ __('Equipments') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($user->trainingLocations as $location)
                            <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                <td class="px-4 py-2">{{ $location->name }}</td>
                                <td class="px-4 py-2 text-right">
                                    {{ is_array($location->equipment) ? count($location->equipment) : 0 }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="rounded-lg border border-dashed border-zinc-300 p-4 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                {{ __('No training locations configured.') }}
            </div>
        @endif
    </div>
</section>
