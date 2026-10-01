@props(['instructions', 'prompt', 'schema', 'error', 'title' => __('Next plan prompt')])

<flux:modal {{ $attributes->merge(['class' => 'w-full max-w-6xl']) }}>
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">{{ $title }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Preview of the exact prompt and response schema. No AI request is made.') }}</flux:text>
        </div>

        @if ($error !== null)
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                {{ $error }}
            </div>
        @else
            <section class="space-y-2">
                <flux:heading size="sm">{{ __('Agent instructions') }}</flux:heading>
                <div class="prose prose-sm max-h-80 max-w-none overflow-auto rounded-xl border border-neutral-200 bg-neutral-50 p-5 dark:prose-invert dark:border-neutral-700 dark:bg-neutral-900">
                    {!! \Illuminate\Support\Str::markdown($instructions, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                </div>
            </section>

            <section class="space-y-2">
                <flux:heading size="sm">{{ __('Prompt') }}</flux:heading>
                <div class="prose prose-sm max-h-[32rem] max-w-none overflow-auto rounded-xl border border-neutral-200 bg-neutral-50 p-5 dark:prose-invert dark:border-neutral-700 dark:bg-neutral-900">
                    {!! \Illuminate\Support\Str::markdown($prompt, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                </div>
            </section>

            <section class="space-y-2">
                <flux:heading size="sm">{{ __('Response schema') }}</flux:heading>
                <pre class="max-h-[32rem] overflow-auto rounded-xl border border-neutral-200 bg-neutral-950 p-5 text-xs leading-5 text-neutral-100 dark:border-neutral-700"><code>{{ $schema }}</code></pre>
            </section>
        @endif

        <div class="flex justify-end">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Close') }}</flux:button>
            </flux:modal.close>
        </div>
    </div>
</flux:modal>
