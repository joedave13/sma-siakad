<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Hasil Belajar</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        .header { width: 100%; border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 10px; }
        .header-table { width: 100%; }
        .header-table td { vertical-align: middle; }
        .logo { width: 72px; height: 72px; }
        .school-name { font-size: 15px; font-weight: bold; }
        .school-detail { font-size: 10px; color: #333; }
        .doc-title { text-align: center; font-size: 13px; font-weight: bold; margin: 10px 0 8px; letter-spacing: 1px; }
        table.grid { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.grid th, table.grid td { border: 1px solid #444; padding: 4px 6px; }
        table.grid th { background: #f0f0f0; font-weight: bold; text-align: center; }
        .identity td { border: none; padding: 2px 4px; }
        .label { width: 130px; font-weight: bold; }
        .note-box { border: 1px solid #444; padding: 8px; min-height: 48px; margin-bottom: 12px; }
        .section-title { font-weight: bold; margin: 8px 0 4px; }
        .decision { border: 1px solid #444; padding: 6px 8px; margin-bottom: 12px; font-weight: bold; }
        .signatures { width: 100%; margin-top: 16px; }
        .signatures td { vertical-align: top; padding: 0 8px; width: 33%; }
        .sign-center { text-align: center; }
        .signature-space { height: 64px; }
        .signature-img { max-height: 56px; max-width: 140px; }
        .footer { margin-top: 18px; border-top: 1px solid #999; padding-top: 4px; font-size: 9px; color: #555; text-align: center; }
    </style>
</head>
<body>
    @php
        $school = $snapshot['school'];
        $logoData = null;
        if ($school['logo_path'] && \Illuminate\Support\Facades\Storage::disk('public')->exists($school['logo_path'])) {
            $logoExt = strtolower(pathinfo($school['logo_path'], PATHINFO_EXTENSION));
            $logoData = 'data:image/'.($logoExt === 'jpg' ? 'jpeg' : $logoExt).';base64,'.base64_encode(\Illuminate\Support\Facades\Storage::disk('public')->get($school['logo_path']));
        }

        $signatureData = null;
        if (! empty($school['signature_path']) && \Illuminate\Support\Facades\Storage::disk('public')->exists($school['signature_path'])) {
            $signatureData = 'data:image/png;base64,'.base64_encode(\Illuminate\Support\Facades\Storage::disk('public')->get($school['signature_path']));
        }

        $roman = [10 => 'X', 11 => 'XI', 12 => 'XII'];
        $gradeLevel = $snapshot['student']['grade_level'];
        $keputusan = null;
        if ($snapshot['semester']['name'] === 'Genap' && $gradeLevel !== null) {
            $keputusan = match ($snapshot['promotion_outcome']) {
                'promote' => 'Naik ke kelas '.($roman[$gradeLevel + 1] ?? ($gradeLevel + 1)),
                'retain' => 'Tinggal di kelas '.($roman[$gradeLevel] ?? $gradeLevel),
                'graduate' => 'Lulus',
                default => null,
            };
        }

        $generated = \Illuminate\Support\Carbon::parse($snapshot['generated_at'])->setTimezone('Asia/Jakarta');
        $dateLine = $school['city'].', '.$generated->locale('id')->isoFormat('D MMMM Y');
    @endphp

    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width: 84px;">
                    @if ($logoData)
                        <img src="{{ $logoData }}" alt="Logo" class="logo">
                    @endif
                </td>
                <td>
                    <div class="school-name">{{ $school['name'] }}</div>
                    <div class="school-detail">{{ $school['address'] }}</div>
                    @if ($school['phone'] || $school['email'])
                        <div class="school-detail">
                            {{ collect([$school['phone'], $school['email']])->filter()->implode(' | ') }}
                        </div>
                    @endif
                    @if ($school['npsn'])
                        <div class="school-detail">NPSN: {{ $school['npsn'] }}</div>
                    @endif
                </td>
            </tr>
        </table>
        <div class="doc-title">LAPORAN HASIL BELAJAR</div>
    </div>

    <table class="identity">
        <tr>
            <td class="label">Nama</td><td>: {{ $snapshot['student']['full_name'] }}</td>
            <td class="label">Kelas</td><td>: {{ $snapshot['student']['classroom_name'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">NISN</td><td>: {{ $snapshot['student']['nisn'] }}</td>
            <td class="label">Semester</td><td>: {{ $snapshot['semester']['name'] }}</td>
        </tr>
        <tr>
            <td class="label">NIS</td><td>: {{ $snapshot['student']['nis'] ?? '-' }}</td>
            <td class="label">Tahun Ajaran</td><td>: {{ $snapshot['semester']['academic_year'] }}</td>
        </tr>
        <tr>
            <td class="label">Wali Kelas</td><td>: {{ $snapshot['homeroom_teacher'] ?? '-' }}</td>
            <td></td><td></td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 32px;">No</th>
                <th>Mata Pelajaran</th>
                <th style="width: 70px;">Nilai Akhir</th>
                <th style="width: 55px;">Predikat</th>
                <th style="width: 180px;">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($snapshot['grades'] as $index => $grade)
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td>{{ $grade['subject'] }}</td>
                    <td style="text-align: center;">{{ $grade['score'] ?? '-' }}</td>
                    <td style="text-align: center;">{{ $grade['predicate'] }}</td>
                    <td>{{ $grade['remark'] ?? '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center;">Belum ada data nilai</td>
                </tr>
            @endforelse
            <tr>
                <td colspan="2" style="text-align: right; font-weight: bold;">Jumlah</td>
                <td style="text-align: center;">{{ $snapshot['score_summary']['total'] }}</td>
                <td colspan="2"></td>
            </tr>
            <tr>
                <td colspan="2" style="text-align: right; font-weight: bold;">Rata-rata</td>
                <td style="text-align: center;">{{ $snapshot['score_summary']['average'] ?? '-' }}</td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">Kehadiran</div>
    <table class="grid">
        <thead>
            <tr>
                <th>Sakit</th>
                <th>Izin</th>
                <th>Alpa</th>
                <th>Terlambat</th>
                <th>Kehadiran</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="text-align: center;">{{ $snapshot['attendance']['sick'] }} hari</td>
                <td style="text-align: center;">{{ $snapshot['attendance']['permitted'] }} hari</td>
                <td style="text-align: center;">{{ $snapshot['attendance']['absent'] }} hari</td>
                <td style="text-align: center;">{{ $snapshot['attendance']['late'] }} hari</td>
                <td style="text-align: center;">{{ $snapshot['attendance']['percentage'] }}%</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">Catatan Wali Kelas</div>
    <div class="note-box">{{ $snapshot['homeroom_note'] ?? '' }}</div>

    @if ($keputusan !== null)
        <div class="section-title">Keputusan</div>
        <div class="decision">{{ $keputusan }}</div>
    @endif

    <table class="signatures">
        <tr>
            <td>
                <div class="sign-center">Orang Tua/Wali</div>
                <div class="signature-space"></div>
                <div class="sign-center">( &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; )</div>
            </td>
            <td>
                <div class="sign-center">Wali Kelas</div>
                <div class="signature-space"></div>
                <div class="sign-center">( {{ $snapshot['homeroom_teacher'] ?? '........................' }} )</div>
            </td>
            <td>
                <div class="sign-center">{{ $dateLine }}</div>
                <div class="sign-center">Kepala Sekolah</div>
                <div class="sign-center">
                    @if ($signatureData)
                        <img src="{{ $signatureData }}" alt="Tanda Tangan" class="signature-img"><br>
                    @else
                        <div class="signature-space"></div>
                    @endif
                    <b>{{ $school['headmaster_name'] }}</b><br>
                    NIP. {{ $school['headmaster_nip'] }}
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">
        Dokumen digital dari SIAKAD-SMA &mdash; {{ $generated->isoFormat('DD/MM/YYYY HH:mm:ss') }} WIB
    </div>
</body>
</html>
