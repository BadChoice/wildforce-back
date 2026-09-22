<?php

namespace App\Http\Requests\Api\Sync;

use App\Contracts\Syncable;
use App\Support\SyncRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SyncPushRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'resource' => ['required', 'string', Rule::in(app(SyncRegistry::class)->resources())],
            'records' => ['required', 'array', 'max:100'],
            'records.*' => ['array'],
            'records.*.id' => ['required', 'uuid'],
            'records.*.created_at' => ['required', 'date'],
            'records.*.updated_at' => ['required', 'date'],
            'records.*.deleted_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('resource')) {
                return;
            }

            /** @var class-string<Syncable> $modelClass */
            $modelClass = app(SyncRegistry::class)->model($this->string('resource')->toString());
            $allowedAttributes = $modelClass::syncAttributes();

            foreach ($this->input('records', []) as $index => $record) {
                if (! is_array($record)) {
                    continue;
                }

                $allowedRecordKeys = [
                    'id',
                    'created_at',
                    'updated_at',
                    'deleted_at',
                    ...$allowedAttributes,
                ];
                $unexpectedAttributes = array_diff(array_keys($record), $allowedRecordKeys);

                if ($unexpectedAttributes !== []) {
                    $validator->errors()->add(
                        "records.{$index}",
                        'The record contains attributes that cannot be synchronized.',
                    );
                }
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
}
