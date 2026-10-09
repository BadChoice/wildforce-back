<x-layouts::app :title="__('Subscription')">
    <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 rounded-xl">
        <div class="flex flex-col gap-2">
            <flux:heading size="xl">{{ __('Choose your subscription') }}</flux:heading>
            <flux:text>{{ __('Continue with Stripe’s secure checkout. You can cancel future renewals from Stripe.') }}</flux:text>
        </div>

        @if ($canCheckout)
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($checkoutPlans as $checkoutPlan)
                    <flux:card class="flex flex-col gap-4">
                        <div class="flex flex-col gap-1">
                            <flux:heading size="lg">{{ str($checkoutPlan['plan']->value)->replace('_', ' ')->title() }}</flux:heading>
                            <flux:text>{{ __('Choose a billing period for your :plan subscription.', ['plan' => str($checkoutPlan['plan']->value)->replace('_', ' ')->title()]) }}</flux:text>
                        </div>

                        <div class="flex flex-col gap-2">
                            @foreach ($checkoutPlan['intervals'] as $interval)
                                <form method="POST" action="{{ route('subscription.billing.checkout', ['plan' => $checkoutPlan['plan']->value, 'interval' => $interval['value']]) }}">
                                    @csrf
                                    <flux:button type="submit" variant="primary" class="w-full">
                                        {{ $interval['price'] === null
                                            ? __('Continue :interval', ['interval' => str($interval['value'])->title()])
                                            : __('Continue :interval (:price)', ['interval' => str($interval['value'])->title(), 'price' => $interval['price']]) }}
                                    </flux:button>
                                </form>
                            @endforeach
                        </div>
                    </flux:card>
                @endforeach
            </div>
        @else
            <flux:callout variant="success" icon="check-circle" heading="{{ __('You already have a subscription.') }}">
                {{ __('Your :plan subscription is currently :status.', [
                    'plan' => str($subscription->plan->value)->replace('_', ' ')->title(),
                    'status' => str($subscription->status->value)->replace('_', ' ')->title(),
                ]) }}
            </flux:callout>
        @endif
    </div>
</x-layouts::app>
