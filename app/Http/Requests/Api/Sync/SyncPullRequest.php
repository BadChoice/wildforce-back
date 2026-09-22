<?php

namespace App\Http\Requests\Api\Sync;

use App\Support\SyncRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncPullRequest extends FormRequest
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
            'updated_after' => ['nullable', 'date'],
        ];
    }
}
