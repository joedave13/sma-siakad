<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SchoolProfileUpdateRequest;
use App\Models\SchoolProfile;
use App\Services\AuditLogger;
use App\Services\SchoolIdentity\LogoProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SchoolProfileController extends Controller
{
    /**
     * @var list<string>
     */
    private const FIELDS = [
        'name',
        'npsn',
        'address',
        'city',
        'postal_code',
        'phone',
        'email',
        'headmaster_name',
        'headmaster_nip',
        'logo_path',
        'headmaster_signature_path',
    ];

    public function edit(): Response
    {
        $profile = SchoolProfile::current();

        return Inertia::render('admin/settings-school', [
            'profile' => $profile === null ? null : $this->payload($profile),
        ]);
    }

    public function update(SchoolProfileUpdateRequest $request, LogoProcessor $processor): RedirectResponse
    {
        $profile = SchoolProfile::current() ?? new SchoolProfile;
        $isNew = ! $profile->exists;
        $old = $isNew ? [] : $profile->only(self::FIELDS);
        $validated = $request->validated();

        $logoPath = $profile->logo_path;
        if ($request->hasFile('logo')) {
            $logoPath = $processor->processLogo($request->file('logo'));
        }

        $signaturePath = $profile->headmaster_signature_path;
        if ($request->hasFile('headmaster_signature')) {
            $signaturePath = $processor->processSignature($request->file('headmaster_signature'));
        }

        $profile->fill([
            ...Arr::except($validated, ['logo', 'headmaster_signature']),
            'logo_path' => $logoPath,
            'headmaster_signature_path' => $signaturePath,
            'updated_by' => $request->user()?->id,
        ]);
        $profile->save();

        AuditLogger::record(
            $isNew ? 'school_profile.create' : 'school_profile.update',
            $profile,
            $old,
            $profile->only(self::FIELDS),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Identitas sekolah berhasil disimpan.',
        ]);

        return redirect()->route('admin.school-settings.edit');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(SchoolProfile $profile): array
    {
        $disk = Storage::disk('public');

        return [
            ...$profile->only(self::FIELDS),
            'logo_url' => filled($profile->logo_path) && $disk->exists((string) $profile->logo_path)
                ? $disk->url((string) $profile->logo_path)
                : null,
            'signature_url' => filled($profile->headmaster_signature_path) && $disk->exists((string) $profile->headmaster_signature_path)
                ? $disk->url((string) $profile->headmaster_signature_path)
                : null,
        ];
    }
}
