@props(['id', 'type', 'label' => null, 'showName' => true])

@php
    $directory = $type === 'muscle-group' ? 'muscleGroups' : 'equipment';
    $filename = $type === 'muscle-group' ? 'muscle_group_'.$id : $id;
    $label ??= str($id)->headline()->toString();
    $imageUrl = rtrim((string) config('exercise_catalog.image_base_url'), '/').'/'.$directory.'/'.rawurlencode($filename).'.png';
@endphp

<span {{ $attributes->class('inline-flex items-center gap-1.5') }}>
    <img
        src="{{ $imageUrl }}"
        alt="{{ $showName ? '' : $label }}"
        @if ($showName) aria-hidden="true" @endif
        class="size-5 shrink-0 rounded-sm object-contain"
        loading="lazy"
    />

    @if ($showName)
        <span>{{ $label }}</span>
    @endif
</span>
