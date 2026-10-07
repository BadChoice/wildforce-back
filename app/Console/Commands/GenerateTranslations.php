<?php

namespace App\Console\Commands;

use App\Services\Translations\AndroidExporter;
use App\Services\Translations\GoogleSheetTranslations;
use App\Services\Translations\IOSExporter;
use App\Services\Translations\LaravelExporter;
use Illuminate\Console\Command;
use Throwable;

class GenerateTranslations extends Command
{
    protected $signature = 'app:translations {--ios : Generate the iOS XCStrings file} {--android : Generate the Android string resources} {--back : Generate the Laravel JSON translation files}';

    protected $description = 'Generate application translation files from Google Sheets';

    public function __construct(
        private GoogleSheetTranslations $googleSheetTranslations,
        private IOSExporter $iosExporter,
        private AndroidExporter $androidExporter,
        private LaravelExporter $laravelExporter,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->option('ios') && ! $this->option('android') && ! $this->option('back')) {
            $this->components->error('Select at least one platform: --ios, --android, or --back.');

            return self::FAILURE;
        }

        try {
            if ($this->option('ios')) {
                $translations = $this->googleSheetTranslations->download((string) config('translations.google_sheet_id'), ['app', 'exercise_catalog']);
                $path = (string) config('translations.ios_path');
                $this->ensurePathIsConfigured($path, 'TRANSLATIONS_IOS_PATH');
                $this->iosExporter->export($translations, $path);
                $this->components->info("Generated iOS translations at {$path}.");
            }

            if ($this->option('android')) {
                $translations = $this->googleSheetTranslations->download((string) config('translations.google_sheet_id'), ['app']);
                $path = (string) config('translations.android_path');
                $this->ensurePathIsConfigured($path, 'TRANSLATIONS_ANDROID_PATH');
                $this->androidExporter->export($translations, $path);
                $this->components->info("Generated Android translations at {$path}.");
            }

            if ($this->option('back')) {
                $translations = $this->googleSheetTranslations->download((string) config('translations.google_sheet_id'), ['back', 'exercise_catalog']);
                $path = lang_path();
                $this->laravelExporter->export($translations, $path);
                $this->components->info("Generated Laravel translations at {$path}.");
            }
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function ensurePathIsConfigured(string $path, string $variable): void
    {
        if ($path === '') {
            throw new \RuntimeException("The {$variable} environment variable is not configured.");
        }
    }
}
