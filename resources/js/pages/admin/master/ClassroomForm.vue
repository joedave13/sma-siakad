<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import ClassroomController from '@/actions/App/Http/Controllers/Admin/Master/ClassroomController';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import classrooms from '@/routes/admin/master/classrooms';
import type {
    ClassroomRecord,
    MasterOption,
    TeacherOption,
} from '@/types/master';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Ruang Kelas',
                href: classrooms.index(),
            },
        ],
    },
});

type Props = {
    academicYears: MasterOption[];
    teachers: TeacherOption[];
    classroom: ClassroomRecord | null;
};

defineProps<Props>();

const gradeLevels = [10, 11, 12];

const majors = [
    { value: 'ipa', label: 'IPA' },
    { value: 'ips', label: 'IPS' },
];
</script>

<template>
    <Head :title="classroom ? 'Ubah Ruang Kelas' : 'Tambah Ruang Kelas'" />

    <div class="flex h-full flex-1 flex-col">
        <PageHeader
            :title="classroom ? 'Ubah Ruang Kelas' : 'Tambah Ruang Kelas'"
        />

        <div class="max-w-2xl">
            <Form
                v-bind="
                    classroom
                        ? ClassroomController.update.form({
                              classroom: classroom.id,
                          })
                        : ClassroomController.store.form()
                "
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="academic_year_id">Tahun ajaran</Label>
                    <select
                        id="academic_year_id"
                        name="academic_year_id"
                        :value="classroom?.academic_year_id"
                        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        required
                        data-test="classroom-academic-year"
                    >
                        <option
                            v-for="year in academicYears"
                            :key="year.id"
                            :value="year.id"
                        >
                            {{ year.name }}
                        </option>
                    </select>
                    <InputError
                        class="mt-2"
                        :message="errors.academic_year_id"
                    />
                </div>

                <div class="grid items-start gap-6 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="name">Nama</Label>
                        <Input
                            id="name"
                            name="name"
                            :default-value="classroom?.name"
                            placeholder="XII-IPA-1"
                            required
                            data-test="classroom-name"
                        />
                        <InputError class="mt-2" :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="grade_level">Tingkat</Label>
                        <select
                            id="grade_level"
                            name="grade_level"
                            :value="classroom?.grade_level"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            required
                            data-test="classroom-grade-level"
                        >
                            <option
                                v-for="level in gradeLevels"
                                :key="level"
                                :value="level"
                            >
                                {{ level }}
                            </option>
                        </select>
                        <InputError
                            class="mt-2"
                            :message="errors.grade_level"
                        />
                    </div>
                </div>

                <div class="grid items-start gap-6 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="major">Jurusan</Label>
                        <select
                            id="major"
                            name="major"
                            :value="classroom?.major ?? ''"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            data-test="classroom-major"
                        >
                            <option value="">Tanpa jurusan</option>
                            <option
                                v-for="major in majors"
                                :key="major.value"
                                :value="major.value"
                            >
                                {{ major.label }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="errors.major" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="homeroom_teacher_id">Wali kelas</Label>
                        <select
                            id="homeroom_teacher_id"
                            name="homeroom_teacher_id"
                            :value="classroom?.homeroom_teacher_id ?? ''"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            data-test="classroom-homeroom-teacher"
                        >
                            <option value="">Belum ditentukan</option>
                            <option
                                v-for="teacher in teachers"
                                :key="teacher.id"
                                :value="teacher.id"
                            >
                                {{ teacher.full_name }}
                            </option>
                        </select>
                        <InputError
                            class="mt-2"
                            :message="errors.homeroom_teacher_id"
                        />
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <Button
                        :disabled="processing"
                        class="min-h-11 md:min-h-0"
                        data-test="save-classroom"
                    >
                        Simpan
                    </Button>
                    <Button
                        variant="outline"
                        as-child
                        class="min-h-11 md:min-h-0"
                    >
                        <Link :href="classrooms.index()">Batal</Link>
                    </Button>
                </div>
            </Form>
        </div>
    </div>
</template>
