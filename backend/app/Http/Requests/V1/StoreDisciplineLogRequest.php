<?php

namespace App\Http\Requests\V1;

use App\Models\DisciplineLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDisciplineLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision_id' => ['nullable', 'integer', 'exists:decisions,id'],
            'log_type' => ['required', Rule::in(DisciplineLog::LOG_TYPES)],
            'reason' => ['nullable', 'string', 'required_if:log_type,override,violation'],
        ];
    }
}
