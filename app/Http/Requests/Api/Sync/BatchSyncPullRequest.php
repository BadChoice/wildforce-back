<?php

namespace App\Http\Requests\Api\Sync;

use App\Support\SyncRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BatchSyncPullRequest extends FormRequest
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
            'cursor' => ['nullable', 'string', 'required_without:resources'],
            'resources' => ['nullable', 'array', 'min:1', 'max:20', 'required_without:cursor'],
            'resources.*' => ['array:resource,updated_after'],
            'resources.*.resource' => [
                'required',
                'string',
                'distinct:strict',
                Rule::in(app(SyncRegistry::class)->batchPullResources()),
            ],
            'resources.*.updated_after' => ['nullable', 'date'],
        ];
    }

    /**
     * @return list<array{resource: string, updated_after: string|null}>
     */
    public function resources(): array
    {
        return $this->input('resources', []);
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->has('cursor') && $this->has('resources')) {
                $validator->errors()->add('cursor', 'The cursor and resources fields cannot be used together.');
            }
        }];
    }
}
