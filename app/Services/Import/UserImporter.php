<?php

namespace App\Services\Import;

use App\Enums\Role;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Accounts\AccountRules;
use App\Services\GeneratedPassword;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class UserImporter
{
    /**
     * Header name (lowercase) to validated field key, per account type.
     *
     * @var array<string, array<string, string>>
     */
    private const HEADERS = [
        'student' => [
            'nisn' => 'nisn',
            'nama' => 'name',
            'jenis_kelamin' => 'gender',
            'nis' => 'nis',
            'tanggal_lahir' => 'birth_date',
            'tempat_lahir' => 'birth_place',
            'alamat' => 'address',
        ],
        'teacher' => [
            'nip' => 'nip',
            'nama' => 'name',
            'telepon' => 'phone',
        ],
        'parent' => [
            'telepon' => 'telepon',
            'nama' => 'name',
        ],
    ];

    /**
     * @var array<string, list<string>>
     */
    private const REQUIRED_HEADERS = [
        'student' => ['nisn', 'nama', 'jenis_kelamin'],
        'teacher' => ['nip', 'nama'],
        'parent' => ['telepon', 'nama'],
    ];

    /**
     * Validator attribute names shown in errors, matching the file columns.
     *
     * @var array<string, array<string, string>>
     */
    private const IMPORT_ATTRIBUTES = [
        'student' => [
            'name' => 'nama',
            'nisn' => 'nisn',
            'nis' => 'nis',
            'gender' => 'jenis kelamin',
            'birth_date' => 'tanggal lahir',
            'birth_place' => 'tempat lahir',
            'address' => 'alamat',
            'status' => 'status',
        ],
        'teacher' => [
            'name' => 'nama',
            'nip' => 'nip',
            'phone' => 'telepon',
        ],
        'parent' => [
            'name' => 'nama',
            'telepon' => 'telepon',
        ],
    ];

    /**
     * The identity field that must stay unique inside the file itself.
     *
     * @var array<string, string>
     */
    private const IDENTIFIER_FIELDS = [
        'student' => 'nisn',
        'teacher' => 'nip',
        'parent' => 'telepon',
    ];

    /**
     * Import accounts from a CSV/XLSX file. Every row is validated before
     * anything is written: a single row-level error aborts the whole batch so
     * a rejected duplicate never leaves a partially imported file, and nothing
     * is ever silently overwritten. On success all accounts share one
     * generated initial password, shown once by the caller.
     *
     * @param  string  $type  student|teacher|parent
     * @return array{created: int, errors: list<string>, password: string|null}
     */
    public function import(string $type, UploadedFile $file): array
    {
        $sheet = Excel::toCollection(null, $file)->first();

        if (! $sheet instanceof Collection) {
            return $this->failure(['Berkas tidak dapat dibaca.']);
        }

        $sheet = $sheet->values();

        if ($sheet->isEmpty()) {
            return $this->failure(['Berkas kosong.']);
        }

        $missing = $this->missingHeaders($type, $sheet->first());

        if ($missing !== []) {
            return $this->failure(['Baris 1: kolom wajib tidak ditemukan: '.implode(', ', $missing).'.']);
        }

        $positions = $this->headerPositions($type, $sheet->first());
        $defaults = $type === 'student' ? ['status' => 'active'] : [];
        $identifierField = self::IDENTIFIER_FIELDS[$type];

        /** @var array<int, int> $seen */
        $seen = [];
        /** @var list<string> $errors */
        $errors = [];
        /** @var list<array{line: int, data: array<string, string|null>}> $records */
        $records = [];

        foreach ($sheet->slice(1) as $offset => $row) {
            $line = $offset + 1;

            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $data = array_merge($defaults, $this->readCells($row, $positions));

            $identifier = trim((string) ($data[$identifierField] ?? ''));

            if ($identifier !== '') {
                if (isset($seen[$identifier])) {
                    $errors[] = "Baris {$line}: {$identifierField} sama dengan baris {$seen[$identifier]} di berkas ini.";

                    continue;
                }

                $seen[$identifier] = $line;
            }

            $rowErrors = Validator::make(
                $data,
                AccountRules::rules($type),
                AccountRules::messages($type),
                self::IMPORT_ATTRIBUTES[$type],
            )->errors()->all();

            foreach ($rowErrors as $message) {
                $errors[] = "Baris {$line}: {$message}";
            }

            if ($rowErrors === []) {
                $records[] = ['line' => $line, 'data' => $data];
            }
        }

        if ($errors !== []) {
            return $this->failure($errors);
        }

        if ($records === []) {
            return $this->failure(['Berkas tidak berisi baris data.']);
        }

        $password = GeneratedPassword::make();

        DB::transaction(function () use ($records, $type, $password): void {
            foreach ($records as $record) {
                $this->createAccount($type, $record['data'], $password);
            }
        });

        return ['created' => count($records), 'errors' => [], 'password' => $password];
    }

    /**
     * @return list<string>
     */
    private function missingHeaders(string $type, mixed $header): array
    {
        if (! $header instanceof Collection) {
            return self::REQUIRED_HEADERS[$type];
        }

        $cells = $this->headerCells($header);

        return array_values(array_filter(
            self::REQUIRED_HEADERS[$type],
            fn (string $name): bool => ! in_array($name, $cells, true),
        ));
    }

    /**
     * @return array<string, int> validated field key to column index
     */
    private function headerPositions(string $type, mixed $header): array
    {
        $cells = $header instanceof Collection ? $this->headerCells($header) : [];
        $positions = [];

        foreach (self::HEADERS[$type] as $name => $field) {
            $index = array_search($name, $cells, true);

            if ($index !== false) {
                $positions[$field] = $index;
            }
        }

        return $positions;
    }

    /**
     * @param  Collection<array-key, mixed>  $header
     * @return array<int, string> lowercase, trimmed, BOM-free header cells
     */
    private function headerCells(Collection $header): array
    {
        return $header
            ->map(fn (mixed $cell): string => mb_strtolower(str_replace("\u{FEFF}", '', trim((string) $cell))))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<array-key, mixed>  $row
     */
    private function rowIsEmpty(Collection $row): bool
    {
        return $row->every(fn (mixed $cell): bool => $this->normalizeCell($cell) === null);
    }

    /**
     * @param  Collection<array-key, mixed>  $row
     * @param  array<string, int>  $positions
     * @return array<string, string|null>
     */
    private function readCells(Collection $row, array $positions): array
    {
        $data = [];

        foreach ($positions as $field => $index) {
            $value = $this->normalizeCell($row->get($index));

            if ($field === 'gender') {
                $data[$field] = $this->normalizeGender($value);

                continue;
            }

            if ($field === 'birth_date') {
                $data[$field] = $this->parseDate($value);

                continue;
            }

            $data[$field] = $value;
        }

        return $data;
    }

    private function normalizeCell(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return sprintf('%.0F', $value);
        }

        $value = str_replace("\u{FEFF}", '', trim((string) $value));

        return $value === '' ? null : $value;
    }

    private function normalizeGender(?string $value): ?string
    {
        $value = trim((string) $value);

        return match (mb_strtolower($value)) {
            'laki-laki', 'l' => 'male',
            'perempuan', 'p' => 'female',
            '' => null,
            default => $value,
        };
    }

    /**
     * Accepts YYYY-MM-DD and DD/MM/YYYY; anything else is returned unchanged
     * so the validator reports it as an invalid date.
     */
    private function parseDate(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y'] as $format) {
            $parsed = \DateTimeImmutable::createFromFormat('!'.$format, $value);

            if ($parsed === false || $parsed->format($format) !== $value) {
                continue;
            }

            return $parsed->format('Y-m-d');
        }

        return $value;
    }

    /**
     * @param  array<string, string|null>  $data
     */
    private function createAccount(string $type, array $data, string $password): void
    {
        $role = match ($type) {
            'student' => Role::Student,
            'teacher' => Role::Teacher,
            'parent' => Role::Parent,
            default => throw new \InvalidArgumentException("Jenis akun tidak dikenal: {$type}"),
        };

        $usernameField = self::IDENTIFIER_FIELDS[$type];

        $user = User::query()->create([
            'name' => $data['name'] ?? '',
            'username' => $data[$usernameField] ?? '',
            'role' => $role,
            'password' => $password,
            'is_active' => true,
            'must_change_password' => true,
        ]);

        match ($type) {
            'student' => Student::query()->create([
                'user_id' => $user->id,
                'nisn' => $data['nisn'],
                'nis' => $data['nis'] ?? null,
                'full_name' => $user->name,
                'gender' => $data['gender'],
                'birth_date' => $data['birth_date'] ?? null,
                'birth_place' => $data['birth_place'] ?? null,
                'address' => $data['address'] ?? null,
                'status' => $data['status'] ?? 'active',
            ]),
            'teacher' => Teacher::query()->create([
                'user_id' => $user->id,
                'nip' => $data['nip'],
                'full_name' => $user->name,
                'phone' => $data['phone'] ?? null,
            ]),
            'parent' => Guardian::query()->create([
                'user_id' => $user->id,
                'full_name' => $user->name,
                'phone' => $data['telepon'],
            ]),
        };
    }

    /**
     * @param  list<string>  $errors
     * @return array{created: 0, errors: list<string>, password: null}
     */
    private function failure(array $errors): array
    {
        return ['created' => 0, 'errors' => $errors, 'password' => null];
    }
}
