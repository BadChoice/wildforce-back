<?php

use App\Enums\CoachingEnrollmentStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?string $selectedClientId = null;

    public string $selectedTab = 'info';

    public bool $showClientDetail = false;

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function clients(): Collection
    {
        return auth()->user()
            ->clients(CoachingEnrollmentStatus::Active)
            ->orderBy('users.name')
            ->get();
    }

    #[Computed]
    public function selectedClient(): ?User
    {
        if ($this->selectedClientId === null) {
            return null;
        }

        return auth()->user()
            ->clients(CoachingEnrollmentStatus::Active)
            ->with([
                'workoutPlans' => fn (HasMany $query): HasMany => $query
                    ->orderByDesc('starts_on')
                    ->orderByDesc('created_at')
                    ->with([
                        'workoutDays' => fn (HasMany $query): HasMany => $query->orderBy('order_index'),
                    ]),
            ])
            ->whereKey($this->selectedClientId)
            ->first();
    }

    public function selectClient(string $clientId): void
    {
        if (! auth()->user()->clients(CoachingEnrollmentStatus::Active)
            ->whereKey($clientId)
            ->exists()) {
            return;
        }

        $this->selectedClientId = $clientId;
        $this->selectedTab = 'info';
        $this->showClientDetail = true;
    }

    public function selectTab(string $tab): void
    {
        if (! in_array($tab, ['info', 'training', 'nutrition', 'body-metrics'], true)) {
            return;
        }

        $this->selectedTab = $tab;
    }

    public function closeClientDetail(): void
    {
        $this->showClientDetail = false;
    }
};
?>

<div>
    <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-900">
        <div class="mb-6">
            <flux:heading size="lg" level="1">{{ __('Clients') }}</flux:heading>
            <flux:text variant="subtle">{{ __('Clients enrolled in your coaching service.') }}</flux:text>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Client') }}</flux:table.column>
                <flux:table.column>{{ __('Email') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->clients as $client)
                    <flux:table.row :key="$client->id" wire:click="selectClient('{{ $client->id }}')" class="cursor-pointer">
                        <flux:table.cell variant="strong">
                            <div class="flex items-center gap-3">
                                <x-user-avatar :user="$client" />
                                <span>{{ $client->name }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $client->email }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="2" class="py-8 text-center">{{ __('No clients have been added yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    @if ($this->selectedClient)
        <flux:modal wire:model="showClientDetail" :closable="false" class="w-full max-w-3xl">
            <x-dashboard.client-detail-panel :client="$this->selectedClient" :active-tab="$selectedTab" />
        </flux:modal>
    @endif
</div>
