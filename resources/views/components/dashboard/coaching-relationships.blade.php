@props(['coaches', 'coaching', 'canAddCoach'])

<div class="space-y-8">
    <section class="space-y-4">
        <div class="flex items-start justify-between gap-4">
            <div>
                <flux:heading size="sm">{{ __('Coaches') }}</flux:heading>
                <flux:text variant="subtle">{{ __('People coaching this user.') }}</flux:text>
            </div>

            @if ($canAddCoach)
                <flux:button size="sm" variant="primary" icon="plus" wire:click="openCoachingEnrollmentForm('coach')">
                    {{ __('Add coach') }}
                </flux:button>
            @endif
        </div>

        @if ($coaches->isEmpty())
            <flux:callout variant="secondary" icon="information-circle" heading="{{ __('No coach assigned') }}">
                {{ __('Assign a coach to start managing this user’s training.') }}
            </flux:callout>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Coach') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Started') }}</flux:table.column>
                    <flux:table.column>{{ __('Ended') }}</flux:table.column>
                    <flux:table.column>{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($coaches as $enrollment)
                        <flux:table.row :key="$enrollment->id">
                            <flux:table.cell variant="strong">
                                <div>{{ $enrollment->coach->name }}</div>
                                <flux:text class="text-xs" variant="subtle">{{ $enrollment->coach->email }}</flux:text>
                            </flux:table.cell>
                            <flux:table.cell><flux:badge :color="$enrollment->status === \App\Enums\CoachingEnrollmentStatus::Active ? 'green' : 'zinc'">{{ str($enrollment->status->value)->headline() }}</flux:badge></flux:table.cell>
                            <flux:table.cell>{{ $enrollment->starts_at->toFormattedDateString() }}</flux:table.cell>
                            <flux:table.cell>{{ $enrollment->ends_at?->toFormattedDateString() ?? '—' }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <x-dashboard.coaching-enrollment-actions :enrollment="$enrollment" />
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </section>

    <flux:separator />

    <section class="space-y-4">
        <div class="flex items-start justify-between gap-4">
            <div>
                <flux:heading size="sm">{{ __('Coaching') }}</flux:heading>
                <flux:text variant="subtle">{{ __('Clients coached by this user.') }}</flux:text>
            </div>

            <flux:button size="sm" variant="primary" icon="plus" wire:click="openCoachingEnrollmentForm('client')">
                {{ __('Add client') }}
            </flux:button>
        </div>

        @if ($coaching->isEmpty())
            <flux:callout variant="secondary" icon="information-circle" heading="{{ __('No clients assigned') }}">
                {{ __('Assign a client to start this user’s coaching relationship.') }}
            </flux:callout>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Client') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Started') }}</flux:table.column>
                    <flux:table.column>{{ __('Ended') }}</flux:table.column>
                    <flux:table.column>{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($coaching as $enrollment)
                        <flux:table.row :key="$enrollment->id">
                            <flux:table.cell variant="strong">
                                <div>{{ $enrollment->client->name }}</div>
                                <flux:text class="text-xs" variant="subtle">{{ $enrollment->client->email }}</flux:text>
                            </flux:table.cell>
                            <flux:table.cell><flux:badge :color="$enrollment->status === \App\Enums\CoachingEnrollmentStatus::Active ? 'green' : 'zinc'">{{ str($enrollment->status->value)->headline() }}</flux:badge></flux:table.cell>
                            <flux:table.cell>{{ $enrollment->starts_at->toFormattedDateString() }}</flux:table.cell>
                            <flux:table.cell>{{ $enrollment->ends_at?->toFormattedDateString() ?? '—' }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <x-dashboard.coaching-enrollment-actions :enrollment="$enrollment" />
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </section>
</div>
