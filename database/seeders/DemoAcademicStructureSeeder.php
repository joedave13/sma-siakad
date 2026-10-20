<?php

namespace Database\Seeders;

use App\Enums\Gender;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\ClassEnrollment;
use App\Models\Classroom;
use App\Models\Guardian;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoAcademicStructureSeeder extends Seeder
{
    /**
     * Seed academic years, semesters, subjects, teachers, classrooms,
     * students, guardians, and enrollments for both demo years.
     */
    public function run(): void
    {
        $current = AcademicYear::query()->firstOrCreate(
            ['name' => DemoData::CURRENT_YEAR],
            ['start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true],
        );
        $next = AcademicYear::query()->firstOrCreate(
            ['name' => DemoData::NEXT_YEAR],
            ['start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'is_active' => false],
        );

        foreach (DemoData::SEMESTERS as $semester) {
            Semester::query()->firstOrCreate(
                ['academic_year_id' => $current->getKey(), 'name' => $semester['name']],
                ['start_date' => $semester['start'], 'end_date' => $semester['end'], 'is_active' => $semester['active']],
            );
        }

        foreach (DemoData::SUBJECTS as $subject) {
            Subject::query()->firstOrCreate(
                ['code' => $subject['code']],
                ['name' => $subject['name'], 'kkm' => $subject['kkm']],
            );
        }

        $password = Hash::make(DemoData::PASSWORD);
        $teachers = $this->seedTeachers($password);
        $this->seedClassrooms($current, $next, $teachers);
        $studentsByOrdinal = $this->seedStudents($password);
        $this->seedEnrollments($current, $studentsByOrdinal);
        $this->seedGuardians($password, $studentsByOrdinal);
    }

    /**
     * Create the demo teacher accounts and profiles in TEACHERS order.
     *
     * @param  string  $password  shared bcrypt hash for every demo account
     * @return list<Teacher>
     */
    private function seedTeachers(string $password): array
    {
        $teachers = [];

        foreach (DemoData::TEACHERS as $teacher) {
            $user = User::query()->firstOrCreate(
                ['username' => $teacher['nip']],
                [
                    'name' => $teacher['full_name'],
                    'role' => Role::Teacher->value,
                    'is_active' => true,
                    'must_change_password' => false,
                    'password' => $password,
                ],
            );

            $teachers[] = Teacher::query()->firstOrCreate(
                ['nip' => $teacher['nip']],
                [
                    'user_id' => $user->getKey(),
                    'full_name' => $teacher['full_name'],
                    'phone' => $teacher['phone'],
                ],
            );
        }

        return $teachers;
    }

    /**
     * Create the demo classrooms in both academic years with homeroom teachers.
     *
     * @param  list<Teacher>  $teachers
     */
    private function seedClassrooms(AcademicYear $current, AcademicYear $next, array $teachers): void
    {
        foreach ([$current, $next] as $year) {
            foreach (DemoData::CLASSROOMS as $classroom) {
                Classroom::query()->firstOrCreate(
                    ['academic_year_id' => $year->getKey(), 'name' => $classroom['name']],
                    [
                        'grade_level' => $classroom['grade_level'],
                        'major' => $classroom['major'],
                        'homeroom_teacher_id' => $teachers[$classroom['homeroom']]->getKey(),
                    ],
                );
            }
        }
    }

    /**
     * Create the demo student accounts and profiles keyed by ordinal.
     *
     * @param  string  $password  shared bcrypt hash for every demo account
     * @return array<int, Student>
     */
    private function seedStudents(string $password): array
    {
        $students = [];

        foreach (array_keys(DemoData::STUDENTS) as $ordinal) {
            $definition = DemoData::STUDENTS[$ordinal];
            $nisn = DemoData::nisnFor($ordinal);

            $user = User::query()->firstOrCreate(
                ['username' => $nisn],
                [
                    'name' => $definition['full_name'],
                    'role' => Role::Student->value,
                    'is_active' => true,
                    'must_change_password' => false,
                    'password' => $password,
                ],
            );

            $students[$ordinal] = Student::query()->firstOrCreate(
                ['nisn' => $nisn],
                [
                    'user_id' => $user->getKey(),
                    'nis' => DemoData::nisFor($ordinal),
                    'full_name' => $definition['full_name'],
                    'gender' => Gender::from($definition['gender']),
                    'birth_date' => $definition['birth_date'],
                    'birth_place' => DemoData::birthPlaceFor($ordinal),
                    'address' => DemoData::addressFor($ordinal),
                    'status' => StudentStatus::Active,
                ],
            );
        }

        return $students;
    }

    /**
     * Enroll every demo student into their classroom for the current year.
     *
     * @param  array<int, Student>  $studentsByOrdinal
     */
    private function seedEnrollments(AcademicYear $current, array $studentsByOrdinal): void
    {
        $classrooms = Classroom::query()
            ->where('academic_year_id', $current->getKey())
            ->get()
            ->keyBy('name');

        foreach (array_keys(DemoData::STUDENTS) as $ordinal) {
            $definition = DemoData::STUDENTS[$ordinal];
            $classroom = $classrooms->get($definition['classroom']);

            if ($classroom === null) {
                continue;
            }

            ClassEnrollment::query()->firstOrCreate(
                [
                    'student_id' => $studentsByOrdinal[$ordinal]->getKey(),
                    'academic_year_id' => $current->getKey(),
                ],
                ['classroom_id' => $classroom->getKey()],
            );
        }
    }

    /**
     * Create the demo guardian accounts and link them to their children.
     *
     * @param  string  $password  shared bcrypt hash for every demo account
     * @param  array<int, Student>  $studentsByOrdinal
     */
    private function seedGuardians(string $password, array $studentsByOrdinal): void
    {
        foreach (DemoData::GUARDIANS as $guardian) {
            $user = User::query()->firstOrCreate(
                ['username' => $guardian['phone']],
                [
                    'name' => $guardian['full_name'],
                    'role' => Role::Parent->value,
                    'is_active' => true,
                    'must_change_password' => false,
                    'password' => $password,
                ],
            );

            $profile = Guardian::query()->firstOrCreate(
                ['phone' => $guardian['phone']],
                ['user_id' => $user->getKey(), 'full_name' => $guardian['full_name']],
            );

            foreach ($guardian['students'] as $ordinal) {
                $profile->students()->syncWithoutDetaching([
                    $studentsByOrdinal[$ordinal]->getKey() => ['relationship' => $guardian['relationship']],
                ]);
            }
        }
    }
}
