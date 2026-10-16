<?php

namespace App\Services\SchoolIdentity;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Validates and re-encodes school identity image uploads with GD. Every file
 * that lands on disk is a fresh server-side encoding, so uploaded bytes never
 * reach storage unmodified.
 */
class LogoProcessor
{
    private const DIRECTORY = 'school';

    private const LOGO_FILENAME = 'logo';

    private const SIGNATURE_FILENAME = 'tanda-tangan-kepala-sekolah';

    private const LOGO_MIN_PIXELS = 200;

    private const LOGO_MAX_PIXELS = 400;

    /**
     * Validate, resize (max 400 px) and re-encode the school logo, replacing
     * the previous file. Returns the stored path.
     *
     * @throws ValidationException
     */
    public function processLogo(UploadedFile $file): string
    {
        [$image, $mime] = $this->decode($file, 'logo', ['image/png', 'image/jpeg']);
        [$width, $height] = getimagesize($file->getPathname()) ?: [0, 0];

        if ($width < self::LOGO_MIN_PIXELS || $height < self::LOGO_MIN_PIXELS) {
            imagedestroy($image);

            throw ValidationException::withMessages([
                'logo' => "Ukuran logo minimal 200×200 piksel, sedangkan berkas yang dipilih {$width}×{$height} piksel.",
            ]);
        }

        if ($width > self::LOGO_MAX_PIXELS || $height > self::LOGO_MAX_PIXELS) {
            $image = $this->resizeToFit($image, self::LOGO_MAX_PIXELS);
        }

        $isPng = $mime === 'image/png';
        $path = self::DIRECTORY.'/'.self::LOGO_FILENAME.($isPng ? '.png' : '.jpg');

        $this->store($image, $isPng, $path);
        $this->deleteOtherExtensions(self::DIRECTORY, self::LOGO_FILENAME, $path);

        return $path;
    }

    /**
     * Validate and re-encode the headmaster signature (transparent PNG),
     * keeping its original dimensions. Returns the stored path.
     *
     * @throws ValidationException
     */
    public function processSignature(UploadedFile $file): string
    {
        [$image] = $this->decode($file, 'headmaster_signature', ['image/png']);

        $path = self::DIRECTORY.'/'.self::SIGNATURE_FILENAME.'.png';

        $this->store($image, true, $path);

        return $path;
    }

    /**
     * Decode the upload into a GD resource, rejecting content whose real
     * MIME type is not in the allow-list.
     *
     * @param  list<string>  $allowedMimes
     * @return array{0: \GdImage, 1: string}
     *
     * @throws ValidationException
     */
    private function decode(UploadedFile $file, string $attribute, array $allowedMimes): array
    {
        $size = @getimagesize($file->getPathname());
        $mime = is_array($size) ? (string) $size['mime'] : '';

        if ($mime === '') {
            throw ValidationException::withMessages([
                $attribute => $attribute === 'logo'
                    ? 'Berkas logo bukan gambar yang valid.'
                    : 'Berkas tanda tangan bukan gambar PNG yang valid.',
            ]);
        }

        if (! in_array($mime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                $attribute => $attribute === 'logo'
                    ? 'Logo harus berformat PNG atau JPG.'
                    : 'Tanda tangan harus berformat PNG.',
            ]);
        }

        $image = match ($mime) {
            'image/png' => @imagecreatefrompng($file->getPathname()),
            'image/jpeg' => @imagecreatefromjpeg($file->getPathname()),
            default => false,
        };

        if ($image === false) {
            throw ValidationException::withMessages([
                $attribute => $attribute === 'logo'
                    ? 'Berkas logo bukan gambar yang valid.'
                    : 'Berkas tanda tangan bukan gambar PNG yang valid.',
            ]);
        }

        if ($mime === 'image/png') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        return [$image, $mime];
    }

    /**
     * Scale the image down so neither edge exceeds the limit, keeping aspect
     * ratio and never upscaling.
     *
     * @param  \GdImage  $image
     * @return \GdImage
     */
    private function resizeToFit(mixed $image, int $maxEdge): mixed
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $ratio = min($maxEdge / $width, $maxEdge / $height);
        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        return $resized;
    }

    /**
     * Write the re-encoded bytes to the public disk.
     *
     * @param  \GdImage  $image
     */
    private function store(mixed $image, bool $isPng, string $path): void
    {
        ob_start();

        if ($isPng) {
            imagepng($image);
        } else {
            imagejpeg($image, null, 90);
        }

        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        Storage::disk('public')->put($path, $bytes);
    }

    /**
     * Remove sibling files with a different extension (png <-> jpg swaps when
     * the logo format changes), never the file just written.
     */
    private function deleteOtherExtensions(string $directory, string $baseName, string $keepPath): void
    {
        $disk = Storage::disk('public');

        foreach (['png', 'jpg', 'jpeg'] as $extension) {
            $candidate = "{$directory}/{$baseName}.{$extension}";

            if ($candidate !== $keepPath && $disk->exists($candidate)) {
                $disk->delete($candidate);
            }
        }
    }
}
