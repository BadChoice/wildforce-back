<?php

namespace App\Http\Requests\Api\Ai;

use App\Services\Ai\JsonSchemaTypeConverter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class CompletionRequest extends FormRequest
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
            'prompt' => ['required', 'string', 'max:50000', 'not_regex:/^\s*$/'],
            'schema' => ['required', 'array'],
            'provider' => ['required', 'string', Rule::in(array_keys(config('ai.providers')))],
            'model' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * Decode the JSON Schema string currently produced by the iOS app.
     */
    protected function prepareForValidation(): void
    {
        $schema = $this->input('schema');

        if (! is_string($schema)) {
            return;
        }

        $decodedSchema = json_decode($schema, true);

        if (is_array($decodedSchema)) {
            $this->merge(['schema' => $decodedSchema]);
        }
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('schema')) {
                return;
            }

            try {
                app(JsonSchemaTypeConverter::class)->objectProperties(
                    app(JsonSchemaTypeFactory::class),
                    $this->array('schema'),
                );
            } catch (InvalidArgumentException $exception) {
                $validator->errors()->add('schema', $exception->getMessage());
            }
        }];
    }
}
