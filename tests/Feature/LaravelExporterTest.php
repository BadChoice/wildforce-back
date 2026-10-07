<?php

use App\Services\Translations\LaravelExporter;
use Illuminate\Support\Facades\Http;

test('exports Laravel JSON translation files', function () {
    $directory = sys_get_temp_dir().'/laravel-translations-'.uniqid();
    $translations = [[
        'key' => 'Welcome :name',
        'description' => 'Dashboard greeting',
        'translations' => [
            'en' => 'Welcome :name',
            'es' => 'Bienvenido :name',
            'ca' => 'Benvingut :name',
            'de' => 'Willkommen :name',
            'fr' => 'Bienvenue :name',
            'it' => 'Benvenuto :name',
            'ja' => 'ようこそ :name',
            'nl' => 'Welkom :name',
            'pt' => 'Bem-vindo :name',
            'zh-Hans' => '欢迎 :name',
        ],
    ]];

    app(LaravelExporter::class)->export($translations, $directory);

    expect(json_decode(file_get_contents("{$directory}/en.json"), true, flags: JSON_THROW_ON_ERROR))
        ->toBe(['Welcome :name' => 'Welcome :name'])
        ->and(json_decode(file_get_contents("{$directory}/ca.json"), true, flags: JSON_THROW_ON_ERROR))
        ->toBe(['Welcome :name' => 'Benvingut :name'])
        ->and(json_decode(file_get_contents("{$directory}/zh-Hans.json"), true, flags: JSON_THROW_ON_ERROR))
        ->toBe(['Welcome :name' => '欢迎 :name']);
});

test('generates back translations through the Artisan command', function () {
    $directory = sys_get_temp_dir().'/laravel-translations-command-'.uniqid();
    config()->set('translations.google_sheet_id', 'sheet-id');
    app()->useLangPath($directory);
    Http::preventStrayRequests();
    Http::fake([
        'docs.google.com/spreadsheets/d/sheet-id/export*' => Http::response(backTranslationWorkbook()),
    ]);

    $this->artisan('app:translations', ['--back' => true])->assertSuccessful();

    expect(json_decode(file_get_contents("{$directory}/en.json"), true, flags: JSON_THROW_ON_ERROR))
        ->toBe([
            'Dashboard' => 'Dashboard',
            'Walking' => 'Walking',
        ])
        ->and(json_decode(file_get_contents("{$directory}/es.json"), true, flags: JSON_THROW_ON_ERROR))
        ->toBe([
            'Dashboard' => 'Panel',
            'Walking' => 'Caminar',
        ]);
});

function backTranslationWorkbook(): string
{
    $path = tempnam(sys_get_temp_dir(), 'translation-workbook-');
    $archive = new ZipArchive;
    $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $locales = array_keys(config('translations.locales'));
    $values = [
        'key', 'description', ...$locales,
        'Dashboard', 'Dashboard title', 'Dashboard', 'Panel', 'Tauler', 'Dashboard', 'Tableau de bord', 'Dashboard', 'ダッシュボード', 'Dashboard', 'Dashboard', '仪表板',
        'Walking', 'Exercise name', 'Walking', 'Caminar', 'Caminar', 'Gehen', 'Marche', 'Camminare', 'ウォーキング', 'Wandelen', 'Caminhada', '步行',
    ];
    $sharedStrings = implode('', array_map(
        fn (string $value): string => '<si><t>'.htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></si>',
        $values,
    ));
    $columns = fn (int $row, int $offset): string => implode('', array_map(
        fn (int $index): string => '<c r="'.chr(65 + $index).$row.'" t="s"><v>'.($offset + $index).'</v></c>',
        range(0, count($locales) + 1),
    ));

    $archive->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.$sharedStrings.'</sst>');
    $archive->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="back" sheetId="1" r:id="rId1"/><sheet name="exercise_catalog" sheetId="2" r:id="rId2"/></sheets></workbook>');
    $archive->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Target="worksheets/sheet2.xml"/></Relationships>');
    $archive->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1">'.$columns(1, 0).'</row><row r="2">'.$columns(2, count($locales) + 2).'</row></sheetData></worksheet>');
    $archive->addFromString('xl/worksheets/sheet2.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1">'.$columns(1, 0).'</row><row r="2">'.$columns(2, (count($locales) + 2) * 2).'</row></sheetData></worksheet>');
    $archive->close();

    $contents = file_get_contents($path);
    unlink($path);

    return $contents;
}
