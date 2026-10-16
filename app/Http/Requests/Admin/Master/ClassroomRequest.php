<?php

namespace App\Http\Requests\Admin\Master;

use App\Enums\ClassMajor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClassroomRequest extends FormRequest
{
    /**
     * Normalize empty selects to null so the nullable rules apply.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'major' => $this->input('major') ?: null,
            'homeroom_teacher_id' => $this->input('homeroom_teacher_id') ?: null,
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
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('classrooms', 'name')
                    ->where('academic_year_id', $this->input('academic_year_id'))
                    ->ignore($this->route('classroom')),
            ],
            'grade_level' => ['required', 'integer', Rule::in([10, 11, 12])],
            'major' => ['nullable', Rule::in(array_column(ClassMajor::cases(), 'value'))],
            'homeroom_teacher_id' => [
                'nullable',
                Rule::exists('teachers', 'id')->whereNull('deleted_at'),
            ],
        ];
    }
}
