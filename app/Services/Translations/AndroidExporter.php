<?php

namespace App\Services\Translations;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AndroidExporter
{
    public function __construct(private Filesystem $files) {}

    /**
     * @param  list<array{key: string, description: ?string, translations: array{en: string, es: string, ca: string}}>  $translations
     */
    public function export(array $translations, string $path): void
    {
        foreach (['en' => 'values', 'es' => 'values-es', 'ca' => 'values-ca'] as $locale => $directory) {
            $this->files->ensureDirectoryExists("{$path}/{$directory}");
            $this->files->put("{$path}/{$directory}/strings.xml", $this->xml($translations, $locale));
        }
    }

    /**
     * @param  list<array{key: string, description: ?string, translations: array{en: string, es: string, ca: string}}>  $translations
     */
    private function xml(array $translations, string $locale): string
    {
        $strings = [];
        $resourceNames = [];

        foreach ($translations as $translation) {
            $resourceName = $this->resourceName($translation['key']);

            if (array_key_exists($resourceName, $resourceNames)) {
                throw new InvalidArgumentException("The Android resource name '{$resourceName}' is duplicated.");
            }

            $resourceNames[$resourceName] = true;
            $comment = $translation['description'] === null
                ? ''
                : '    <!-- '.str_replace('--', '—', $translation['description'])." -->\n";
            $strings[] = $comment.'    <string name="'.$resourceName.'" formatted="false">'
                .$this->escape($translation['translations'][$locale]).'</string>';
        }

        return "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<resources>\n"
            .implode("\n", $strings)
            ."\n</resources>\n";
    }

    private function resourceName(string $key): string
    {
        $resourceName = Str::of($key)
            ->replace(['-', ' '], '_')
            ->lower()
            ->replaceMatches('/[^a-z0-9_]/', '_')
            ->toString();

        if ($resourceName === '' || ctype_digit($resourceName[0])) {
            throw new InvalidArgumentException("The key '{$key}' cannot be converted to an Android resource name.");
        }

        return $resourceName;
    }

    private function escape(string $value): string
    {
        return str_replace(
            ['\\', "\n", "'", '"'],
            ['\\\\', '\\n', "\\'", '\\"'],
            htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
        );
    }
}
