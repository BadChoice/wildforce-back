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

    expect($translations)->toHaveCount(1)
        ->and($translations[0]['key'])->toBe('welcome.title')
        ->and($translations[0]['description'])->toBe('Home screen title')
        ->and($translations[0]['translations'])->toHaveKeys(array_keys(config('translations.locales')))
        ->and($translations[0]['translations']['en'])->toBe('Welcome & enjoy')
        ->and($translations[0]['translations']['de'])->toBe('Willkommen')
        ->and($translations[0]['translations']['zh-Hans'])->toBe('欢迎');
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
            'de' => 'Willkommen',
            'fr' => 'Bienvenue',
            'it' => 'Benvenuto',
            'ja' => 'ようこそ',
            'nl' => 'Welkom',
            'pt' => 'Bem-vindo',
            'zh-Hans' => '欢迎',
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
                        'de' => ['stringUnit' => ['state' => 'translated', 'value' => 'Willkommen']],
                        'en' => ['stringUnit' => ['state' => 'translated', 'value' => 'Welcome & enjoy']],
                        'es' => ['stringUnit' => ['state' => 'translated', 'value' => 'Bienvenido']],
                        'fr' => ['stringUnit' => ['state' => 'translated', 'value' => 'Bienvenue']],
                        'it' => ['stringUnit' => ['state' => 'translated', 'value' => 'Benvenuto']],
                        'ja' => ['stringUnit' => ['state' => 'translated', 'value' => 'ようこそ']],
                        'nl' => ['stringUnit' => ['state' => 'translated', 'value' => 'Welkom']],
                        'pt' => ['stringUnit' => ['state' => 'translated', 'value' => 'Bem-vindo']],
                        'zh-Hans' => ['stringUnit' => ['state' => 'translated', 'value' => '欢迎']],
                    ],
                ],
            ],
        ])
        ->and(file_get_contents("{$directory}/res/values/strings.xml"))
        ->toContain('<string name="welcome_title" formatted="false">Welcome &amp; enjoy</string>')
        ->and(file_get_contents("{$directory}/res/values-es/strings.xml"))
        ->toContain('<string name="welcome_title" formatted="false">Bienvenido</string>')
        ->and(file_get_contents("{$directory}/res/values-ca/strings.xml"))
        ->toContain('<string name="welcome_title" formatted="false">Benvingut</string>')
        ->and(file_get_contents("{$directory}/res/values-de/strings.xml"))
        ->toContain('<string name="welcome_title" formatted="false">Willkommen</string>')
        ->and(file_get_contents("{$directory}/res/values-zh-rCN/strings.xml"))
        ->toContain('<string name="welcome_title" formatted="false">欢迎</string>');
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
    $locales = array_keys(config('translations.locales'));
    $values = [
        'key', 'description', ...$locales,
        'welcome.title', 'Home screen title', 'Welcome & enjoy', 'Bienvenido', 'Benvingut', 'Willkommen',
        'Bienvenue', 'Benvenuto', 'ようこそ', 'Welkom', 'Bem-vindo', '欢迎',
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
    $archive->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1">'.$columns(1, 0).'</row><row r="2">'.$columns(2, count($locales) + 2).'</row></sheetData></worksheet>');
    $archive->close();

    $contents = file_get_contents($path);
    unlink($path);

    return $contents;
}
