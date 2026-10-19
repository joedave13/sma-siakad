<?php

namespace App\Http\Requests\Homeroom;

use Illuminate\Foundation\Http\FormRequest;

class HomeroomNoteRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer'],
            'note' => ['nullable', 'string', 'max:300'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.max' => 'Catatan wali kelas maksimal 300 karakter.',
        ];
    }
}
