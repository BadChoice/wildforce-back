<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Enums\Lab;
use ReflectionClass;

final class AgentModels
{
    /**
     * Provider and model pairs declared through #[Provider] and #[Model] on the application's agents.
     *
     * @return list<array{provider: string, model: string}>
     */
    public function all(): array
    {
        $models = [];

        foreach (File::allFiles(app_path('Ai/Agents')) as $file) {
            $class = 'App\\Ai\\Agents\\'.Str::of($file->getRelativePathname())->beforeLast('.php')->replace('/', '\\');

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            $model = $reflection->getAttributes(Model::class)[0] ?? null;
            $provider = $reflection->getAttributes(Provider::class)[0] ?? null;

            if ($model === null || $provider === null) {
                continue;
            }

            foreach ((array) $provider->newInstance()->value as $providerName) {
                $providerName = $providerName instanceof Lab ? $providerName->value : $providerName;
                $models["{$providerName}/{$model->newInstance()->value}"] = ['provider' => $providerName, 'model' => $model->newInstance()->value];
            }
        }

        return array_values($models);
    }
}
