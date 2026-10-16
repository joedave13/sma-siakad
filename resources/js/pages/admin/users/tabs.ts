import type { InertiaLinkProps } from '@inertiajs/vue3';
import guardians from '@/routes/admin/users/guardians';
import accountImport from '@/routes/admin/users/import';
import students from '@/routes/admin/users/students';
import teachers from '@/routes/admin/users/teachers';

export type AccountTab = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export const accountTabs: AccountTab[] = [
    { title: 'Siswa', href: students.index() },
    { title: 'Guru', href: teachers.index() },
    { title: 'Orang Tua', href: guardians.index() },
    { title: 'Impor', href: accountImport.create() },
];
