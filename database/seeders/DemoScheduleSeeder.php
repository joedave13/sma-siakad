<?php

namespace Database\Seeders;

use App\Enums\DayOfWeek;
use App\Models\Classroom;
use App\Models\LessonPeriod;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use RuntimeException;

class DemoScheduleSeeder extends Seeder
{
    /**
     * Seed every teaching assignment with two weekly sessions, placed
     * collision-free for teachers and classrooms across the school week.
     */
    public function run(): void
    {
        $semester = Semester::query()->where('is_active', true)->firstOrFail();
        $periods = LessonPeriod::query()->orderBy('number')->get();
        $classrooms = Classroom::query()
            ->where('academic_year_id', $semester->academic_year_id)
            ->get()
            ->keyBy('name');
        $subjects = Subject::query()->get()->keyBy('code');
        $teachers = Teacher::query()->get()->keyBy('nip');

        $ring = $this->dayRing();
        $taken = [];
        $assignments = $this->assignments($semester, $classrooms, $subjects, $teachers);

        foreach ($assignments as $index => $entry) {
            for ($session = 0; $session < 2; $session++) {
                $preferredDay = $ring[($index + $session) % count($ring)];

                [$day, $period] = $this->findSlot(
                    $preferredDay,
                    $entry['teacher']->getKey(),
                    $entry['classroom']->getKey(),
                    $index * 3 + $session * 7,
                    $taken,
                    $ring,
                    $periods,
                );

                Schedule::query()->firstOrCreate([
                    'teaching_assignment_id' => $entry['assignment']->getKey(),
                    'day_of_week' => $day,
                    'lesson_period_id' => $period->getKey(),
                ]);

                $taken[$day->value.':'.$period->number.':t'.$entry['teacher']->getKey()] = true;
                $taken[$day->value.':'.$period->number.':c'.$entry['classroom']->getKey()] = true;
            }
        }
    }

    /**
     * The school week starting at today (when it is a school day).
     *
     * @return list<DayOfWeek>
     */
    private function dayRing(): array
    {
        $days = DayOfWeek::cases();
        $today = DayOfWeek::fromDate(Date::today());

        if ($today === null) {
            return $days;
        }

        $start = (int) array_search($today, $days, true);

        return array_merge(array_slice($days, $start), array_slice($days, 0, $start));
    }

    /**
     * The flat assignment list in classroom-major order with their owners.
     *
     * @param  Collection<string, Classroom>  $classrooms
     * @param  Collection<string, Subject>  $subjects
     * @param  Collection<string, Teacher>  $teachers
     * @return list<array{assignment: TeachingAssignment, classroom: Classroom, teacher: Teacher}>
     */
    private function assignments(Semester $semester, Collection $classrooms, Collection $subjects, Collection $teachers): array
    {
        $entries = [];

        foreach (DemoData::CLASSROOMS as $definition) {
            $classroom = $classrooms->get($definition['name']);

            if ($classroom === null) {
                continue;
            }

            foreach (DemoData::CLASSROOM_SUBJECTS[$definition['name']] as $code) {
                $owner = DemoData::TEACHERS[DemoData::SUBJECT_OWNERS[$code]];
                $subject = $subjects->get($code);
                $teacher = $teachers->get($owner['nip']);

                if ($subject === null || $teacher === null) {
                    continue;
                }

                $entries[] = [
                    'assignment' => TeachingAssignment::query()->firstOrCreate([
                        'semester_id' => $semester->getKey(),
                        'classroom_id' => $classroom->getKey(),
                        'subject_id' => $subject->getKey(),
                        'teacher_id' => $teacher->getKey(),
                    ]),
                    'classroom' => $classroom,
                    'teacher' => $teacher,
                ];
            }
        }

        return $entries;
    }

    /**
     * The first period free for both the teacher and the classroom, walking
     * forward through the week from the preferred day.
     *
     * @param  array<string, true>  $taken
     * @param  list<DayOfWeek>  $ring
     * @param  Collection<int, LessonPeriod>  $periods
     * @return array{0: DayOfWeek, 1: LessonPeriod}
     */
    private function findSlot(
        DayOfWeek $preferredDay,
        int $teacherId,
        int $classroomId,
        int $offset,
        array $taken,
        array $ring,
        Collection $periods,
    ): array {
        $dayCount = count($ring);
        $periodCount = $periods->count();
        $dayStart = (int) array_search($preferredDay, $ring, true);

        for ($dayStep = 0; $dayStep < $dayCount; $dayStep++) {
            $day = $ring[($dayStart + $dayStep) % $dayCount];

            for ($periodStep = 0; $periodStep < $periodCount; $periodStep++) {
                $period = $periods[($offset + $periodStep) % $periodCount];

                if (isset($taken[$day->value.':'.$period->number.':t'.$teacherId])
                    || isset($taken[$day->value.':'.$period->number.':c'.$classroomId])) {
                    continue;
                }

                return [$day, $period];
            }
        }

        throw new RuntimeException('Tidak tersedia slot jadwal bebas untuk penugasan ini.');
    }
}
