<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\SchoolProfile;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class DemoSchoolProfileSeeder extends Seeder
{
    /**
     * Truecolor emblem palette as 0xRRGGBB integers for the GD helpers.
     */
    private const FOREST = 0x1F513A;

    private const AMBER = 0x915A08;

    private const WHITE = 0xFFFFFF;

    private const INK = 0x172554;

    private const TRANSPARENT = 0x7F000000;

    /**
     * Seed the school identity with a generated logo and headmaster signature.
     */
    public function run(): void
    {
        if (! file_exists(public_path('storage'))) {
            Artisan::call('storage:link');
        }

        if (! file_exists(public_path('storage'))) {
            $this->command->warn('Symlink public/storage could not be created; run "php artisan storage:link" manually.');
        }

        $profile = SchoolProfile::query()->firstOrCreate([], [
            'name' => 'SMA Negeri 1 Nusantara',
            'npsn' => '20123456',
            'address' => 'Jl. Pendidikan No. 12',
            'city' => 'Kota Nusantara',
            'postal_code' => '40123',
            'phone' => '0215551234',
            'email' => 'sekolah@sman1nusantara.sch.id',
            'headmaster_name' => 'Drs. H. Budi Santoso, M.Pd.',
            'headmaster_nip' => '196805121992031004',
            'logo_path' => $this->storeLogo(),
            'headmaster_signature_path' => $this->storeSignature(),
            'updated_by' => User::query()->where('role', Role::Admin->value)->value('id'),
        ]);

        if ($profile->wasRecentlyCreated) {
            AuditLogger::record('school_profile.update', $profile, [], ['name' => $profile->name]);
        }
    }

    /**
     * Write the forest-and-amber school emblem and return its public path.
     */
    private function storeLogo(): string
    {
        $path = 'school/logo.png';

        if (Storage::disk('public')->exists($path)) {
            return $path;
        }

        $image = imagecreatetruecolor(512, 512);

        imagefilledrectangle($image, 0, 0, 511, 511, self::FOREST);
        imagefilledellipse($image, 256, 256, 400, 400, self::WHITE);
        imagefilledellipse($image, 256, 256, 360, 360, self::FOREST);
        imagefilledellipse($image, 256, 256, 220, 220, self::AMBER);

        $points = [];
        for ($i = 0; $i < 10; $i++) {
            $radius = $i % 2 === 0 ? 74 : 30;
            $angle = deg2rad(-90 + $i * 36);
            $points[] = (int) round(256 + $radius * cos($angle));
            $points[] = (int) round(256 + $radius * sin($angle));
        }

        imagepolygon($image, $points, self::WHITE);

        ob_start();
        imagepng($image);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    /**
     * Write the headmaster's ink signature and return its public path.
     */
    private function storeSignature(): string
    {
        $path = 'school/tanda-tangan.png';

        if (Storage::disk('public')->exists($path)) {
            return $path;
        }

        $image = imagecreatetruecolor(360, 160);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefilledrectangle($image, 0, 0, 359, 159, self::TRANSPARENT);
        imagealphablending($image, true);

        imagesetthickness($image, 3);

        $previousX = null;
        $previousY = null;
        for ($x = 20; $x <= 340; $x += 4) {
            $y = (int) round(88 + 34 * sin($x / 26) + 14 * sin($x / 7));

            if ($previousX !== null) {
                imageline($image, $previousX, $previousY, $x, $y, self::INK);
            }

            $previousX = $x;
            $previousY = $y;
        }

        ob_start();
        imagepng($image);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

        Storage::disk('public')->put($path, $binary);

        return $path;
    }
}
