@props(['muscle'])

@php
$imageUrl = rtrim((string) config('exercise_catalog.image_base_url'), '/').'/muscleGroups/muscle_group_'.$muscle.'.png';
@endphp

<flux:tooltip content="{{__($muscle)}}">
    <img {{ $attributes->merge(['class' => 'h-8 w-8']) }} alt="{{$muscle}}" src="{{$imageUrl}}" />
</flux:tooltip>
