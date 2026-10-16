<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\SchoolProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * GD-encoded PNG bytes for upload fixtures.
 */
function pngImageBytes(int $width = 200, int $height = 200): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 30, 100, 200));

    ob_start();
    imagepng($image);
    imagedestroy($image);

    return (string) ob_get_clean();
}

/**
 * GD-encoded JPEG bytes for upload fixtures.
 */
function jpgImageBytes(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 30, 100, 200));

    ob_start();
    imagejpeg($image, null, 90);
    imagedestroy($image);

    return (string) ob_get_clean();
}

/**
 * Transparent PNG with a visible stroke, as the signature upload expects.
 */
function transparentSignatureBytes(): string
{
    $image = imagecreatetruecolor(300, 150);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    imageline($image, 10, 75, 290, 75, imagecolorallocate($image, 200, 30, 30));

    ob_start();
    imagepng($image);
    imagedestroy($image);

    return (string) ob_get_clean();
}

/**
 * PNG signature and header so the MIME check passes, but an undecodable
 * pixel stream so the GD decode rejects it.
 */
function corruptPngBytes(): string
{
    $chunk = fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));

    return "\x89PNG\r\n\x1a\n"
        .$chunk('IHDR', pack('N', 200).pack('N', 200)."\x08\x06\x00\x00\x00")
        .$chunk('IDAT', 'not-a-valid-deflate-stream');
}

/**
 * The full identity payload without any uploaded files.
 *
 * @return array<string, mixed>
 */
function schoolSettingsPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'SMA Negeri 1 Contoh',
        'npsn' => '20123456',
        'address' => 'Jalan Pendidikan No. 1',
        'city' => 'Bandung',
        'postal_code' => '40115',
        'phone' => '0221234567',
        'email' => 'sekolah@example.sch.id',
        'headmaster_name' => 'Budi Santoso',
        'headmaster_nip' => '196001011985031001',
    ], $overrides);
}

/**
 * An existing profile whose logo file is already readable on disk.
 */
function seededSchoolIdentity(): SchoolProfile
{
    Storage::disk('public')->put('school/logo.png', pngImageBytes());

    return SchoolProfile::query()->create([
        'name' => 'SMA Negeri Lama',
        'address' => 'Jalan Lama No. 9',
        'city' => 'Bandung',
        'headmaster_name' => 'Kepala Lama',
        'headmaster_nip' => '196001011980031002',
        'logo_path' => 'school/logo.png',
    ]);
}

beforeEach(function (): void {
    Storage::fake('public');
});

it('redirects guests from the school settings page', function (): void {
    $this->get(route('admin.school-settings.edit'))->assertRedirect(route('login'));
});

it('forbids a teacher from opening school settings', function (string $method, string $routeName): void {
    $this->actingAs(User::factory()->asRole(Role::Teacher)->create())
        ->{$method}(route($routeName))
        ->assertForbidden();
})->with([
    'read' => ['get', 'admin.school-settings.edit'],
    'write' => ['put', 'admin.school-settings.update'],
]);

it('renders an empty settings page for a new school', function (): void {
    $this->actingAs(User::factory()->asRole(Role::Admin)->create())
        ->get(route('admin.school-settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/settings-school')
            ->where('profile', null));
});

it('shows the stored logo url and hides a signature that was never uploaded', function (): void {
    seededSchoolIdentity();

    $this->actingAs(User::factory()->asRole(Role::Admin)->create())
        ->get(route('admin.school-settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/settings-school')
            ->where('profile.name', 'SMA Negeri Lama')
            ->where('profile.logo_url', Storage::disk('public')->url('school/logo.png'))
            ->where('profile.signature_url', null));
});

it('hides the logo url when the stored file has disappeared from disk', function (): void {
    SchoolProfile::query()->create([
        'name' => 'SMA Negeri Hilang',
        'address' => 'Jalan Hilang No. 1',
        'city' => 'Bandung',
        'headmaster_name' => 'Kepala Sekolah',
        'headmaster_nip' => '196001011980031003',
        'logo_path' => 'school/logo.png',
    ]);

    $this->actingAs(User::factory()->asRole(Role::Admin)->create())
        ->get(route('admin.school-settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.logo_url', null)
            ->where('profile.signature_url', null));
});

it('creates the identity with a re-encoded logo, a signature, an audit entry and a toast', function (): void {
    $admin = User::factory()->asRole(Role::Admin)->create();

    $this->actingAs($admin)->put(route('admin.school-settings.update'), [
        ...schoolSettingsPayload(),
        'logo' => UploadedFile::fake()->createWithContent('logo.png', pngImageBytes(500, 500)),
        'headmaster_signature' => UploadedFile::fake()->createWithContent('tanda-tangan.png', transparentSignatureBytes()),
    ])->assertRedirect(route('admin.school-settings.edit'));

    $profile = SchoolProfile::query()->sole();
    expect($profile->name)->toBe('SMA Negeri 1 Contoh')
        ->and($profile->npsn)->toBe('20123456')
        ->and($profile->updated_by)->toBe($admin->id)
        ->and($profile->logo_path)->toBe('school/logo.png')
        ->and($profile->headmaster_signature_path)->toBe('school/tanda-tangan-kepala-sekolah.png');

    $disk = Storage::disk('public');
    expect($disk->exists('school/logo.png'))->toBeTrue()
        ->and($disk->exists('school/tanda-tangan-kepala-sekolah.png'))->toBeTrue();

    [$width, $height] = getimagesize($disk->path('school/logo.png'));
    expect($width)->toBe(400)->and($height)->toBe(400);

    $log = AuditLog::query()->where('action', 'school_profile.create')->sole();
    expect($log->user_id)->toBe($admin->id)
        ->and($log->auditable_id)->toBe($profile->id)
        ->and($log->old_values)->toBeNull()
        ->and($log->new_values['name'])->toBe('SMA Negeri 1 Contoh');

    expect(session('inertia.flash_data')['toast']['message'])->toBe('Identitas sekolah berhasil disimpan.');
});

it('updates the identity and audit-logs the changed values', function (): void {
    $admin = User::factory()->asRole(Role::Admin)->create();
    seededSchoolIdentity();

    $this->actingAs($admin)->put(route('admin.school-settings.update'), schoolSettingsPayload([
        'name' => 'SMA Negeri Diubah',
        'city' => 'Cimahi',
    ]))->assertRedirect(route('admin.school-settings.edit'));

    $profile = SchoolProfile::query()->sole();
    expect($profile->name)->toBe('SMA Negeri Diubah')
        ->and($profile->city)->toBe('Cimahi')
        ->and($profile->logo_path)->toBe('school/logo.png')
        ->and(Storage::disk('public')->exists('school/logo.png'))->toBeTrue();

    $log = AuditLog::query()->where('action', 'school_profile.update')->sole();
    expect($log->old_values['name'])->toBe('SMA Negeri Lama')
        ->and($log->new_values['name'])->toBe('SMA Negeri Diubah')
        ->and($log->old_values['city'])->toBe('Bandung')
        ->and($log->new_values['city'])->toBe('Cimahi');
});

it('requires the mandatory identity fields with Indonesian messages', function (string $field, string $message): void {
    $this->actingAs(User::factory()->asRole(Role::Admin)->create())
        ->put(route('admin.school-settings.update'), [])
        ->assertSessionHasErrors([$field => $message]);

    expect(SchoolProfile::query()->count())->toBe(0);
})->with([
    'nama sekolah' => ['name', 'Nama sekolah wajib diisi.'],
    'alamat' => ['address', 'Alamat sekolah wajib diisi.'],
    'kota' => ['city', 'Kota wajib diisi.'],
    'nama kepala sekolah' => ['headmaster_name', 'Nama kepala sekolah wajib diisi.'],
    'NIP kepala sekolah' => ['headmaster_nip', 'NIP kepala sekolah wajib diisi.'],
]);

it('demands a logo until one exists on disk', function (): void {
    $this->actingAs(User::factory()->asRole(Role::Admin)->create())
        ->put(route('admin.school-settings.update'), schoolSettingsPayload())
        ->assertSessionHasErrors(['logo' => 'Logo wajib diunggah.']);

    expect(SchoolProfile::query()->count())->toBe(0);
});

it('demands a new logo when the stored file has disappeared', function (): void {
    SchoolProfile::query()->create([
        'name' => 'SMA Negeri Lama',
        'address' => 'Jalan Lama No. 9',
        'city' => 'Bandung',
        'headmaster_name' => 'Kepala Lama',
        'headmaster_nip' => '196001011980031002',
        'logo_path' => 'school/logo.png',
    ]);

    $this->actingAs(User::factory()->asRole(Role::Admin)->create())
        ->put(route('admin.school-settings.update'), schoolSettingsPayload())
        ->assertSessionHasErrors(['logo' => 'Logo wajib diunggah.']);
});

it('rejects invalid logo uploads with Indonesian messages', function (UploadedFile $file, string $message): void {
    $this->actingAs(User::factory()->asRole(Role::Admin)->create())
        ->put(route('admin.school-settings.update'), [
            ...schoolSettingsPayload(),
            'logo' => $file,
        ])
        ->assertSessionHasErrors(['logo' => $message]);

    expect(SchoolProfile::query()->count())->toBe(0)
        ->and(Storage::disk('public')->exists('school/logo.png'))->toBeFalse();
})->with([
    'wrong format' => [
        UploadedFile::fake()->createWithContent('logo.gif', 'GIF89a'.str_repeat("\x00", 64)),
        'Logo harus berformat PNG atau JPG.',
    ],
    'too large' => [
        UploadedFile::fake()->createWithContent('logo.png', pngImageBytes().str_repeat('X', 2050 * 1024)),
        'Ukuran logo maksimal 2 MB.',
    ],
    'too small' => [
        UploadedFile::fake()->createWithContent('logo.png', pngImageBytes(100, 100)),
        'Ukuran logo minimal 200×200 piksel, sedangkan berkas yang dipilih 100×100 piksel.',
    ],
    'undecodable' => [
        UploadedFile::fake()->createWithContent('logo.png', corruptPngBytes()),
        'Berkas logo bukan gambar yang valid.',
    ],
]);

it('keeps the previous identity and logo when a replacement is rejected', function (): void {
    seededSchoolIdentity();

    $this->actingAs(User::factory()->asRole(Role::Admin)->create())
        ->put(route('admin.school-settings.update'), [
            ...schoolSettingsPayload(['name' => 'Nama Baru Ditolak']),
            'logo' => UploadedFile::fake()->createWithContent('logo.png', pngImageBytes(100, 100)),
        ])
        ->assertSessionHasErrors([
            'logo' => 'Ukuran logo minimal 200×200 piksel, sedangkan berkas yang dipilih 100×100 piksel.',
        ]);

    $profile = SchoolProfile::query()->sole();
    expect($profile->name)->toBe('SMA Negeri Lama')
        ->and($profile->logo_path)->toBe('school/logo.png')
        ->and(Storage::disk('public')->exists('school/logo.png'))->toBeTrue();
});

it('replaces the logo when the format changes, deleting the old file and scaling down', function (): void {
    seededSchoolIdentity();

    $this->actingAs(User::factory()->asRole(Role::Admin)->create())
        ->put(route('admin.school-settings.update'), [
            ...schoolSettingsPayload(),
            'logo' => UploadedFile::fake()->createWithContent('foto-sekolah.jpg', jpgImageBytes(800, 600)),
        ])
        ->assertRedirect(route('admin.school-settings.edit'));

    $disk = Storage::disk('public');
    $profile = SchoolProfile::query()->sole();
    expect($profile->logo_path)->toBe('school/logo.jpg')
        ->and($disk->exists('school/logo.jpg'))->toBeTrue()
        ->and($disk->exists('school/logo.png'))->toBeFalse();

    [$width, $height] = getimagesize($disk->path('school/logo.jpg'));
    expect($width)->toBe(400)->and($height)->toBe(300);
});

it('rejects uploads that are not a png or exceed one megabyte for the signature', function (UploadedFile $file, string $message): void {
    seededSchoolIdentity();

    $this->actingAs(User::factory()->asRole(Role::Admin)->create())
        ->put(route('admin.school-settings.update'), [
            ...schoolSettingsPayload(),
            'headmaster_signature' => $file,
        ])
        ->assertSessionHasErrors(['headmaster_signature' => $message]);

    expect(SchoolProfile::query()->sole()->headmaster_signature_path)->toBeNull()
        ->and(Storage::disk('public')->exists('school/tanda-tangan-kepala-sekolah.png'))->toBeFalse();
})->with([
    'wrong format' => [
        UploadedFile::fake()->createWithContent('tanda-tangan.jpg', jpgImageBytes(300, 150)),
        'Tanda tangan harus berformat PNG.',
    ],
    'too large' => [
        UploadedFile::fake()->createWithContent('tanda-tangan.png', transparentSignatureBytes().str_repeat('Y', 1025 * 1024)),
        'Ukuran tanda tangan maksimal 1 MB.',
    ],
]);
