<?php

namespace App\Services\Nutrition;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OpenFoodFactsService
{
    /**
     * Submit a user-provided product contribution to Open Food Facts.
     *
     * @param  array{barcode: string, name: string, brands: string, macros_per_100g: array{calories: float|int, protein: float|int, carbs: float|int, fat: float|int}, serving_size: string|null, nutriscore: string|null, front_image: UploadedFile, nutrition_image: UploadedFile}  $contribution
     * @return array{code: string, status: string}
     */
    public function contribute(array $contribution): array
    {
        $credentials = $this->credentials();
        try {
            $response = Http::acceptJson()
                ->withHeaders(['User-Agent' => (string) config('services.open_food_facts.user_agent')])
                ->connectTimeout(3)
                ->timeout(15)
                ->attach('imgupload_front', $contribution['front_image']->get(), $contribution['front_image']->getClientOriginalName())
                ->attach('imgupload_nutrition', $contribution['nutrition_image']->get(), $contribution['nutrition_image']->getClientOriginalName())
                ->post($this->endpoint(), array_filter([
                    'code' => $contribution['barcode'],
                    'product_name' => $contribution['name'],
                    'brands' => $contribution['brands'],
                    'nutriments_energy-kcal_100g' => $contribution['macros_per_100g']['calories'],
                    'nutriments_proteins_100g' => $contribution['macros_per_100g']['protein'],
                    'nutriments_carbohydrates_100g' => $contribution['macros_per_100g']['carbs'],
                    'nutriments_fat_100g' => $contribution['macros_per_100g']['fat'],
                    'serving_size' => $contribution['serving_size'],
                    'nutrition_grades' => $contribution['nutriscore'],
                    'user_id' => $credentials['user_id'],
                    'password' => $credentials['password'],
                    'app_name' => config('services.open_food_facts.app_name'),
                    'app_version' => config('services.open_food_facts.app_version'),
                ], fn (mixed $value): bool => $value !== null && $value !== ''));
        } catch (ConnectionException $exception) {
            throw new HttpException(502, 'Open Food Facts could not be reached.', $exception);
        }

        $body = $response->json();

        if (! $response->successful() || ! is_array($body) || ($body['status'] ?? null) !== 'status ok') {
            throw new HttpException(502, 'Open Food Facts could not accept the contribution.');
        }

        return [
            'code' => (string) ($body['code'] ?? $contribution['barcode']),
            'status' => (string) $body['status'],
        ];
    }

    /** @return array{user_id: string, password: string} */
    private function credentials(): array
    {
        $userId = config('services.open_food_facts.user_id');
        $password = config('services.open_food_facts.password');

        if (! is_string($userId) || $userId === '' || ! is_string($password) || $password === '') {
            throw new RuntimeException('Open Food Facts credentials are not configured.');
        }

        return ['user_id' => $userId, 'password' => $password];
    }

    private function endpoint(): string
    {
        $baseUrl = config('services.open_food_facts.base_url');

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new RuntimeException('The Open Food Facts base URL is not configured.');
        }

        return rtrim($baseUrl, '/').'/cgi/product_jqm2.pl';
    }
}
