@props(['subscription'])

@if ($subscription === null)
    <x-dashboard.placeholder-tab :title="__('No subscription')" :description="__('This user has no subscription assigned.')" />
@else
    <section class="space-y-5">
        <div class="flex items-start justify-between gap-4">
            <div>
                <flux:heading size="lg">{{ str($subscription->plan->value)->replace('_', ' ')->title() }}</flux:heading>
                <flux:text variant="subtle">{{ __('Subscription details and current access.') }}</flux:text>
            </div>
            @if ($subscription->isActive())
                <flux:badge color="green">{{ __('Active') }}</flux:badge>
            @else
                <flux:badge color="zinc">{{ __('Inactive') }}</flux:badge>
            @endif
        </div>

        <dl class="grid gap-3 sm:grid-cols-2">
            <x-dashboard.detail-item :label="__('Plan')" :value="str($subscription->plan->value)->replace('_', ' ')->title()" />
            <x-dashboard.detail-item :label="__('Provider')" :value="str($subscription->provider->value)->replace('_', ' ')->title()" />
            <x-dashboard.detail-item :label="__('Status')" :value="str($subscription->status->value)->replace('_', ' ')->title()" />
            <x-dashboard.detail-item :label="__('Auto-renewal')" :value="$subscription->auto_renews ? __('Enabled') : __('Disabled')" />
            <x-dashboard.detail-item :label="__('Started')" :value="$subscription->starts_at?->format('Y-m-d H:i') ?? __('Not available')" />
            <x-dashboard.detail-item :label="__('Access ends')" :value="$subscription->renews_at?->format('Y-m-d H:i') ?? __('No expiry')" />
            @if ($subscription->cancelled_at)
                <x-dashboard.detail-item :label="__('Cancelled')" :value="$subscription->cancelled_at->format('Y-m-d H:i')" />
            @endif
        </dl>
    </section>
@endif
