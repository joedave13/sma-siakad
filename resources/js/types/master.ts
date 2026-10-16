export type MasterOption = {
    id: number;
    name: string;
};

export type AcademicYearRecord = {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    is_active: boolean;
};

export type SemesterRecord = {
    id: number;
    academic_year_id: number;
    name: 'ganjil' | 'genap';
    start_date: string | null;
    end_date: string | null;
    is_active: boolean;
    academic_year?: MasterOption;
};

export type LessonPeriodRecord = {
    id: number;
    number: number;
    start_time: string;
    end_time: string;
};

export type SubjectRecord = {
    id: number;
    code: string;
    name: string;
    kkm: number;
};

export type TeacherOption = {
    id: number;
    full_name: string;
};

export type ClassroomRecord = {
    id: number;
    academic_year_id: number;
    name: string;
    grade_level: number;
    major: 'ipa' | 'ips' | null;
    homeroom_teacher_id: number | null;
    academic_year?: MasterOption;
    homeroom_teacher?: TeacherOption;
};
