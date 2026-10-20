<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;

/**
 * Shared demo dataset and deterministic score/status helpers for DemoDataSeeder.
 */
final class DemoData
{
    public const string CURRENT_YEAR = '2026/2027';

    public const string NEXT_YEAR = '2027/2028';

    public const string PASSWORD = 'password';

    /**
     * @var list<array{name: string, start: string, end: string, active: bool}>
     */
    public const array SEMESTERS = [
        ['name' => 'ganjil', 'start' => '2026-07-13', 'end' => '2026-12-18', 'active' => true],
        ['name' => 'genap', 'start' => '2027-01-05', 'end' => '2027-06-25', 'active' => false],
    ];

    /**
     * @var list<array{code: string, name: string, kkm: int}>
     */
    public const array SUBJECTS = [
        ['code' => 'PPKN', 'name' => 'PPKn', 'kkm' => 75],
        ['code' => 'BIND', 'name' => 'Bahasa Indonesia', 'kkm' => 75],
        ['code' => 'MTK', 'name' => 'Matematika', 'kkm' => 75],
        ['code' => 'BING', 'name' => 'Bahasa Inggris', 'kkm' => 75],
        ['code' => 'SEJ', 'name' => 'Sejarah Indonesia', 'kkm' => 75],
        ['code' => 'PJOK', 'name' => 'PJOK', 'kkm' => 70],
        ['code' => 'INF', 'name' => 'Informatika', 'kkm' => 75],
        ['code' => 'BIO', 'name' => 'Biologi', 'kkm' => 75],
        ['code' => 'FIS', 'name' => 'Fisika', 'kkm' => 75],
        ['code' => 'KIM', 'name' => 'Kimia', 'kkm' => 75],
        ['code' => 'SOS', 'name' => 'Sosiologi', 'kkm' => 75],
        ['code' => 'GEO', 'name' => 'Geografi', 'kkm' => 75],
        ['code' => 'EKON', 'name' => 'Ekonomi', 'kkm' => 75],
    ];

    /**
     * Subject code to owning teacher index in TEACHERS.
     *
     * @var array<string, int>
     */
    public const array SUBJECT_OWNERS = [
        'PPKN' => 6,
        'BIND' => 1,
        'MTK' => 0,
        'BING' => 2,
        'SEJ' => 7,
        'PJOK' => 7,
        'INF' => 6,
        'BIO' => 3,
        'FIS' => 4,
        'KIM' => 4,
        'SOS' => 5,
        'GEO' => 5,
        'EKON' => 7,
    ];

    /**
     * @var list<array{full_name: string, nip: string, phone: string}>
     */
    public const array TEACHERS = [
        ['full_name' => 'Siti Aminah, S.Pd.', 'nip' => '198001122005011003', 'phone' => '628131110001'],
        ['full_name' => 'Bambang Suryanto, S.Pd.', 'nip' => '197903252004121002', 'phone' => '628131110002'],
        ['full_name' => 'Agus Setiawan, S.Pd.', 'nip' => '198207172006041005', 'phone' => '628131110003'],
        ['full_name' => 'Rina Kurniawati, S.Pd.', 'nip' => '198109092007012006', 'phone' => '628131110004'],
        ['full_name' => 'Dedi Prasetyo, S.Pd.', 'nip' => '197811232003121007', 'phone' => '628131110005'],
        ['full_name' => 'Nurhayati, S.Pd.', 'nip' => '198404182009022008', 'phone' => '628131110006'],
        ['full_name' => 'Joko Susilo, S.Pd.', 'nip' => '198302142008031009', 'phone' => '628131110007'],
        ['full_name' => 'Lestari Handayani, S.Pd.', 'nip' => '198609302011012010', 'phone' => '628131110008'],
    ];

    /**
     * Classrooms of both academic years; homeroom is a TEACHERS index.
     *
     * @var list<array{name: string, grade_level: int, major: string|null, homeroom: int}>
     */
    public const array CLASSROOMS = [
        ['name' => 'X-1', 'grade_level' => 10, 'major' => null, 'homeroom' => 0],
        ['name' => 'X-2', 'grade_level' => 10, 'major' => null, 'homeroom' => 1],
        ['name' => 'XI IPA-1', 'grade_level' => 11, 'major' => 'ipa', 'homeroom' => 2],
        ['name' => 'XI IPS-1', 'grade_level' => 11, 'major' => 'ips', 'homeroom' => 3],
        ['name' => 'XII IPA-1', 'grade_level' => 12, 'major' => 'ipa', 'homeroom' => 4],
        ['name' => 'XII IPS-1', 'grade_level' => 12, 'major' => 'ips', 'homeroom' => 5],
    ];

    /**
     * Subjects taught per classroom, in teaching-assignment order.
     *
     * @var array<string, list<string>>
     */
    public const array CLASSROOM_SUBJECTS = [
        'X-1' => ['PPKN', 'BIND', 'MTK', 'BING', 'PJOK', 'INF', 'BIO'],
        'X-2' => ['PPKN', 'BIND', 'MTK', 'BING', 'PJOK', 'INF', 'BIO'],
        'XI IPA-1' => ['PPKN', 'BIND', 'MTK', 'BING', 'INF', 'BIO', 'FIS', 'KIM'],
        'XI IPS-1' => ['PPKN', 'BIND', 'MTK', 'BING', 'SEJ', 'SOS', 'GEO'],
        'XII IPA-1' => ['PPKN', 'BIND', 'MTK', 'BING', 'BIO', 'FIS', 'KIM'],
        'XII IPS-1' => ['PPKN', 'BIND', 'MTK', 'BING', 'SOS', 'GEO', 'EKON'],
    ];

    /**
     * Students in ordinal order: nisn/nis derive from the ordinal, ability
     * drives every seeded grade score.
     *
     * @var list<array{full_name: string, gender: string, birth_date: string, classroom: string, ability: int}>
     */
    public const array STUDENTS = [
        ['full_name' => 'Andi Wijaya', 'gender' => 'male', 'birth_date' => '2010-03-14', 'classroom' => 'X-1', 'ability' => 88],
        ['full_name' => 'Dewi Pratiwi', 'gender' => 'female', 'birth_date' => '2010-07-02', 'classroom' => 'X-1', 'ability' => 84],
        ['full_name' => 'Rizky Ramadhan', 'gender' => 'male', 'birth_date' => '2010-11-21', 'classroom' => 'X-1', 'ability' => 91],
        ['full_name' => 'Siti Nurhaliza', 'gender' => 'female', 'birth_date' => '2010-01-19', 'classroom' => 'X-2', 'ability' => 79],
        ['full_name' => 'Ahmad Fauzi', 'gender' => 'male', 'birth_date' => '2010-05-30', 'classroom' => 'X-2', 'ability' => 62],
        ['full_name' => 'Eka Marlina', 'gender' => 'female', 'birth_date' => '2010-09-08', 'classroom' => 'X-2', 'ability' => 86],
        ['full_name' => 'Sari Wijaya', 'gender' => 'female', 'birth_date' => '2009-04-11', 'classroom' => 'XI IPA-1', 'ability' => 82],
        ['full_name' => 'Anggun Permata', 'gender' => 'female', 'birth_date' => '2009-08-25', 'classroom' => 'XI IPA-1', 'ability' => 77],
        ['full_name' => 'Fajar Nugroho', 'gender' => 'male', 'birth_date' => '2009-12-03', 'classroom' => 'XI IPA-1', 'ability' => 90],
        ['full_name' => 'Putri Handayani', 'gender' => 'female', 'birth_date' => '2009-02-17', 'classroom' => 'XI IPS-1', 'ability' => 76],
        ['full_name' => 'Yoga Santoso', 'gender' => 'male', 'birth_date' => '2009-06-28', 'classroom' => 'XI IPS-1', 'ability' => 85],
        ['full_name' => 'Citra Ayu', 'gender' => 'female', 'birth_date' => '2009-10-14', 'classroom' => 'XI IPS-1', 'ability' => 83],
        ['full_name' => 'Hendra Kusuma', 'gender' => 'male', 'birth_date' => '2008-03-09', 'classroom' => 'XII IPA-1', 'ability' => 89],
        ['full_name' => 'Wulan Sari', 'gender' => 'female', 'birth_date' => '2008-07-21', 'classroom' => 'XII IPA-1', 'ability' => 70],
        ['full_name' => 'Doni Kurniawan', 'gender' => 'male', 'birth_date' => '2008-11-05', 'classroom' => 'XII IPA-1', 'ability' => 81],
        ['full_name' => 'Melati Santoso', 'gender' => 'female', 'birth_date' => '2008-02-28', 'classroom' => 'XII IPS-1', 'ability' => 87],
        ['full_name' => 'Gilang Ramadhan', 'gender' => 'male', 'birth_date' => '2008-06-16', 'classroom' => 'XII IPS-1', 'ability' => 84],
        ['full_name' => 'Ayu Wulandari', 'gender' => 'female', 'birth_date' => '2008-10-24', 'classroom' => 'XII IPS-1', 'ability' => 80],
    ];

    /**
     * Guardians with their children's student ordinals; two families have
     * siblings in different grades so the guardian child switcher has data.
     *
     * @var list<array{full_name: string, phone: string, relationship: string, students: list<int>}>
     */
    public const array GUARDIANS = [
        ['full_name' => 'Ibu Ratna Wijaya', 'phone' => '628123000001', 'relationship' => 'Ibu', 'students' => [0, 6]],
        ['full_name' => 'Bapak Hendra Santoso', 'phone' => '628123000002', 'relationship' => 'Ayah', 'students' => [10, 15]],
        ['full_name' => 'Ibu Sri Wahyuni', 'phone' => '628123000003', 'relationship' => 'Ibu', 'students' => [1]],
        ['full_name' => 'Bapak Agus Salim', 'phone' => '628123000004', 'relationship' => 'Ayah', 'students' => [2]],
        ['full_name' => 'Ibu Tuti Herawati', 'phone' => '628123000005', 'relationship' => 'Ibu', 'students' => [3]],
        ['full_name' => 'Bapak Muhammad Yusuf', 'phone' => '628123000006', 'relationship' => 'Ayah', 'students' => [4]],
        ['full_name' => 'Ibu Endang Purwanti', 'phone' => '628123000007', 'relationship' => 'Ibu', 'students' => [5]],
        ['full_name' => 'Ibu Maya Sartika', 'phone' => '628123000008', 'relationship' => 'Ibu', 'students' => [7]],
        ['full_name' => 'Bapak Rudi Hartono', 'phone' => '628123000009', 'relationship' => 'Ayah', 'students' => [8]],
        ['full_name' => 'Ibu Nia Kurnia', 'phone' => '628123000010', 'relationship' => 'Ibu', 'students' => [9]],
        ['full_name' => 'Bapak Darmawan', 'phone' => '628123000011', 'relationship' => 'Ayah', 'students' => [11]],
        ['full_name' => 'Ibu Ratih Panduwati', 'phone' => '628123000012', 'relationship' => 'Ibu', 'students' => [12]],
        ['full_name' => 'Bapak Slamet Riyadi', 'phone' => '628123000013', 'relationship' => 'Ayah', 'students' => [13]],
        ['full_name' => 'Ibu Fitriani', 'phone' => '628123000014', 'relationship' => 'Ibu', 'students' => [14]],
        ['full_name' => 'Ibu Yanti Rahmawati', 'phone' => '628123000016', 'relationship' => 'Ibu', 'students' => [16]],
        ['full_name' => 'Bapak Teguh Wirawan', 'phone' => '628123000017', 'relationship' => 'Ayah', 'students' => [17]],
    ];

    /**
     * Assignments whose student never receives a Final (UAS) entry, leaving
     * the component incomplete for grading-gap and report card states.
     *
     * @var list<array{classroom: string, subject: string, student: int}>
     */
    public const array GRADE_GAPS = [
        ['classroom' => 'X-1', 'subject' => 'MTK', 'student' => 0],
        ['classroom' => 'XI IPS-1', 'subject' => 'BING', 'student' => 10],
    ];

    /**
     * @var list<string>
     */
    private const array REMARKS = [
        'Sudah baik, pertahankan prestasinya.',
        'Perlu pendalaman materi pada bagian ini.',
        'Kerjakan tugas lebih teliti dan tepat waktu.',
        'Progres yang baik dibanding semester sebelumnya.',
    ];

    /**
     * @var list<string>
     */
    private const array HOMEROOM_NOTES = [
        'Anak yang disiplin dan mengikuti pembelajaran dengan baik. Pertahankan keaktifannya di kelas.',
        'Perlu meningkatkan ketelitian dalam mengerjakan tugas, terutama pada mata pelajaran numerik.',
        'Menunjukkan perkembangan yang baik sepanjang semester dan mudah bekerja sama dengan teman.',
        'Hadir secara konsisten dan aktif membantu kegiatan kelas. Semoga hasil ini dipertahankan.',
        'Dianjurkan untuk lebih giat belajar dan memanfaatkan waktu belajar di rumah secara optimal.',
    ];

    /**
     * The ten-digit NISN for one student ordinal.
     */
    public static function nisnFor(int $ordinal): string
    {
        $prefix = match (true) {
            $ordinal <= 5 => '00912345',
            $ordinal <= 11 => '00812345',
            default => '00712345',
        };

        return $prefix.sprintf('%02d', $ordinal + 1);
    }

    /**
     * The eight-digit NIS for one student ordinal.
     */
    public static function nisFor(int $ordinal): string
    {
        $year = match (true) {
            $ordinal <= 5 => '2026',
            $ordinal <= 11 => '2025',
            default => '2024',
        };

        return $year.sprintf('%04d', $ordinal + 1);
    }

    /**
     * NISN to ordinal lookup for the demo roster.
     *
     * @return array<string, int>
     */
    public static function ordinalsByNisn(): array
    {
        $map = [];
        foreach (array_keys(self::STUDENTS) as $ordinal) {
            $map[self::nisnFor($ordinal)] = $ordinal;
        }

        return $map;
    }

    /**
     * The deterministic attendance status for one student and school day of
     * the seed window (day 0 is the oldest).
     */
    public static function statusFor(int $ordinal, int $dayIndex): AttendanceStatus
    {
        return match (($ordinal * 3 + $dayIndex) % 23) {
            0 => AttendanceStatus::Absent,
            1 => AttendanceStatus::Sick,
            2 => AttendanceStatus::Permitted,
            3 => AttendanceStatus::Late,
            default => AttendanceStatus::Present,
        };
    }

    /**
     * The explanatory note stored with one non-present attendance status.
     */
    public static function attendanceNoteFor(AttendanceStatus $status): ?string
    {
        return match ($status) {
            AttendanceStatus::Sick => 'Flu, istirahat di rumah.',
            AttendanceStatus::Permitted => 'Keperluan keluarga.',
            AttendanceStatus::Absent => 'Tanpa keterangan.',
            AttendanceStatus::Late => 'Bus sekolah terlambat.',
            AttendanceStatus::Present => null,
        };
    }

    /**
     * The index of one subject code in SUBJECTS, used for score variety.
     */
    public static function subjectIndexFor(string $code): int
    {
        foreach (self::SUBJECTS as $index => $subject) {
            if ($subject['code'] === $code) {
                return $index;
            }
        }

        return 0;
    }

    /**
     * One deterministic component score around the student's ability.
     */
    public static function scoreFor(int $ability, int $ordinal, int $subjectIndex, int $entryIndex): int
    {
        return max(55, min(100, $ability + (($ordinal * 7 + $subjectIndex * 3 + $entryIndex * 5) % 17) - 8));
    }

    /**
     * The seeded subject remark, present for about a third of grade rows.
     */
    public static function remarkFor(int $ordinal, int $subjectIndex): ?string
    {
        $seed = $ordinal + $subjectIndex;

        if ($seed % 3 !== 0) {
            return null;
        }

        return self::REMARKS[$seed % count(self::REMARKS)];
    }

    /**
     * The seeded homeroom note for one student's report card.
     */
    public static function homeroomNoteFor(int $ordinal): string
    {
        return self::HOMEROOM_NOTES[$ordinal % count(self::HOMEROOM_NOTES)];
    }

    /**
     * The seeded home address for one student ordinal.
     */
    public static function addressFor(int $ordinal): string
    {
        $streets = ['Melati', 'Anggrek', 'Kenanga', 'Dahlia', 'Cempaka', 'Mawar'];

        return 'Jl. '.$streets[$ordinal % count($streets)].' No. '.($ordinal + 1).', Kota Nusantara';
    }

    /**
     * The seeded birth place for one student ordinal.
     */
    public static function birthPlaceFor(int $ordinal): string
    {
        $places = ['Bandung', 'Semarang', 'Surabaya', 'Yogyakarta', 'Jakarta', 'Bogor', 'Malang', 'Solo', 'Depok', 'Bekasi'];

        return $places[$ordinal % count($places)];
    }
}
