<?php

use App\Enums\BodyMetricRange;
use App\Models\User;
use App\Repositories\BodyMetricsRepository;
use App\ValueObjects\BodyMetrics\BodyMetricSeries;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public User $user;

    public BodyMetricRange $range = BodyMetricRange::ThreeMonths;

    public function mount(User $user): void
    {
        abort_unless(auth()->user()?->canViewUserData($user->getKey()), 403);

        $this->user = $user;
    }

    /**
     * @return Collection<string, BodyMetricSeries>
     */
    #[Computed]
    public function series(): Collection
    {
        return app(BodyMetricsRepository::class)->history($this->user, $this->range);
    }
};
?>

<section aria-labelledby="body-metrics-heading">
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <flux:heading id="body-metrics-heading" size="sm">{{ __('Body metrics') }}</flux:heading>
            <flux:text variant="subtle">{{ __('Track each measurement over time.') }}</flux:text>
        </div>

        @if ($this->series->isNotEmpty())
            <flux:radio.group wire:model.live="range" variant="segmented" size="sm" :label="__('Range')" label:sr-only>
                @foreach (BodyMetricRange::cases() as $rangeOption)
                    <flux:radio :value="$rangeOption->value" :label="$rangeOption->label()" wire:key="body-metric-range-{{ $rangeOption->value }}" />
                @endforeach
            </flux:radio.group>
        @endif
    </div>

    @if ($this->series->isEmpty())
        <x-dashboard.placeholder-tab :title="__('No body metrics yet')" :description="__('Measurements recorded by this user will appear here.')" />
    @else
        <div class="grid gap-4 sm:grid-cols-2" wire:loading.class="opacity-60" wire:target="range">
            @foreach ($this->series as $type => $series)
                <x-dashboard.body-metric-chart :series="$series" wire:key="body-metric-{{ $user->id }}-{{ $type }}" />
            @endforeach
        </div>
    @endif
</section>
