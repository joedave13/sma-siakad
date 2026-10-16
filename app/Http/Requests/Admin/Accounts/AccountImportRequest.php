<?php

namespace App\Http\Requests\Admin\Accounts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountImportRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['student', 'teacher', 'parent'])],
            'file' => ['required', 'file', 'extensions:csv,xlsx', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Jenis akun wajib dipilih.',
            'type.in' => 'Jenis akun tidak dikenal.',
            'file.required' => 'Berkas impor wajib dipilih.',
            'file.file' => 'Berkas impor tidak valid.',
            'file.extensions' => 'Berkas harus berformat CSV atau XLSX.',
            'file.max' => 'Ukuran berkas maksimal 2 MB.',
        ];
    }
}
