<?php

use App\Enums\Equipment;
use App\Models\TrainingLocation;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public bool $showModal = false;

    public ?string $userId = null;

    public ?string $editingLocationId = null;

    public string $locationName = '';

    public bool $locationIsDefault = false;

    public string $locationLatitude = '';

    public string $locationLongitude = '';

    public int $locationSortOrder = 0;

    /**
     * @var list<string>
     */
    public array $selectedEquipment = [];

    #[On('open-training-location-modal')]
    public function openModal(?string $userId = null, ?string $locationId = null): void
    {
        $this->resetValidation();
        $this->userId = $userId;
        $this->editingLocationId = $locationId;

        if ($userId && $locationId) {
            $user = User::query()->find($userId);
            $location = $user?->trainingLocations()->whereKey($locationId)->first();

            if ($location) {
                $this->locationName = $location->name;
                $this->locationIsDefault = (bool) $location->is_default;
                $this->locationLatitude = $location->latitude === null ? '' : (string) $location->latitude;
                $this->locationLongitude = $location->longitude === null ? '' : (string) $location->longitude;
                $this->locationSortOrder = (int) $location->sort_order;
                $this->selectedEquipment = collect($location->equipment ?? [])
                    ->map(fn (mixed $item): string => $item instanceof Equipment ? $item->value : (string) $item)
                    ->all();
                $this->showModal = true;

                return;
            }
        }

        $this->locationName = '';
        $this->locationIsDefault = false;
        $this->locationLatitude = '';
        $this->locationLongitude = '';
        $this->locationSortOrder = 0;
        $this->selectedEquipment = [];
        $this->showModal = true;
    }

    public function toggleEquipment(string $equipmentValue): void
    {
        if (in_array($equipmentValue, $this->selectedEquipment, true)) {
            $this->selectedEquipment = array_values(array_filter(
                $this->selectedEquipment,
                fn (string $item): bool => $item !== $equipmentValue,
            ));
        } else {
            $this->selectedEquipment[] = $equipmentValue;
        }
    }

    public function save(): void
    {
        if (! $this->userId) {
            return;
        }

        $user = User::query()->find($this->userId);
        if (! $user) {
            return;
        }

        $this->validate([
            'locationName' => ['required', 'string', 'max:255'],
            'locationIsDefault' => ['boolean'],
            'locationLatitude' => ['nullable', 'numeric', 'between:-90,90'],
            'locationLongitude' => ['nullable', 'numeric', 'between:-180,180'],
            'locationSortOrder' => ['required', 'integer', 'min:0'],
            'selectedEquipment' => ['array'],
            'selectedEquipment.*' => [Rule::enum(Equipment::class)],
        ]);

        $equipmentEnums = collect($this->selectedEquipment)
            ->map(fn (string $val): ?Equipment => Equipment::tryFrom($val))
            ->filter()
            ->values()
            ->all();

        if ($this->locationIsDefault) {
            $user->trainingLocations()->update(['is_default' => false]);
        }

        if ($this->editingLocationId) {
            $location = $user->trainingLocations()->whereKey($this->editingLocationId)->first();
            if ($location) {
                $location->update([
                    'name' => $this->locationName,
                    'is_default' => $this->locationIsDefault,
                    'latitude' => $this->locationLatitude === '' ? null : $this->locationLatitude,
                    'longitude' => $this->locationLongitude === '' ? null : $this->locationLongitude,
                    'sort_order' => $this->locationSortOrder,
                    'equipment' => $equipmentEnums,
                ]);
            }
        } else {
            $user->trainingLocations()->create([
                'name' => $this->locationName,
                'is_default' => $this->locationIsDefault,
                'latitude' => $this->locationLatitude === '' ? null : $this->locationLatitude,
                'longitude' => $this->locationLongitude === '' ? null : $this->locationLongitude,
                'sort_order' => $this->locationSortOrder,
                'equipment' => $equipmentEnums,
            ]);
        }

        $this->showModal = false;
        $this->dispatch('training-location-saved');
    }
};
?>

<div>
    @if ($showModal)
        <flux:modal wire:model="showModal" class="w-full max-w-2xl">
            <form wire:submit="save" class="space-y-6">
                <div class="flex items-center justify-between border-b border-zinc-200 pb-4 dark:border-zinc-700">
                    <div>
                        <flux:heading size="lg">
                            {{ $editingLocationId ? __('Edit training location') : __('Add training location') }}
                        </flux:heading>
                        <flux:text variant="subtle">
                            {{ __('Modify details and available equipment for this location.') }}
                        </flux:text>
                    </div>
                    <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field class="sm:col-span-2">
                        <flux:label>{{ __('Name') }}</flux:label>
                        <flux:input wire:model="locationName" placeholder="{{ __('e.g., Home Gym, Commercial Gym') }}" />
                        <flux:error name="locationName" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Latitude') }}</flux:label>
                        <flux:input wire:model="locationLatitude" type="number" step="any" placeholder="41.3851" />
                        <flux:error name="locationLatitude" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Longitude') }}</flux:label>
                        <flux:input wire:model="locationLongitude" type="number" step="any" placeholder="2.1734" />
                        <flux:error name="locationLongitude" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Sort order') }}</flux:label>
                        <flux:input wire:model="locationSortOrder" type="number" min="0" />
                        <flux:error name="locationSortOrder" />
                    </flux:field>

                    <flux:field class="flex items-center pt-6">
                        <flux:checkbox wire:model="locationIsDefault" :label="__('Set as default location')" />
                        <flux:error name="locationIsDefault" />
                    </flux:field>
                </div>

                <div class="space-y-3 pt-2">
                    <flux:label class="font-medium">{{ __('Equipment') }}</flux:label>
                    <flux:error name="selectedEquipment" />

                    <div class="grid grid-cols-4 gap-2 max-h-72 overflow-y-auto p-1">
                        @foreach (\App\Enums\Equipment::cases() as $equipment)
                            @php
                                $isSelected = in_array($equipment->value, $selectedEquipment, true);
                            @endphp
                            <button
                                type="button"
                                wire:click="toggleEquipment('{{ $equipment->value }}')"
                                class="flex flex-col items-center justify-center p-3 rounded-lg border text-xs font-medium text-center transition cursor-pointer {{ $isSelected ? 'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' : 'border-zinc-200 bg-zinc-50 text-zinc-700 hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' }}"
                            >
                                <x-exercises.equipment-icon :equipment="$equipment->value" class="h-6 w-6 mb-1.5" />
                                <span class="truncate w-full">{{ str($equipment->value)->headline() }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>
