<?php

use App\Enums\ExerciseCatalogReferenceType;

test('maps every catalog reference identifier to an imported Lucide icon', function () {
    $projectPath = dirname(__DIR__, 2);

    /** @var array<string, array<int, array{id: string}>> $referenceData */
    $referenceData = json_decode(
        file_get_contents($projectPath.'/resources/exercise-catalog/v1/catalog.json') ?: throw new RuntimeException('The exercise catalog could not be read.'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    )['referenceData'];

    foreach ($referenceData as $type => $references) {
        $referenceType = ExerciseCatalogReferenceType::tryFrom($type);

        expect($referenceType)->not->toBeNull();

        foreach ($references as $reference) {
            $icon = $referenceType->icon($reference['id']);

            expect($icon)
                ->not->toBe($referenceType->fallbackIcon())
                ->and(file_exists($projectPath.'/resources/views/flux/icon/'.$icon.'.blade.php'))->toBeTrue();
        }
    }
});
