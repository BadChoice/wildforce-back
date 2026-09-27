@props(['label', 'type', 'ids', 'emptyLabel' => null])

<div {{ $attributes->class('rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800/60') }}>
    <dt class="text-xs font-medium tracking-wide text-zinc-500 dark:text-zinc-400">{{ $label }}</dt>
    <dd class="mt-2 flex flex-wrap gap-x-3 gap-y-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">
        @forelse ($ids as $id)
            <x-exercises.reference-icon :id="$id" :type="$type" />
        @empty
            @if ($emptyLabel)
                <span>{{ $emptyLabel }}</span>
            @endif
        @endforelse
    </dd>
</div>
