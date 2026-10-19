<?php

namespace App\Http\Requests\Grades;

use App\Enums\GradeComponent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GradeStoreRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.student_id' => ['required', 'integer'],
            'entries.*.component' => ['required', 'string', Rule::enum(GradeComponent::class)],
            'entries.*.title' => ['nullable', 'string', 'max:60'],
            'entries.*.score' => ['nullable', 'string', 'max:16'],
            'entries.*.expected_updated_at' => ['nullable', 'string', 'max:64'],
            'remarks' => ['nullable', 'array'],
            'remarks.*.student_id' => ['required', 'integer'],
            'remarks.*.remark' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'entries.required' => 'Data nilai tidak boleh kosong.',
            'entries.*.title.max' => 'Judul kolom maksimal 60 karakter.',
            'remarks.*.remark.max' => 'Keterangan maksimal 255 karakter.',
        ];
    }
}
