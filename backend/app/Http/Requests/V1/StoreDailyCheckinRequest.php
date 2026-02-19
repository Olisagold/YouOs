<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreDailyCheckinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'energy' => ['required', 'integer', 'between:1,10'],
            'mood' => ['required', 'integer', 'between:1,10'],
            'missions_json' => ['required', 'array', 'min:1', 'max:3'],
            'missions_json.*' => ['required', 'string', 'min:1'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
