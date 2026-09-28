@props(['equipment'])

@php
$imageUrl = rtrim((string) config('exercise_catalog.image_base_url'), '/').'/equipment/'.$equipment.'.png';
@endphp

<flux:tooltip content="{{__($equipment)}}">
    <img {{ $attributes->class('h-8 w-8') }} alt="{{$equipment}}" src="{{$imageUrl}}" />
</flux:tooltip>
