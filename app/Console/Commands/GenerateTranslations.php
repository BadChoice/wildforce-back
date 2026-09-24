<?php

namespace App\Console\Commands;

use App\Services\Translations\AndroidExporter;
use App\Services\Translations\GoogleSheetTranslations;
use App\Services\Translations\IOSExporter;
use Illuminate\Console\Command;
use Throwable;

class GenerateTranslations extends Command
{
    protected $signature = 'app:translations {--ios : Generate the iOS XCStrings file} {--android : Generate the Android string resources}';

    protected $description = 'Generate mobile translation files from Google Sheets';

    public function __construct(
        private GoogleSheetTranslations $googleSheetTranslations,
        private IOSExporter $iosExporter,
        private AndroidExporter $androidExporter,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->option('ios') && ! $this->option('android')) {
            $this->components->error('Select at least one platform: --ios or --android.');

            return self::FAILURE;
        }

        try {
            $translations = $this->googleSheetTranslations->download((string) config('translations.google_sheet_id'));

            if ($this->option('ios')) {
                $path = (string) config('translations.ios_path');
                $this->ensurePathIsConfigured($path, 'TRANSLATIONS_IOS_PATH');
                $this->iosExporter->export($translations, $path);
                $this->components->info("Generated iOS translations at {$path}.");
            }

            if ($this->option('android')) {
                $path = (string) config('translations.android_path');
                $this->ensurePathIsConfigured($path, 'TRANSLATIONS_ANDROID_PATH');
                $this->androidExporter->export($translations, $path);
                $this->components->info("Generated Android translations at {$path}.");
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
