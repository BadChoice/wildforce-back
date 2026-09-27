@props(['user', 'size' => null])

@php
    $imageUrl = rtrim((string) config('exercise_catalog.image_base_url'), '/').'/avatars/'.rawurlencode($user->id).'.png';
@endphp

<span {{ $attributes->class('relative inline-flex shrink-0') }}>
    @if ($size)
        <flux:avatar :name="$user->name" :initials="$user->initials()" :size="$size" aria-hidden="true" />
    @else
        <flux:avatar :name="$user->name" :initials="$user->initials()" aria-hidden="true" />
    @endif
    <img
        src="{{ $imageUrl }}"
        alt="{{ $user->name }}"
        class="absolute inset-0 size-full rounded-full object-cover"
        loading="lazy"
        onerror="this.remove()"
    />
</span>
