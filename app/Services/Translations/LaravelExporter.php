<?php

namespace App\Services\Translations;

use Illuminate\Filesystem\Filesystem;

class LaravelExporter
{
    public function __construct(private Filesystem $files) {}

    /**
     * @param  list<array{key: string, description: ?string, translations: array<string, string>}>  $translations
     */
    public function export(array $translations, string $path): void
    {
        $this->files->ensureDirectoryExists($path);

        foreach (array_keys(config('translations.locales')) as $locale) {
            $strings = [];

            foreach ($translations as $translation) {
                $strings[$translation['key']] = $translation['translations'][$locale] ?? '';
            }

            $this->files->put(
                "{$path}/{$locale}.json",
                json_encode($strings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL,
            );
        }
    }
}
