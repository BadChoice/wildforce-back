<?php

namespace App\Services\ExerciseCatalog;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

class ExerciseCatalogValidator
{
    /**
     * @param  array<string, mixed>  $catalog
     * @return list<string>
     */
    public function validate(array $catalog): array
    {
        $validator = Validator::make($catalog, [
            'metadata.schemaVersion' => ['required', 'string', 'in:1.0.0'],
            'metadata.locale' => ['required', 'string', 'in:en'],
            'metadata.source' => ['required', 'string'],
            'metadata.exerciseCount' => ['required', 'integer', 'min:1'],
            'referenceData' => ['required', 'array'],
            'referenceData.exerciseCategories' => ['required', 'array', 'min:1'],
            'referenceData.goals' => ['required', 'array', 'min:1'],
            'referenceData.trainingLevels' => ['required', 'array', 'min:1'],
            'referenceData.movementPatterns' => ['required', 'array', 'min:1'],
            'referenceData.equipment' => ['required', 'array', 'min:1'],
            'referenceData.movementRestrictions' => ['required', 'array', 'min:1'],
            'referenceData.trackingModes' => ['required', 'array', 'min:1'],
            'referenceData.targetMetrics' => ['required', 'array', 'min:1'],
            'referenceData.majorMuscleGroups' => ['required', 'array', 'min:1'],
            'referenceData.muscleGroups' => ['required', 'array', 'min:1'],
            'exercises' => ['required', 'array', 'min:1'],
            'exercises.*.id' => ['required', 'string', 'regex:/^[a-z][A-Za-z0-9]*$/'],
            'exercises.*.name' => ['required', 'string'],
            'exercises.*.category' => ['required', 'string'],
            'exercises.*.requiredEquipment' => ['present', 'array'],
            'exercises.*.compatibleGoals' => ['required', 'array', 'min:1'],
            'exercises.*.movementPatterns' => ['required', 'array', 'min:1'],
            'exercises.*.difficulty' => ['required', 'string'],
            'exercises.*.isUnilateral' => ['required', 'boolean'],
            'exercises.*.isPerSideLoad' => ['required', 'boolean'],
            'exercises.*.contraindicatedRestrictions' => ['present', 'array'],
            'exercises.*.substitutionCandidates' => ['present', 'array'],
            'exercises.*.description' => ['required', 'string'],
            'exercises.*.instructions' => ['required', 'array'],
            'exercises.*.tips' => ['required', 'array'],
            'exercises.*.primaryMuscles' => ['required', 'array', 'min:1'],
            'exercises.*.secondaryMuscles' => ['present', 'array'],
            'exercises.*.supportsExternalLoad' => ['required', 'boolean'],
            'exercises.*.trackingMode' => ['required', 'string'],
            'exercises.*.targetMetrics' => ['required', 'array', 'min:1'],
            'exercises.*.metValue' => ['required', 'numeric', 'min:0'],
            'exercises.*.notesForLlm' => ['nullable', 'string'],
            'exercises.*.assets.verticalImageKey' => ['required', 'string'],
            'exercises.*.assets.tutorialImageKey' => ['required', 'string'],
        ]);

        $errors = $validator->errors()->all();

        foreach ($this->duplicateReferenceErrors($catalog) as $error) {
            $errors[] = $error;
        }

        foreach ($this->referenceIntegrityErrors($catalog) as $error) {
            $errors[] = $error;
        }

        return array_values($errors);
    }

    /**
     * @param  array<string, mixed>  $catalog
     * @return list<string>
     */
    private function duplicateReferenceErrors(array $catalog): array
    {
        $errors = [];
        $referenceData = Arr::get($catalog, 'referenceData', []);

        if (! is_array($referenceData)) {
            return $errors;
        }

        foreach ($referenceData as $type => $definitions) {
            if (! is_array($definitions)) {
                continue;
            }

            $ids = $this->referenceIds($definitions);

            if (count($ids) !== count(array_unique($ids))) {
                $errors[] = "referenceData.{$type} contains duplicate identifiers.";
            }
        }

        $exerciseIds = $this->referenceIds(Arr::get($catalog, 'exercises', []));

        if (count($exerciseIds) !== count(array_unique($exerciseIds))) {
            $errors[] = 'exercises contains duplicate identifiers.';
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $catalog
     * @return list<string>
     */
    private function referenceIntegrityErrors(array $catalog): array
    {
        $errors = [];
        $referenceData = Arr::get($catalog, 'referenceData', []);
        $exercises = Arr::get($catalog, 'exercises', []);

        if (! is_array($referenceData) || ! is_array($exercises)) {
            return $errors;
        }

        $knownIds = [];
        foreach (['exerciseCategories', 'goals', 'trainingLevels', 'movementPatterns', 'equipment', 'movementRestrictions', 'trackingModes', 'targetMetrics', 'majorMuscleGroups', 'muscleGroups'] as $type) {
            $knownIds[$type] = $this->referenceIds(Arr::get($referenceData, $type, []));
        }

        $exerciseIds = $this->referenceIds($exercises);

        foreach (Arr::get($referenceData, 'muscleGroups', []) as $index => $muscleGroup) {
            if (! is_array($muscleGroup)) {
                continue;
            }

            $this->appendUnknownReferenceErrors($errors, "referenceData.muscleGroups.{$index}.majorGroup", [$muscleGroup['majorGroup'] ?? null], $knownIds['majorMuscleGroups']);
        }

        foreach ($exercises as $index => $exercise) {
            if (! is_array($exercise)) {
                continue;
            }

            $path = "exercises.{$index}";
            $this->appendUnknownReferenceErrors($errors, "{$path}.category", [$exercise['category'] ?? null], $knownIds['exerciseCategories']);
            $this->appendUnknownReferenceErrors($errors, "{$path}.requiredEquipment", $exercise['requiredEquipment'] ?? [], $knownIds['equipment']);
            $this->appendUnknownReferenceErrors($errors, "{$path}.compatibleGoals", $exercise['compatibleGoals'] ?? [], $knownIds['goals']);
            $this->appendUnknownReferenceErrors($errors, "{$path}.movementPatterns", $exercise['movementPatterns'] ?? [], $knownIds['movementPatterns']);
            $this->appendUnknownReferenceErrors($errors, "{$path}.difficulty", [$exercise['difficulty'] ?? null], $knownIds['trainingLevels']);
            $this->appendUnknownReferenceErrors($errors, "{$path}.contraindicatedRestrictions", $exercise['contraindicatedRestrictions'] ?? [], $knownIds['movementRestrictions']);
            $this->appendUnknownReferenceErrors($errors, "{$path}.substitutionCandidates", $exercise['substitutionCandidates'] ?? [], $exerciseIds);
            $this->appendUnknownReferenceErrors($errors, "{$path}.primaryMuscles", $exercise['primaryMuscles'] ?? [], $knownIds['muscleGroups']);
            $this->appendUnknownReferenceErrors($errors, "{$path}.secondaryMuscles", $exercise['secondaryMuscles'] ?? [], $knownIds['muscleGroups']);
            $this->appendUnknownReferenceErrors($errors, "{$path}.trackingMode", [$exercise['trackingMode'] ?? null], $knownIds['trackingModes']);
            $this->appendUnknownReferenceErrors($errors, "{$path}.targetMetrics", $exercise['targetMetrics'] ?? [], $knownIds['targetMetrics']);

            $id = $exercise['id'] ?? null;
            if (is_string($id) && in_array($id, $exercise['substitutionCandidates'] ?? [], true)) {
                $errors[] = "{$path}.substitutionCandidates cannot include the exercise itself.";
            }

            if (array_intersect($exercise['primaryMuscles'] ?? [], $exercise['secondaryMuscles'] ?? []) !== []) {
                $errors[] = "{$path} cannot repeat a primary muscle as secondary.";
            }
        }

        if (Arr::get($catalog, 'metadata.exerciseCount') !== count($exercises)) {
            $errors[] = 'metadata.exerciseCount does not match the number of exercises.';
        }

        return $errors;
    }

    /**
     * @param  list<string>  $errors
     * @param  list<string>  $knownIds
     */
    private function appendUnknownReferenceErrors(array &$errors, string $path, mixed $references, array $knownIds): void
    {
        foreach (is_array($references) ? $references : [$references] as $reference) {
            if (! is_string($reference) || ! in_array($reference, $knownIds, true)) {
                $errors[] = "{$path} references an unknown identifier.";
            }
        }
    }

    /**
     * @return list<string>
     */
    private function referenceIds(mixed $definitions): array
    {
        if (! is_array($definitions)) {
            return [];
        }

        $ids = [];
        foreach ($definitions as $definition) {
            if (is_array($definition) && is_string($definition['id'] ?? null)) {
                $ids[] = $definition['id'];
            }
        }

        return $ids;
    }
}
