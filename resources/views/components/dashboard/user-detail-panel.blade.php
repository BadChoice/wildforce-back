@props(['user', 'activeTab'])

<div class="flex min-h-dvh flex-col bg-white dark:bg-zinc-900">
    <header class="border-b border-zinc-200 p-5 dark:border-zinc-700">
        <div class="flex items-start gap-3">
            <flux:avatar :name="$user->name" size="lg" />
            <div class="min-w-0 flex-1">
                <flux:heading size="lg" class="truncate">{{ $user->name }}</flux:heading>
                <flux:text variant="subtle" class="truncate">{{ $user->email }}</flux:text>
            </div>
            <flux:button variant="ghost" icon="x-mark" size="sm" wire:click="closeUserDetail" aria-label="{{ __('Close user details') }}" />
        </div>

        <nav aria-label="{{ __('User details') }}" role="tablist" class="mt-5 flex gap-1 overflow-x-auto border-b border-zinc-200 dark:border-zinc-700">
            @foreach (['training' => __('Training'), 'exercise-profiles' => __('Exercise Profiles'), 'nutrition' => __('Nutrition'), 'subscription' => __('Subscription')] as $tab => $label)
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
    </header>

    <div class="flex-1 overflow-y-auto p-5">
        @switch($activeTab)
            @case('training')
                <div class="space-y-8">
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

            @case('subscription')
                <x-dashboard.placeholder-tab :title="__('Subscription')" :description="__('Subscription details will be available here soon.')" />
                @break
        @endswitch
    </div>
</div>
