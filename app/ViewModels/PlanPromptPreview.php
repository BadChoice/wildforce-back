<?php

namespace App\ViewModels;

use Livewire\Wireable;

final class PlanPromptPreview implements Wireable
{
    public function __construct(
        public bool $isOpen = false,
        public string $title = 'Next plan prompt',
        public string $instructions = '',
        public string $prompt = '',
        public string $schema = '',
        public ?string $error = null,
    ) {}

    /** @param array{instructions: string, prompt: string, schema: string} $preview */
    public function show(string $title, array $preview): void
    {
        $this->isOpen = true;
        $this->title = $title;
        $this->instructions = $preview['instructions'];
        $this->prompt = $preview['prompt'];
        $this->schema = $preview['schema'];
        $this->error = null;
    }

    public function showError(string $title, string $error): void
    {
        $this->isOpen = true;
        $this->title = $title;
        $this->instructions = '';
        $this->prompt = '';
        $this->schema = '';
        $this->error = $error;
    }

    public function reset(): void
    {
        $this->isOpen = false;
        $this->title = 'Next plan prompt';
        $this->instructions = '';
        $this->prompt = '';
        $this->schema = '';
        $this->error = null;
    }

    /** @return array{isOpen: bool, title: string, instructions: string, prompt: string, schema: string, error: string|null} */
    public function toLivewire(): array
    {
        return [
            'isOpen' => $this->isOpen,
            'title' => $this->title,
            'instructions' => $this->instructions,
            'prompt' => $this->prompt,
            'schema' => $this->schema,
            'error' => $this->error,
        ];
    }

    /** @param array{isOpen: bool, title: string, instructions: string, prompt: string, schema: string, error: string|null} $value */
    public static function fromLivewire(mixed $value): static
    {
        return new self(
            isOpen: $value['isOpen'],
            title: $value['title'],
            instructions: $value['instructions'],
            prompt: $value['prompt'],
            schema: $value['schema'],
            error: $value['error'],
        );
    }
}
