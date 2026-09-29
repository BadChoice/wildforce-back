<?php

use App\Models\Subscription;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Subscription settings')] class extends Component
{
    #[Computed]
    public function subscription(): ?Subscription
    {
        return Auth::user()->subscription()->first();
    }
};
?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Subscription settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Subscription')" :subheading="__('Review your current access and billing details')">
        @if ($subscription = $this->subscription)
            <flux:card class="mt-6 space-y-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <flux:heading size="lg">{{ str($subscription->plan->value)->replace('_', ' ')->title() }}</flux:heading>
                        <flux:text>{{ __('Your current subscription details.') }}</flux:text>
                    </div>

                    <flux:badge :color="$subscription->isActive() ? 'green' : 'zinc'">
                        {{ str($subscription->status->value)->replace('_', ' ')->title() }}
                    </flux:badge>
                </div>

                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Provider') }}</dt>
                        <dd>{{ str($subscription->provider->value)->replace('_', ' ')->title() }}</dd>
                    </div>
                    @if ($subscription->billing_amount && $subscription->billing_currency)
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Price') }}</dt>
                            <dd>{{ $subscription->billing_amount }} {{ $subscription->billing_currency }}</dd>
                        </div>
                    @endif
                    @if ($subscription->billing_interval && $subscription->billing_interval_count)
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Billing period') }}</dt>
                            <dd>{{ __('Every :count :interval', [
                                'count' => $subscription->billing_interval_count,
                                'interval' => $subscription->billing_interval->value,
                            ]) }}</dd>
                        </div>
                    @endif
                    @if ($subscription->renews_at)
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ $subscription->auto_renews ? __('Next renewal') : __('Access ends') }}</dt>
                            <dd>{{ $subscription->renews_at->format('Y-m-d') }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($subscription->provider->value === 'stripe')
                    <form method="POST" action="{{ route('subscription.stripe-portal') }}">
                        @csrf
                        <flux:button type="submit" variant="primary">{{ __('Manage with Stripe') }}</flux:button>
                    </form>
                @endif
            </flux:card>
        @else
            <flux:callout variant="secondary" icon="information-circle" heading="{{ __('No subscription') }}">
                {{ __('You do not currently have a subscription.') }}
            </flux:callout>
        @endif
    </x-pages::settings.layout>
</section>
