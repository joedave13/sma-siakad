<?php

namespace App\Http\Requests\Admin;

use App\Models\SchoolProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Validator;

class SchoolProfileUpdateRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'npsn' => ['nullable', 'string', 'max:32'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'headmaster_name' => ['required', 'string', 'max:255'],
            'headmaster_nip' => ['required', 'string', 'max:30'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:2048'],
            'headmaster_signature' => ['nullable', 'file', 'mimes:png', 'max:1024'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama sekolah wajib diisi.',
            'address.required' => 'Alamat sekolah wajib diisi.',
            'city.required' => 'Kota wajib diisi.',
            'headmaster_name.required' => 'Nama kepala sekolah wajib diisi.',
            'headmaster_nip.required' => 'NIP kepala sekolah wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'logo.file' => 'Berkas logo tidak valid.',
            'logo.mimes' => 'Logo harus berformat PNG atau JPG.',
            'logo.max' => 'Ukuran logo maksimal 2 MB.',
            'headmaster_signature.file' => 'Berkas tanda tangan tidak valid.',
            'headmaster_signature.mimes' => 'Tanda tangan harus berformat PNG.',
            'headmaster_signature.max' => 'Ukuran tanda tangan maksimal 1 MB.',
        ];
    }

    /**
     * The logo is mandatory until a readable file exists on disk, matching the
     * identity check that blocks report card publishing.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('logo') || $this->hasFile('logo')) {
                    return;
                }

                $profile = SchoolProfile::current();
                $missing = $profile === null
                    || blank($profile->logo_path)
                    || ! Storage::disk('public')->exists((string) $profile->logo_path);

                if ($missing) {
                    $validator->errors()->add('logo', 'Logo wajib diunggah.');
                }
            },
        ];
    }
}
