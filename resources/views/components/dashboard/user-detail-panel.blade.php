@props(['user', 'activeTab', 'progressionAnalysis' => null, 'showProgressionAnalysis' => false])

<div class="flex min-h-dvh flex-col bg-white dark:bg-zinc-900">
    <header class="border-zinc-200 p-5 dark:border-zinc-700">
        @if ($showProgressionAnalysis)
            <flux:button size="sm" variant="ghost" icon="arrow-left" wire:click="closeProgressionAnalysis" class="-ml-2 mb-3">{{ __('Training') }}</flux:button>
        @endif
        <div class="flex items-start gap-3">
            <x-user-avatar :user="$user" size="lg" />
            <div class="min-w-0 flex-1">
                <flux:heading size="lg" class="truncate">{{ $user->name }}</flux:heading>
                <flux:text variant="subtle" class="truncate">{{ $user->email }}</flux:text>
            </div>
            <flux:button variant="ghost" icon="x-mark" size="sm" wire:click="closeUserDetail" aria-label="{{ __('Close user details') }}" />
        </div>

        @unless ($showProgressionAnalysis)
            <nav aria-label="{{ __('User details') }}" role="tablist" class="mt-5 flex gap-1 overflow-x-auto border-b border-zinc-200 dark:border-zinc-700">
                @foreach (['training' => __('Training'), 'exercise-profiles' => __('Exercise Profiles'), 'nutrition' => __('Nutrition'), 'body-metrics' => __('Body metrics'), 'subscription' => __('Subscription')] as $tab => $label)
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

    <div class="flex-1 overflow-y-auto p-5">
        @if ($showProgressionAnalysis && $progressionAnalysis)
            <x-dashboard.progression-analysis :analysis="$progressionAnalysis" />
        @else
            @switch($activeTab)
            @case('training')
                <div class="space-y-8">
                    <div class="flex justify-end">
                        <flux:button size="sm" variant="ghost" icon="chart-bar" wire:click="openProgressionAnalysis">{{ __('Progression analysis') }}</flux:button>
                    </div>
                    <x-dashboard.training-profile :profile="$user->trainingPreferences" />
                    <x-dashboard.workout-plan-list :workout-plans="$user->workoutPlans" />
                </div>
                @break

            @case('exercise-profiles')
                <x-dashboard.exercise-profile-list :exercise-profiles="$user->exerciseProfiles" />
                @break

            @case('nutrition')
                <x-dashboard.placeholder-tab :title="__('Nutrition')" :description="__('Nutrition details will be available here soon.')" />
                @break

            @case('body-metrics')
                <x-dashboard.user-body-metrics :user="$user" />
                @break

            @case('subscription')
                <x-dashboard.subscription-details :subscription="$user->subscription" />
                @break
            @endswitch
        @endif
    </div>
</div>
