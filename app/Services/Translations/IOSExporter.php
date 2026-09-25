<?php

namespace App\Services\Translations;

use Illuminate\Filesystem\Filesystem;

class IOSExporter
{
    public function __construct(private Filesystem $files) {}

    /**
     * @param  list<array{key: string, description: ?string, translations: array<string, string>}>  $translations
     */
    public function export(array $translations, string $path): void
    {
        $strings = [];

        foreach ($translations as $translation) {
            $strings[$translation['key']] = array_filter([
                'comment' => $translation['description'],
                'localizations' => collect($translation['translations'])
                    ->mapWithKeys(fn (string $value, string $locale): array => [$locale => [
                        'stringUnit' => [
                            'state' => 'translated',
                            'value' => $value,
                        ],
                    ]])
                    ->all(),
            ]);
        }

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, json_encode([
            'sourceLanguage' => 'en',
            'strings' => $strings,
            'version' => '1.0',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL);
    }
}
