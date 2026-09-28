@props(['label', 'type', 'ids', 'emptyLabel' => null])

<x-ui.reference-box :attributes="$attributes" :label="$label">
    @forelse ($ids as $id)
        <x-exercises.reference-icon :id="$id" :type="$type" />
    @empty
        @if ($emptyLabel)
            <span>{{ $emptyLabel }}</span>
        @endif
    @endforelse
</x-ui.reference-box>
