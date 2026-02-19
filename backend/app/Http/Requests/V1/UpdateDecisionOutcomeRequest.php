<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateDecisionOutcomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'final_choice' => ['nullable', 'string'],
            'outcome_notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('final_choice') && ! $this->filled('outcome_notes')) {
                $validator->errors()->add(
                    'payload',
                    'At least one of final_choice or outcome_notes is required.'
                );
            }
        });
    }
}
