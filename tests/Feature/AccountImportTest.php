<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

test('import page rejects unknown types and non-spreadsheet files', function (array $data, string $field, string $message): void {
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->post(route('admin.users.import.store'), $data)
        ->assertSessionHasErrors([$field => $message]);
})->with([
    'unknown type' => [
        ['type' => 'brother', 'file' => UploadedFile::fake()->createWithContent('students.csv', "nisn,nama,jenis_kelamin\n")],
        'type',
        'Jenis akun tidak dikenal.',
    ],
    'wrong extension' => [
        ['type' => 'student', 'file' => UploadedFile::fake()->create('students.txt', 10)],
        'file',
        'Berkas harus berformat CSV atau XLSX.',
    ],
]);

test('a valid student csv imports accounts sharing one shown password', function (): void {
    $csv = <<<'CSV'
nisn,nama,jenis_kelamin,nis,tanggal_lahir,tempat_lahir,alamat
1234567890,Budi Santoso,Laki-laki,9001,15/08/2010,Jakarta,Jl. Merdeka 1
1234567891,Siti Aminah,Perempuan,9002,2010-09-20,Bandung,Jl. Melati 2
CSV;
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->post(route('admin.users.import.store'), [
        'type' => 'student',
        'file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
    ])->assertRedirect(route('admin.users.import.create'));

    expect(Student::query()->count())->toBe(2);

    $toast = session('inertia.flash_data')['toast']['message'];
    expect($toast)->toStartWith('Berhasil mengimpor 2 akun siswa. Kata sandi awal bersama: ');
    preg_match('/bersama: (\S+) \(/', $toast, $matches);

    $budi = Student::query()->where('nisn', '1234567890')->firstOrFail();
    $siti = Student::query()->where('nisn', '1234567891')->firstOrFail();

    expect($budi->full_name)->toBe('Budi Santoso')
        ->and($budi->gender->value)->toBe('male')
        ->and($budi->birth_date->toDateString())->toBe('2010-08-15')
        ->and($budi->status->value)->toBe('active')
        ->and($budi->user->username)->toBe('1234567890')
        ->and($budi->user->is_active)->toBeTrue()
        ->and($budi->user->must_change_password)->toBeTrue()
        ->and($siti->gender->value)->toBe('female')
        ->and($siti->birth_date->toDateString())->toBe('2010-09-20')
        ->and(Hash::check($matches[1], $budi->user->password))->toBeTrue()
        ->and(Hash::check($matches[1], $siti->user->password))->toBeTrue()
        ->and(AuditLog::query()->where('action', 'account.import')->count())->toBe(1);
});

test('teacher and parent csv files import accounts keyed by nip and phone', function (string $type, string $header, string $row, string $role, callable $assert): void {
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->post(route('admin.users.import.store'), [
        'type' => $type,
        'file' => UploadedFile::fake()->createWithContent("{$type}.csv", "{$header}\n{$row}\n"),
    ])->assertRedirect(route('admin.users.import.create'));

    $assert();
})->with([
    'teacher' => [
        'teacher',
        'nip,nama,telepon',
        '198712345678901234,Ahmad Dani,6281111111111',
        'teacher',
        function (): void {
            $teacher = Teacher::query()->where('nip', '198712345678901234')->firstOrFail();

            expect($teacher->full_name)->toBe('Ahmad Dani')
                ->and($teacher->user->username)->toBe('198712345678901234')
                ->and($teacher->user->role)->toBe(Role::Teacher);
        },
    ],
    'parent' => [
        'parent',
        'telepon,nama',
        '6281234567890,Siti Rahayu',
        'parent',
        function (): void {
            $guardian = Guardian::query()->where('phone', '6281234567890')->firstOrFail();

            expect($guardian->full_name)->toBe('Siti Rahayu')
                ->and($guardian->user->username)->toBe('6281234567890')
                ->and($guardian->user->role)->toBe(Role::Parent);
        },
    ],
]);

test('an xlsx student file imports like csv', function (): void {
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray([
        ['nisn', 'nama', 'jenis_kelamin'],
        ['1234567898', 'Andi Wijaya', 'L'],
    ]);
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'akun-siswa.xlsx';
    IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->post(route('admin.users.import.store'), [
        'type' => 'student',
        'file' => new UploadedFile($path, 'students.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
    ])->assertRedirect(route('admin.users.import.create'));

    $student = Student::query()->where('nisn', '1234567898')->firstOrFail();

    expect($student->full_name)->toBe('Andi Wijaya')
        ->and($student->gender->value)->toBe('male')
        ->and($student->user->username)->toBe('1234567898');

    @unlink($path);
});

test('one invalid row rolls back the whole batch with a line-numbered report', function (): void {
    $csv = <<<'CSV'
nisn,nama,jenis_kelamin
1234567890,Budi Santoso,Laki-laki
123456,Keju Pelangi,Perempuan
CSV;
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->post(route('admin.users.import.store'), [
        'type' => 'student',
        'file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
    ])->assertRedirect(route('admin.users.import.create'));

    expect(Student::query()->count())->toBe(0)
        ->and(User::query()->where('role', Role::Student)->count())->toBe(0)
        ->and(session('inertia.flash_data')['importErrors'])->toBe([
            'Baris 3: NISN harus terdiri dari 10 digit angka.',
        ]);
});

test('a nisn that already exists in the database rejects the batch', function (): void {
    $existing = Student::factory()->create();
    $csv = "nisn,nama,jenis_kelamin\n{$existing->nisn},Peniru,Laki-laki\n";
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->post(route('admin.users.import.store'), [
        'type' => 'student',
        'file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
    ])->assertRedirect(route('admin.users.import.create'));

    expect(Student::query()->count())->toBe(1)
        ->and(session('inertia.flash_data')['importErrors'])->toBe([
            'Baris 2: NISN tersebut sudah terdaftar.',
        ]);
});

test('duplicates inside the file are reported with both row numbers', function (): void {
    $csv = <<<'CSV'
nisn,nama,jenis_kelamin
1234567890,Pertama,Laki-laki
1234567890,Kedua,Perempuan
CSV;
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->post(route('admin.users.import.store'), [
        'type' => 'student',
        'file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
    ])->assertRedirect(route('admin.users.import.create'));

    expect(Student::query()->count())->toBe(0)
        ->and(session('inertia.flash_data')['importErrors'])->toBe([
            'Baris 3: nisn sama dengan baris 2 di berkas ini.',
        ]);
});

test('a missing required header is reported on row one', function (): void {
    $csv = "nisn,nama\n1234567890,Budi Santoso\n";
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->post(route('admin.users.import.store'), [
        'type' => 'student',
        'file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
    ])->assertRedirect(route('admin.users.import.create'));

    expect(Student::query()->count())->toBe(0)
        ->and(session('inertia.flash_data')['importErrors'])->toBe([
            'Baris 1: kolom wajib tidak ditemukan: jenis_kelamin.',
        ]);
});

test('a file with only a header row reports no data', function (): void {
    $this->actingAs(User::factory()->asRole(Role::Admin)->create());

    $this->post(route('admin.users.import.store'), [
        'type' => 'student',
        'file' => UploadedFile::fake()->createWithContent('students.csv', "nisn,nama,jenis_kelamin\n"),
    ])->assertRedirect(route('admin.users.import.create'));

    expect(session('inertia.flash_data')['importErrors'])->toBe([
        'Berkas tidak berisi baris data.',
    ]);
});
