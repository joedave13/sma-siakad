import type { InertiaLinkProps } from '@inertiajs/vue3';
import assignments from '@/routes/admin/schedule/assignments';
import enrollments from '@/routes/admin/schedule/enrollments';
import weekly from '@/routes/admin/schedule/weekly';

export type ScheduleTab = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export const scheduleTabs: ScheduleTab[] = [
    { title: 'Penempatan Siswa', href: enrollments.index() },
    { title: 'Penugasan Mengajar', href: assignments.index() },
    { title: 'Jadwal Mingguan', href: weekly.index() },
];
