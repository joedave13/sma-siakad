<?php

namespace App\Http\Requests\Admin\Master;

use App\Enums\SemesterTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SemesterRequest extends FormRequest
{
    /**
     * Normalize nullable dates and the active checkbox so updates always
     * receive the submitted values.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'start_date' => $this->input('start_date') ?: null,
            'end_date' => $this->input('end_date') ?: null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'academic_year_id' => [
                'required',
                Rule::exists('academic_years', 'id')->whereNull('deleted_at'),
            ],
            'name' => ['required', Rule::in(array_column(SemesterTerm::cases(), 'value'))],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
