@props(['title', 'description'])

<section class="flex min-h-56 flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 p-6 text-center dark:border-zinc-700">
    <flux:heading size="sm">{{ $title }}</flux:heading>
    <flux:text variant="subtle" class="mt-2 max-w-sm">{{ $description }}</flux:text>
</section>
