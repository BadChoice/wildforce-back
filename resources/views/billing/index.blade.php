<x-layouts::app :title="__('Subscription')">
    <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 rounded-xl">
        <div class="flex flex-col gap-2">
            <flux:heading size="xl">{{ __('Choose your subscription') }}</flux:heading>
            <flux:text>{{ __('Continue with Stripe’s secure checkout. You can cancel future renewals from Stripe.') }}</flux:text>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <flux:card class="flex flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <flux:heading size="lg">{{ __('Monthly') }}</flux:heading>
                    <flux:text>{{ __('Flexible month-to-month access.') }}</flux:text>
                </div>

                <form method="POST" action="{{ route('billing.checkout', ['interval' => 'monthly']) }}">
                    @csrf
                    <flux:button type="submit" variant="primary" class="w-full">{{ __('Continue monthly') }}</flux:button>
                </form>
            </flux:card>

            <flux:card class="flex flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <flux:heading size="lg">{{ __('Yearly') }}</flux:heading>
                    <flux:text>{{ __('One year of uninterrupted access.') }}</flux:text>
                </div>

                <form method="POST" action="{{ route('billing.checkout', ['interval' => 'yearly']) }}">
                    @csrf
                    <flux:button type="submit" variant="primary" class="w-full">{{ __('Continue yearly') }}</flux:button>
                </form>
            </flux:card>
        </div>
    </div>
</x-layouts::app>
