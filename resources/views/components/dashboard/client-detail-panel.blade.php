@props(['client', 'activeTab', 'progressionAnalysis' => null, 'showProgressionAnalysis' => false])

<div class="max-h-[80vh] overflow-y-auto">
    <header class="border-zinc-200 p-5 dark:border-zinc-700">
        @if ($showProgressionAnalysis)
            <flux:button size="sm" variant="ghost" icon="arrow-left" wire:click="closeProgressionAnalysis" class="-ml-2 mb-3">{{ __('Training') }}</flux:button>
        @endif
        <div class="flex items-start gap-3">
            <x-user-avatar :user="$client" size="lg" />
            <div class="min-w-0 flex-1">
                <flux:heading size="lg" class="truncate">{{ $client->name }}</flux:heading>
                <flux:text variant="subtle" class="truncate">{{ $client->email }}</flux:text>
            </div>
            <flux:button variant="ghost" icon="x-mark" size="sm" wire:click="closeClientDetail" aria-label="{{ __('Close client details') }}" />
        </div>

        @unless ($showProgressionAnalysis)
            <nav aria-label="{{ __('Client details') }}" role="tablist" class="mt-5 flex gap-1 overflow-x-auto border-b border-zinc-200 dark:border-zinc-700">
                @foreach (['info' => __('Info'), 'training' => __('Training'), 'nutrition' => __('Nutrition'), 'body-metrics' => __('Body metrics')] as $tab => $label)
                    <button
                        type="button"
                        role="tab"
                        wire:click="selectTab('{{ $tab }}')"
                        aria-selected="{{ $activeTab === $tab ? 'true' : 'false' }}"
                        class="shrink-0 border-b-2 px-3 py-2 text-sm font-medium transition {{ $activeTab === $tab ? 'border-zinc-950 text-zinc-950 dark:border-white dark:text-white' : 'border-transparent text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </nav>
        @endunless
    </header>

    <div class="p-5">
        @if ($showProgressionAnalysis && $progressionAnalysis)
            <x-dashboard.progression-analysis :analysis="$progressionAnalysis" />
        @else
            @switch($activeTab)
            @case('info')
                <dl class="grid gap-3 sm:grid-cols-2">
                    <x-dashboard.detail-item :label="__('Name')" :value="$client->name" />
                    <x-dashboard.detail-item :label="__('Email')" :value="$client->email" />
                    <x-dashboard.detail-item :label="__('Height')" :value="$client->height_cm ? __(':height cm', ['height' => $client->height_cm]) : __('Not set')" />
                    <x-dashboard.detail-item :label="__('Weight')" :value="$client->weight_kg ? __(':weight kg', ['weight' => $client->weight_kg]) : __('Not set')" />
                    <x-dashboard.detail-item :label="__('Date of birth')" :value="$client->birth_date?->format('d/m/Y') ?? __('Not set')" />
                    <x-dashboard.detail-item :label="__('Gender')" :value="$client->gender ? str($client->gender)->headline() : __('Not set')" />
                    <x-dashboard.detail-item :label="__('Language')" :value="$client->language ? str($client->language)->upper() : __('Not set')" />
                    <x-dashboard.detail-item :label="__('Current streak')" :value="trans_choice(':count day|:count days', $client->current_streak, ['count' => $client->current_streak])" />
                </dl>
                @break

            @case('training')
                <div class="space-y-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <flux:heading size="sm">{{ __('Workout plans') }}</flux:heading>
                            <flux:text variant="subtle">{{ __('Build and manage this client’s training plans.') }}</flux:text>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <flux:button size="sm" variant="ghost" icon="chart-bar" wire:click="openProgressionAnalysis">{{ __('Progression analysis') }}</flux:button>
                            <flux:button size="sm" variant="ghost" icon="document-text" wire:click="openNextPlanPrompt">{{ __('Next plan prompt') }}</flux:button>
                            <flux:button size="sm" variant="primary" icon="plus" wire:click="$dispatch('open-create-workout-plan-modal', { clientId: '{{ $client->id }}' })">{{ __('Workout plan') }}</flux:button>
                        </div>
                    </div>

                    <x-dashboard.workout-plan-list :workout-plans="$client->workoutPlans" editable />
                </div>
                @break

            @case('nutrition')
                <div class="space-y-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <flux:heading size="sm">{{ __('Nutrition plans') }}</flux:heading>
                            <flux:text variant="subtle">{{ __('Review the generator prompt and response schema for this client.') }}</flux:text>
                        </div>
                        <flux:button size="sm" variant="ghost" icon="document-text" wire:click="openNutritionPlanPrompt">{{ __('Nutrition plan prompt') }}</flux:button>
                    </div>
                </div>
                @break

            @case('body-metrics')
                <x-dashboard.user-body-metrics :user="$client" />
                @break
            @endswitch
        @endif
    </div>
</div>
