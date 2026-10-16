<?php

namespace App\Services\Accounts;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use Illuminate\Validation\Rule;

class AccountRules
{
    /**
     * Validation rules for one account type, shared by the manual account
     * forms and the CSV/XLSX importer so both enforce identical identity
     * formats and uniqueness. Null ids mean "create" (nothing to ignore).
     *
     * @param  string  $type  student|teacher|parent
     * @return array<string, array<int, mixed>>
     *
     * @throws \InvalidArgumentException
     */
    public static function rules(string $type, ?int $ignoreProfileId = null, ?int $ignoreUserId = null): array
    {
        return match ($type) {
            'student' => [
                'name' => ['required', 'string', 'max:255'],
                'nisn' => [
                    'bail',
                    'required',
                    'string',
                    'regex:/^[0-9]{10}$/',
                    Rule::unique('students', 'nisn')->ignore($ignoreProfileId),
                    Rule::unique('users', 'username')->ignore($ignoreUserId),
                ],
                'nis' => [
                    'nullable',
                    'string',
                    'max:20',
                    Rule::unique('students', 'nis')->ignore($ignoreProfileId),
                ],
                'gender' => ['required', Rule::in(array_column(Gender::cases(), 'value'))],
                'birth_date' => ['nullable', 'date'],
                'birth_place' => ['nullable', 'string', 'max:255'],
                'address' => ['nullable', 'string', 'max:255'],
                'status' => ['required', Rule::in(array_column(StudentStatus::cases(), 'value'))],
                'is_active' => ['nullable', 'boolean'],
            ],
            'teacher' => [
                'name' => ['required', 'string', 'max:255'],
                'nip' => [
                    'bail',
                    'required',
                    'string',
                    'regex:/^(?:[0-9]{16}|[0-9]{18})$/',
                    Rule::unique('teachers', 'nip')->ignore($ignoreProfileId),
                    Rule::unique('users', 'username')->ignore($ignoreUserId),
                ],
                'phone' => ['nullable', 'string', 'max:30'],
                'is_active' => ['nullable', 'boolean'],
            ],
            'parent' => [
                'name' => ['required', 'string', 'max:255'],
                'telepon' => [
                    'bail',
                    'required',
                    'string',
                    'regex:/^628[0-9]{8,12}$/',
                    Rule::unique('users', 'username')->ignore($ignoreUserId),
                ],
                'is_active' => ['nullable', 'boolean'],
            ],
            default => throw new \InvalidArgumentException("Jenis akun tidak dikenal: {$type}"),
        };
    }

    /**
     * Type-specific Indonesian messages, used by both the form requests and
     * the importer's per-row validator.
     *
     * @param  string  $type  student|teacher|parent
     * @return array<string, string>
     *
     * @throws \InvalidArgumentException
     */
    public static function messages(string $type): array
    {
        return match ($type) {
            'student' => [
                'name.required' => 'Nama wajib diisi.',
                'nisn.required' => 'NISN wajib diisi.',
                'nisn.regex' => 'NISN harus terdiri dari 10 digit angka.',
                'nisn.unique' => 'NISN tersebut sudah terdaftar.',
                'nis.unique' => 'NIS tersebut sudah terdaftar.',
                'gender.required' => 'Jenis kelamin wajib dipilih.',
                'gender.in' => 'Jenis kelamin tidak valid.',
                'status.required' => 'Status siswa wajib dipilih.',
                'status.in' => 'Status siswa tidak valid.',
            ],
            'teacher' => [
                'name.required' => 'Nama wajib diisi.',
                'nip.required' => 'NIP atau NUPTK wajib diisi.',
                'nip.regex' => 'NIP harus 18 digit angka atau NUPTK harus 16 digit angka.',
                'nip.unique' => 'NIP atau NUPTK tersebut sudah terdaftar.',
            ],
            'parent' => [
                'name.required' => 'Nama wajib diisi.',
                'telepon.required' => 'Nomor telepon wajib diisi.',
                'telepon.regex' => 'Nomor telepon harus diawali 628 lalu 8 sampai 12 digit angka.',
                'telepon.unique' => 'Nomor telepon tersebut sudah terdaftar.',
            ],
            default => throw new \InvalidArgumentException("Jenis akun tidak dikenal: {$type}"),
        };
    }
}
