<?php

use App\Services\Translations\AndroidExporter;
use App\Services\Translations\GoogleSheetTranslations;
use App\Services\Translations\IOSExporter;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('downloads translations from a public Google Sheet workbook', function () {
    Http::preventStrayRequests();
    Http::fake([
        'docs.google.com/spreadsheets/d/sheet-id/export*' => Http::response(translationWorkbook()),
    ]);

    $translations = app(GoogleSheetTranslations::class)->download('sheet-id');

    expect($translations)->toBe([
        [
            'key' => 'welcome.title',
            'description' => 'Home screen title',
            'translations' => [
                'en' => 'Welcome & enjoy',
                'es' => 'Bienvenido',
                'ca' => 'Benvingut',
            ],
        ],
    ]);
});

test('exports iOS and Android translation resources', function () {
    $directory = sys_get_temp_dir().'/translations-'.uniqid();
    $translations = [[
        'key' => 'welcome.title',
        'description' => 'Home screen title',
        'translations' => [
            'en' => 'Welcome & enjoy',
            'es' => 'Bienvenido',
            'ca' => 'Benvingut',
        ],
    ]];

    app(IOSExporter::class)->export($translations, "{$directory}/Localizable.xcstrings");
    app(AndroidExporter::class)->export($translations, "{$directory}/res");

    expect(json_decode(file_get_contents("{$directory}/Localizable.xcstrings"), true, flags: JSON_THROW_ON_ERROR))
        ->toMatchArray([
            'sourceLanguage' => 'en',
            'strings' => [
                'welcome.title' => [
                    'comment' => 'Home screen title',
                    'localizations' => [
                        'ca' => ['stringUnit' => ['state' => 'translated', 'value' => 'Benvingut']],
                        'en' => ['stringUnit' => ['state' => 'translated', 'value' => 'Welcome & enjoy']],
                        'es' => ['stringUnit' => ['state' => 'translated', 'value' => 'Bienvenido']],
                    ],
                ],
            ],
        ])
        ->and(file_get_contents("{$directory}/res/values/strings.xml"))
        ->toContain('<string name="welcome_title" formatted="false">Welcome &amp; enjoy</string>')
        ->and(file_get_contents("{$directory}/res/values-es/strings.xml"))
        ->toContain('<string name="welcome_title" formatted="false">Bienvenido</string>')
        ->and(file_get_contents("{$directory}/res/values-ca/strings.xml"))
        ->toContain('<string name="welcome_title" formatted="false">Benvingut</string>');
});

test('generates the requested platform through the Artisan command', function () {
    $directory = sys_get_temp_dir().'/translations-command-'.uniqid();
    config()->set('translations.google_sheet_id', 'sheet-id');
    config()->set('translations.ios_path', "{$directory}/Localizable.xcstrings");
    Http::preventStrayRequests();
    Http::fake([
        'docs.google.com/spreadsheets/d/sheet-id/export*' => Http::response(translationWorkbook()),
    ]);

    $this->artisan('app:translations', ['--ios' => true])->assertSuccessful();

    expect(file_get_contents("{$directory}/Localizable.xcstrings"))
        ->toContain('"welcome.title"');
});

function translationWorkbook(): string
{
    $path = tempnam(sys_get_temp_dir(), 'translation-workbook-');
    $archive = new ZipArchive;
    $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $archive->addFromString('xl/sharedStrings.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <si><t>key</t></si><si><t>description</t></si><si><t>en</t></si><si><t>es</t></si><si><t>ca</t></si>
    <si><t>welcome.title</t></si><si><t>Home screen title</t></si><si><t>Welcome &amp; enjoy</t></si><si><t>Bienvenido</t></si><si><t>Benvingut</t></si>
</sst>
XML);
    $archive->addFromString('xl/worksheets/sheet1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>
    <row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c><c r="D1" t="s"><v>3</v></c><c r="E1" t="s"><v>4</v></c></row>
    <row r="2"><c r="A2" t="s"><v>5</v></c><c r="B2" t="s"><v>6</v></c><c r="C2" t="s"><v>7</v></c><c r="D2" t="s"><v>8</v></c><c r="E2" t="s"><v>9</v></c></row>
</sheetData></worksheet>
XML);
    $archive->close();

    $contents = file_get_contents($path);
    unlink($path);

    return $contents;
}
