@props(['exercise'])

<div class="flex gap-1">
    @foreach($exercise['primaryMuscles'] ?? [] as $muscleGroup)
        <x-exercises.muscle-icon :muscle="$muscleGroup" />
    @endforeach
    <flux:separator vertical="true" />
    @foreach($exercise['secondaryMuscles'] ?? [] as $muscleGroup)
        <x-exercises.muscle-icon :muscle="$muscleGroup" />
    @endforeach
</div>
