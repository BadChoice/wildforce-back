<?php

use App\Models\WorkoutPlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public WorkoutPlan $workoutPlan;


    public function mount(WorkoutPlan $workoutPlan): void
    {
        $this->workoutPlan = $workoutPlan;
    }

};
?>


<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
    <div class="space-y-4">
        {{ $this->workoutPlan->name }}
    </div>

    <div class="grid grid-cols-[32px_repeat(7,minmax(0,1fr))] gap-2">

        @foreach([1, 2, 3, 4] as $week)

            {{-- Week label --}}
            <div class="group flex flex-col items-center justify-between py-4">
                <div class="flex items-center gap-2 -rotate-90 whitespace-nowrap text-xs text-neutral-300 uppercase mt-2">
                    <span>{{ __("Week") }} {{ $week }}</span>
                </div>
                <div class="opacity-0 group-hover:opacity-100 transition-opacity">
                    <flux:button variant="subtle" size="xs" icon="calendar-days" />
                </div>
            </div>

            {{-- Week days --}}
            @foreach([1, 2, 3, 4, 5, 6, 7] as $day)
                <div class="bg-neutral-50  rounded-xl p-2 h-48">
                    <div class="flex flex-col pb-2 font-bold text-neutral-500">
                        <span class="text-xs"> {{ __("DAY") }} {{ (7 * ($week-1) + $day) }}</span>
                    </div>

                    <div size="xs" class="text-xs p-1 bg-white border rounded-lg" >
                        <div class="flex justify-between items-center">
                            <div class="font-bold p-1">Workout 1</div>
                            <flux:button size="xs" variant="subtle" icon="ellipsis-horizontal" />
                        </div>
                        <flux:separator />
                        <div class="text-xs text-neutral-500">1 exercises</div>
                    </div>
                </div>
            @endforeach

        @endforeach

    </div>
</div>
