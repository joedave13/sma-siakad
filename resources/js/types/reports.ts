export type SemesterRecord = {
    id: number;
    name: string;
    academic_year: string;
    is_active: boolean;
    locked: boolean;
    locked_at: string | null;
    published: boolean;
    published_at: string | null;
};

export type GenerationSummary = {
    total: number;
    done: number;
    pending: number;
    failed: number;
    without_card: number;
};

export type GenerationRow = {
    student_id: number;
    full_name: string;
    nisn: string;
    status: 'pending' | 'failed' | 'done' | 'none';
    error: string | null;
    generated_at: string | null;
    revised: boolean;
};

export type ReportIndexProps = {
    semesters: SemesterRecord[];
};

export type ReportShowProps = {
    semester: SemesterRecord;
    generation: {
        summary: GenerationSummary;
        rows: GenerationRow[];
    };
};
