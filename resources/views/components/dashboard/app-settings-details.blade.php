@props(['appSettings'])

@if ($appSettings === null)
    <x-dashboard.placeholder-tab :title="__('No app settings')" :description="__('This user has not synced app settings yet.')" />
@else
    @php
        $booleanSettings = [
            'is_health_kit_enabled' => __('Health Kit'),
            'is_watch_auto_tracking_enabled' => __('Watch auto tracking'),
            'is_screen_on_during_workout_enabled' => __('Screen on during workout'),
            'is_notifications_enabled' => __('Notifications'),
            'is_full_focus_mode_enabled' => __('Full focus mode'),
            'has_seen_notification_request' => __('Seen notification request'),
            'has_seen_body_progress_tutorial' => __('Seen body progress tutorial'),
            'has_rated_app' => __('Rated app'),
        ];
    @endphp

    <dl class="grid gap-3 sm:grid-cols-2">
        @foreach ($booleanSettings as $attribute => $label)
            <x-dashboard.detail-item :label="$label" :value="$appSettings->{$attribute} ? __('Yes') : __('No')" />
        @endforeach
        <x-dashboard.detail-item
            class="sm:col-span-2"
            :label="__('Full focus selection')"
            :value="filled($appSettings->full_focus_selection) ? collect($appSettings->full_focus_selection)->map(fn ($value) => is_scalar($value) ? str($value)->headline() : json_encode($value))->join(', ') : __('Not set')"
        />
        <x-dashboard.detail-item :label="__('Last updated')" :value="$appSettings->updated_at?->format('d/m/Y H:i') ?? __('Not set')" />
    </dl>
@endif
