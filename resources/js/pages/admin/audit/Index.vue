<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import auditRoutes from '@/routes/admin/audit';
import type { AuditLogMeta, AuditLogRecord } from '@/types/settings';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Log Audit',
                href: auditRoutes.index(),
            },
        ],
    },
});

type Props = {
    logs: AuditLogRecord[];
    meta: AuditLogMeta;
    actions: string[];
    filter: { action: string };
};

const props = defineProps<Props>();

const fieldLabels: Record<string, string> = {
    name: 'nama',
    code: 'kode',
    kkm: 'kkm',
    number: 'nomor',
    start_date: 'tanggal mulai',
    end_date: 'tanggal selesai',
    start_time: 'jam mulai',
    end_time: 'jam selesai',
    grade_level: 'tingkat kelas',
    major: 'jurusan',
    is_active: 'aktif',
    status: 'status',
    address: 'alamat',
    city: 'kota',
    npsn: 'npsn',
    postal_code: 'kode pos',
    phone: 'telepon',
    email: 'email',
    headmaster_name: 'nama kepala sekolah',
    headmaster_nip: 'nip kepala sekolah',
    logo_path: 'logo',
    headmaster_signature_path: 'tanda tangan',
    academic_year_id: 'tahun ajaran',
    semester_id: 'semester',
    subject_id: 'mata pelajaran',
    teacher_id: 'guru',
    student_id: 'siswa',
    classroom_id: 'ruang kelas',
    homeroom_teacher_id: 'wali kelas',
    teaching_assignment_id: 'penugasan mengajar',
    lesson_period_id: 'jam pelajaran',
    day_of_week: 'hari',
    username: 'nama pengguna',
    role: 'peran',
    grades_locked_at: 'kunci nilai',
    grades_locked_reason: 'alasan penguncian',
};

const fieldLabel = (key: string): string =>
    fieldLabels[key] ?? key.replace(/_/g, ' ');

const formatValue = (value: unknown): string => {
    if (value === null || value === undefined || value === '') {
        return '-';
    }
    if (typeof value === 'boolean') {
        return value ? 'Ya' : 'Tidak';
    }

    return String(value);
};

const changeLines = (row: AuditLogRecord): string[] => {
    const oldValues = row.old_values;
    const newValues = row.new_values;

    if (oldValues === null && newValues === null) {
        return [];
    }

    if (oldValues === null && newValues !== null) {
        return Object.entries(newValues).map(
            ([key, value]) => `${fieldLabel(key)}: ${formatValue(value)}`,
        );
    }

    if (newValues === null && oldValues !== null) {
        return Object.entries(oldValues).map(
            ([key, value]) => `${fieldLabel(key)}: ${formatValue(value)}`,
        );
    }

    const keys = new Set([
        ...Object.keys(oldValues as Record<string, unknown>),
        ...Object.keys(newValues as Record<string, unknown>),
    ]);

    return [...keys].map((key) => {
        const before = (oldValues as Record<string, unknown>)[key];
        const after = (newValues as Record<string, unknown>)[key];

        return `${fieldLabel(key)}: ${formatValue(before)} -> ${formatValue(after)}`;
    });
};

const columns = [
    { key: 'time', label: 'Waktu', className: 'w-40' },
    { key: 'user', label: 'Pengguna', className: 'w-44' },
    { key: 'action', label: 'Aksi', className: 'w-56' },
    { key: 'object', label: 'Objek', className: 'w-56' },
    { key: 'changes', label: 'Perubahan' },
];

const emptyTitle = computed(() =>
    props.filter.action === ''
        ? 'Belum ada aktivitas tercatat'
        : 'Tidak ada aktivitas untuk aksi ini',
);

const emptyDescription = computed(() =>
    props.filter.action === ''
        ? 'Perubahan data oleh pengguna aplikasi, seperti identitas sekolah dan data akademik, akan tampil di sini beserta pelakunya.'
        : `Tidak ada catatan dengan aksi ${props.filter.action}. Pilih "Semua aksi" untuk melihat seluruh aktivitas.`,
);

const changeFilter = (event: Event): void => {
    const value = (event.target as HTMLSelectElement).value;

    router.get(
        auditRoutes.index.url(value === '' ? {} : { query: { action: value } }),
        {},
        { preserveScroll: true, preserveState: true },
    );
};

const visit = (url: string): void => {
    router.get(url, {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Log Audit" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader
            title="Log Audit"
            description="Jejak perubahan data penting yang dilakukan pengguna aplikasi."
        />

        <div class="mb-4 grid max-w-xs gap-2">
            <Label for="audit-action">Aksi</Label>
            <select
                id="audit-action"
                :value="filter.action"
                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                data-test="audit-filter"
                @change="changeFilter"
            >
                <option value="">Semua aksi</option>
                <option v-for="action in actions" :key="action" :value="action">
                    {{ action }}
                </option>
            </select>
        </div>

        <DataTable
            :columns="columns"
            :rows="logs"
            :row-key="(row: AuditLogRecord) => row.id"
            :empty-title="emptyTitle"
            :empty-description="emptyDescription"
        >
            <template #cell="{ row, column }">
                <span
                    v-if="column.key === 'time'"
                    class="whitespace-nowrap text-muted-foreground"
                    >{{ row.time }}</span
                >
                <span v-else-if="column.key === 'user'">{{ row.user }}</span>
                <Badge
                    v-else-if="column.key === 'action'"
                    variant="secondary"
                    class="font-mono text-xs"
                    >{{ row.action }}</Badge
                >
                <span
                    v-else-if="column.key === 'object'"
                    class="text-muted-foreground"
                    >{{ row.object }}</span
                >
                <div
                    v-else-if="column.key === 'changes'"
                    class="space-y-0.5 text-xs text-muted-foreground"
                >
                    <template v-if="changeLines(row).length > 0">
                        <div
                            v-for="line in changeLines(row)"
                            :key="line"
                            class="line-clamp-2"
                        >
                            {{ line }}
                        </div>
                    </template>
                    <span v-else>-</span>
                </div>
            </template>
        </DataTable>

        <div
            v-if="meta.total > 0"
            class="mt-4 flex flex-wrap items-center justify-between gap-3"
        >
            <p class="text-sm text-muted-foreground">
                Halaman {{ meta.current_page }} dari {{ meta.last_page }} ({{
                    meta.total
                }}
                catatan)
            </p>
            <div class="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    class="min-h-11 md:min-h-0"
                    :disabled="meta.prev_page_url === null"
                    data-test="audit-prev"
                    @click="meta.prev_page_url && visit(meta.prev_page_url)"
                >
                    Sebelumnya
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    class="min-h-11 md:min-h-0"
                    :disabled="meta.next_page_url === null"
                    data-test="audit-next"
                    @click="meta.next_page_url && visit(meta.next_page_url)"
                >
                    Berikutnya
                </Button>
            </div>
        </div>
    </div>
</template>
