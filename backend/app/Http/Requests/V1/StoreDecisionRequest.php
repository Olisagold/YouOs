<?php

namespace App\Http\Requests\V1;

use App\Models\Decision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(Decision::CATEGORIES)],
            'context_json' => ['required', 'array'],
            'context_json.what' => ['required', 'string', 'min:1'],
            'context_json.why' => ['required', 'string', 'min:1'],
            'context_json.when' => ['required', 'string', 'min:1'],
            'context_json.urgency' => ['required', Rule::in(['low', 'medium', 'high'])],
            'context_json.estimated_impact' => ['required', 'string', 'min:1'],
            'context_json.alternatives' => ['nullable', 'array'],
            'context_json.alternatives.*' => ['required', 'string', 'min:1'],
        ];
    }
}
