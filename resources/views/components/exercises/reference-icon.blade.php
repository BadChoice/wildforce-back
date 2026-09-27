@props(['id', 'type', 'label' => null, 'showName' => true])

@php
    $referenceType = App\Enums\ExerciseCatalogReferenceType::tryFrom($type);
    $icon = $referenceType?->icon($id) ?? 'circle-alert';
    $label ??= str($id)->headline()->toString();
@endphp

<span {{ $attributes->class('inline-flex items-center gap-1.5') }} @if (! $showName) role="img" aria-label="{{ $label }}" @endif>
    <flux:icon :name="$icon" variant="micro" class="text-zinc-500 dark:text-zinc-400" />

    @if ($showName)
        <span>{{ $label }}</span>
    @endif
</span>
