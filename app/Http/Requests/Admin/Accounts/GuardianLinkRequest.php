<?php

namespace App\Http\Requests\Admin\Accounts;

use App\Models\Guardian;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardianLinkRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $guardian = $this->route('guardian');

        return [
            'student_id' => [
                'required',
                Rule::exists('students', 'id')->whereNull('deleted_at'),
                Rule::unique('guardian_student', 'student_id')
                    ->where('guardian_id', $guardian instanceof Guardian ? $guardian->getKey() : null),
            ],
            'relationship' => ['required', Rule::in(['Ayah', 'Ibu', 'Wali'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'student_id.required' => 'Siswa wajib dipilih.',
            'student_id.exists' => 'Siswa tidak ditemukan atau sudah tidak aktif.',
            'student_id.unique' => 'Siswa tersebut sudah tertaut ke orang tua ini.',
            'relationship.required' => 'Hubungan wajib dipilih.',
            'relationship.in' => 'Hubungan tidak dikenal.',
        ];
    }
}
