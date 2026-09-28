<?php

use App\Services\ExerciseCatalog\ExerciseCatalog;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $search = '';

    public function plans(): Collection
    {
        return auth()->user()->workoutPlans()->limit(20)->get();
    }
};
?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
    <div
        class="overflow-hidden rounded-xl border p-2 border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-900">
        <flux:table>
            @foreach($this->plans() as $plan)
                <flux:table.row>
                    <flux:table.cell>
                        <b>{{ $plan->name }}</b>
                            @foreach($plan->workoutDays as $workout)
                                &nbsp;&nbsp;&nbsp;{{ $workout->title }}
                            <x-workout-plans.planned-exercises-table :workout="$workout" />
                        @endforeach
                        </flux:table>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table>
    </div>
</div>
