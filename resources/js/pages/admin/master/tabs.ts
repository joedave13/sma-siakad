import type { InertiaLinkProps } from '@inertiajs/vue3';
import academicYears from '@/routes/admin/master/academic-years';
import classrooms from '@/routes/admin/master/classrooms';
import lessonPeriods from '@/routes/admin/master/lesson-periods';
import semesters from '@/routes/admin/master/semesters';
import subjects from '@/routes/admin/master/subjects';

export type MasterTab = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export const masterTabs: MasterTab[] = [
    { title: 'Tahun Ajaran', href: academicYears.index() },
    { title: 'Semester', href: semesters.index() },
    { title: 'Jam Pelajaran', href: lessonPeriods.index() },
    { title: 'Mata Pelajaran', href: subjects.index() },
    { title: 'Ruang Kelas', href: classrooms.index() },
];
