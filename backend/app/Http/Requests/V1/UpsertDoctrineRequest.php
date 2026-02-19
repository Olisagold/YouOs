<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpsertDoctrineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'goals_json' => ['required', 'array', 'min:1'],
            'goals_json.*' => ['required', 'array'],
            'goals_json.*.rank' => ['required', 'integer', 'distinct'],
            'goals_json.*.goal' => ['required', 'string', 'min:1'],

            'rules_json' => ['required', 'array', 'min:1'],
            'rules_json.*' => ['required', 'string', 'min:1'],

            'habits_json' => ['required', 'array', 'min:1'],
            'habits_json.*' => ['required', 'array'],
            'habits_json.*.habit' => ['required', 'string', 'min:1'],
            'habits_json.*.trigger' => ['required', 'string', 'min:1'],

            'weekly_targets_json' => ['required', 'array', 'min:1'],
            'weekly_targets_json.*' => ['required', 'array'],
            'weekly_targets_json.*.target' => ['required', 'string', 'min:1'],
            'weekly_targets_json.*.metric' => ['required', 'string', 'min:1'],
            'weekly_targets_json.*.current' => ['required', 'numeric'],
            'weekly_targets_json.*.goal' => ['required', 'numeric'],
        ];
    }
}
