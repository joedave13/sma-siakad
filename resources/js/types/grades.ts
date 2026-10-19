export type GradeComponentValue = 'assignment' | 'quiz' | 'midterm' | 'final';

export type GradeAssignmentSummary = {
    id: number;
    subject: string;
    kkm: number;
    classroom: string;
    student_count: number;
};

export type GradesIndexProps = {
    semester: { name: string; academic_year: string } | null;
    assignments: GradeAssignmentSummary[];
    profileMissing: boolean;
};

export type GradeCell = {
    component: GradeComponentValue;
    title: string | null;
    score: string | null;
    updated_at: string;
};

export type GridStudent = {
    id: number;
    nisn: string;
    full_name: string;
    cells: GradeCell[];
    remark: string | null;
    final_score: number | null;
};

export type GradeGridProps = {
    assignment: {
        id: number;
        subject: string;
        kkm: number;
        classroom: string;
    };
    semester: {
        name: string;
        academic_year: string;
        locked: boolean;
    };
    columns: { assignment: string[]; quiz: string[] };
    students: GridStudent[];
};

export type GradeColumn = {
    component: GradeComponentValue;
    title: string | null;
    key: string;
};

export type GradeCellState = {
    score: string;
    expected: string | null;
};

export type GradeEntryPayload = {
    student_id: number;
    component: GradeComponentValue;
    title: string | null;
    score: string | null;
    expected_updated_at: string | null;
};

export type GradeRemarkPayload = {
    student_id: number;
    remark: string | null;
};

export type HomeroomClassroom = {
    id: number;
    name: string;
    grade_level: number;
    student_count: number;
    ready: boolean;
};

export type HomeroomIndexProps = {
    classrooms: HomeroomClassroom[];
    profileMissing: boolean;
    noActiveYear: boolean;
    noActiveSemester: boolean;
};

export type HomeroomGrade = {
    subject: string;
    score: number | null;
    kkm: number;
    predicate: string;
    passed: boolean;
    remark: string | null;
};

export type HomeroomStudent = {
    id: number;
    nisn: string;
    full_name: string;
    grades: HomeroomGrade[];
    missing: string[];
    homeroom_note: string | null;
};

export type HomeroomShowProps = {
    classroom: {
        id: number;
        name: string;
        grade_level: number;
    };
    semester: {
        id: number;
        name: string;
        academic_year: string;
    } | null;
    students: HomeroomStudent[];
    ready: boolean;
};
