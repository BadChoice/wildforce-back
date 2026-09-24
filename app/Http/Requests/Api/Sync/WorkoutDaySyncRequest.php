<?php

namespace App\Http\Requests\Api\Sync;

use App\Models\ExerciseResult;
use App\Models\PlannedExercise;
use App\Models\WorkoutBlock;
use App\Models\WorkoutDay;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class WorkoutDaySyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'records' => ['required', 'array', 'max:100'],
            'records.*' => ['array'],
            'records.*.id' => ['required', 'uuid'],
            'records.*.created_at' => ['required', 'date'],
            'records.*.updated_at' => ['required', 'date'],
            'records.*.deleted_at' => ['nullable', 'date'],
            'records.*.blocks' => ['present', 'array'],
            'records.*.exercises' => ['present', 'array'],
            'records.*.blocks.*' => ['array'],
            'records.*.blocks.*.id' => ['required', 'uuid'],
            'records.*.blocks.*.created_at' => ['required', 'date'],
            'records.*.blocks.*.updated_at' => ['required', 'date'],
            'records.*.blocks.*.deleted_at' => ['nullable', 'date'],
            'records.*.blocks.*.exercises' => ['present', 'array'],
            'records.*.exercises.*' => ['array'],
            'records.*.blocks.*.exercises.*' => ['array'],
            'records.*.exercises.*.id' => ['required', 'uuid'],
            'records.*.blocks.*.exercises.*.id' => ['required', 'uuid'],
            'records.*.exercises.*.created_at' => ['required', 'date'],
            'records.*.blocks.*.exercises.*.created_at' => ['required', 'date'],
            'records.*.exercises.*.updated_at' => ['required', 'date'],
            'records.*.blocks.*.exercises.*.updated_at' => ['required', 'date'],
            'records.*.exercises.*.deleted_at' => ['nullable', 'date'],
            'records.*.blocks.*.exercises.*.deleted_at' => ['nullable', 'date'],
            'records.*.exercises.*.exercise_results' => ['present', 'array'],
            'records.*.blocks.*.exercises.*.exercise_results' => ['present', 'array'],
            'records.*.exercises.*.exercise_results.*.id' => ['required', 'uuid'],
            'records.*.blocks.*.exercises.*.exercise_results.*.id' => ['required', 'uuid'],
            'records.*.exercises.*.exercise_results.*.created_at' => ['required', 'date'],
            'records.*.blocks.*.exercises.*.exercise_results.*.created_at' => ['required', 'date'],
            'records.*.exercises.*.exercise_results.*.updated_at' => ['required', 'date'],
            'records.*.blocks.*.exercises.*.exercise_results.*.updated_at' => ['required', 'date'],
            'records.*.exercises.*.exercise_results.*.deleted_at' => ['nullable', 'date'],
            'records.*.blocks.*.exercises.*.exercise_results.*.deleted_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ($this->input('records', []) as $recordIndex => $record) {
                if (! is_array($record)) {
                    continue;
                }

                $this->validateRecord($validator, "records.{$recordIndex}", $record, WorkoutDay::class, [], ['blocks', 'exercises']);

                foreach ($record['blocks'] ?? [] as $blockIndex => $block) {
                    if (! is_array($block)) {
                        continue;
                    }

                    $this->validateRecord($validator, "records.{$recordIndex}.blocks.{$blockIndex}", $block, WorkoutBlock::class, ['workout_day_id'], ['exercises']);
                    $this->validateExercises($validator, "records.{$recordIndex}.blocks.{$blockIndex}.exercises", $block['exercises'] ?? []);
                }

                $this->validateExercises($validator, "records.{$recordIndex}.exercises", $record['exercises'] ?? []);
            }
        }];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function records(): array
    {
        return $this->input('records', []);
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  class-string<WorkoutDay|WorkoutBlock|PlannedExercise|ExerciseResult>  $modelClass
     * @param  list<string>  $additionalExcludedAttributes
     * @param  list<string>  $nestedKeys
     */
    private function validateRecord(
        Validator $validator,
        string $path,
        array $record,
        string $modelClass,
        array $additionalExcludedAttributes = [],
        array $nestedKeys = [],
    ): void {
        $allowedAttributes = array_diff($modelClass::syncAttributes(), $additionalExcludedAttributes);
        $allowedRecordKeys = ['id', 'created_at', 'updated_at', 'deleted_at', ...$allowedAttributes, ...$nestedKeys];

        if (array_diff(array_keys($record), $allowedRecordKeys) !== []) {
            $validator->errors()->add($path, 'The record contains attributes that cannot be synchronized.');
        }
    }

    private function validateExercises(Validator $validator, string $path, mixed $exercises): void
    {
        if (! is_array($exercises)) {
            return;
        }

        foreach ($exercises as $exerciseIndex => $exercise) {
            if (! is_array($exercise)) {
                continue;
            }

            $exercisePath = "{$path}.{$exerciseIndex}";
            $this->validateRecord($validator, $exercisePath, $exercise, PlannedExercise::class, ['workout_day_id', 'workout_block_id'], ['exercise_results']);

            foreach ($exercise['exercise_results'] ?? [] as $resultIndex => $result) {
                if (is_array($result)) {
                    $this->validateRecord($validator, "{$exercisePath}.exercise_results.{$resultIndex}", $result, ExerciseResult::class, ['planned_exercise_id']);
                }
            }
        }
    }
}
