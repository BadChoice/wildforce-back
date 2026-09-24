<?php

namespace App\Services\Translations;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class GoogleSheetTranslations
{
    /**
     * Download the first worksheet of a public Google Sheet as translations.
     *
     * @return list<array{key: string, description: ?string, translations: array{en: string, es: string, ca: string}}>
     */
    public function download(string $spreadsheetId): array
    {
        if ($spreadsheetId === '') {
            throw new RuntimeException('The Google Sheets translation ID is not configured.');
        }

        $contents = Http::connectTimeout(3)
            ->timeout(30)
            ->get("https://docs.google.com/spreadsheets/d/{$spreadsheetId}/export", ['format' => 'xlsx'])
            ->throw()
            ->body();

        return $this->parseWorkbook($contents);
    }

    /**
     * @return list<array{key: string, description: ?string, translations: array{en: string, es: string, ca: string}}>
     */
    private function parseWorkbook(string $contents): array
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'translations-');

        if ($temporaryFile === false) {
            throw new RuntimeException('Unable to create a temporary translations file.');
        }

        try {
            file_put_contents($temporaryFile, $contents);

            $archive = new ZipArchive;

            if ($archive->open($temporaryFile) !== true) {
                throw new RuntimeException('Google Sheets did not return a valid XLSX file.');
            }

            try {
                $sharedStrings = $this->sharedStrings($archive);
                $worksheet = $archive->getFromName('xl/worksheets/sheet1.xml');

                if ($worksheet === false) {
                    throw new RuntimeException('The XLSX file does not contain a first worksheet.');
                }

                return $this->translationsFromWorksheet($worksheet, $sharedStrings);
            } finally {
                $archive->close();
            }
        } finally {
            unlink($temporaryFile);
        }
    }

    /**
     * @return list<string>
     */
    private function sharedStrings(ZipArchive $archive): array
    {
        $contents = $archive->getFromName('xl/sharedStrings.xml');

        if ($contents === false) {
            return [];
        }

        $xml = $this->xml($contents, 'shared strings');
        $xml->registerXPathNamespace('spreadsheet', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        return array_map(
            function (SimpleXMLElement $item): string {
                $item->registerXPathNamespace('spreadsheet', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

                return implode('', array_map(
                    fn (SimpleXMLElement $text): string => (string) $text,
                    $item->xpath('.//spreadsheet:t') ?: [],
                ));
            },
            $xml->xpath('//spreadsheet:si') ?: [],
        );
    }

    /**
     * @param  list<string>  $sharedStrings
     * @return list<array{key: string, description: ?string, translations: array{en: string, es: string, ca: string}}>
     */
    private function translationsFromWorksheet(string $contents, array $sharedStrings): array
    {
        $xml = $this->xml($contents, 'worksheet');
        $xml->registerXPathNamespace('spreadsheet', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = $xml->xpath('//spreadsheet:sheetData/spreadsheet:row') ?: [];

        if ($rows === []) {
            throw new RuntimeException('The Google Sheet is empty.');
        }

        $header = $this->rowValues($rows[0], $sharedStrings);
        $columns = array_flip(array_map(fn (string $value): string => mb_strtolower(trim($value)), $header));

        foreach (['key', 'description', 'en', 'es', 'ca'] as $column) {
            if (! array_key_exists($column, $columns)) {
                throw new RuntimeException("The Google Sheet is missing the required '{$column}' column.");
            }
        }

        $translations = [];

        foreach (array_slice($rows, 1) as $row) {
            $values = $this->rowValues($row, $sharedStrings);
            $key = trim($values[$columns['key']] ?? '');

            if ($key === '') {
                if (implode('', $values) === '') {
                    continue;
                }

                throw new RuntimeException('Every non-empty Google Sheet row must have a key.');
            }

            if (array_key_exists($key, $translations)) {
                throw new RuntimeException("The Google Sheet contains the duplicate key '{$key}'.");
            }

            $description = trim($values[$columns['description']] ?? '');
            $translations[$key] = [
                'key' => $key,
                'description' => $description === '' ? null : $description,
                'translations' => [
                    'en' => $values[$columns['en']] ?? '',
                    'es' => $values[$columns['es']] ?? '',
                    'ca' => $values[$columns['ca']] ?? '',
                ],
            ];
        }

        return array_values($translations);
    }

    /**
     * @param  list<string>  $sharedStrings
     * @return array<int, string>
     */
    private function rowValues(SimpleXMLElement $row, array $sharedStrings): array
    {
        $row->registerXPathNamespace('spreadsheet', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $values = [];

        foreach ($row->xpath('./spreadsheet:c') ?: [] as $cell) {
            $cell->registerXPathNamespace('spreadsheet', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $attributes = $cell->attributes();
            $column = $this->columnIndex((string) $attributes['r']);
            $type = (string) $attributes['t'];
            $value = (string) ($cell->v ?? '');

            $values[$column] = match ($type) {
                's' => $sharedStrings[(int) $value] ?? '',
                'inlineStr' => implode('', array_map(
                    fn (SimpleXMLElement $text): string => (string) $text,
                    $cell->xpath('.//spreadsheet:t') ?: [],
                )),
                default => $value,
            };
        }

        return $values;
    }

    private function columnIndex(string $cellReference): int
    {
        preg_match('/^[A-Z]+/', $cellReference, $matches);
        $column = $matches[0] ?? '';
        $index = 0;

        foreach (str_split($column) as $character) {
            $index = ($index * 26) + (ord($character) - 64);
        }

        return $index - 1;
    }

    private function xml(string $contents, string $part): SimpleXMLElement
    {
        $xml = simplexml_load_string($contents, SimpleXMLElement::class, LIBXML_NONET);

        if ($xml === false) {
            throw new RuntimeException("The XLSX {$part} XML is invalid.");
        }

        return $xml;
    }
}
