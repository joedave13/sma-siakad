export type BelowKkmPayload = {
    total: number;
    evaluated: number;
    rows: {
        student_id: number;
        name: string;
        classroom: string;
        failing_subjects: number;
    }[];
};

export type UnpublishedReportCardsPayload = {
    total: number;
    generated_total: number;
    semesters: {
        semester_id: number;
        term: string;
        academic_year: string;
        count: number;
    }[];
};

export type TodayPeriod = {
    schedule_id: number;
    period_number: number;
    start_time: string;
    end_time: string;
    subject: string;
    classroom: string;
    submitted: boolean;
};

export type PendingSubmission = {
    schedule_id: number;
    date: string;
    date_label: string;
    period_number: number;
    start_time: string;
    end_time: string;
    subject: string;
    classroom: string;
};

export type LatestReportCardPayload = {
    term: string;
    academic_year: string;
    published_at: string;
    view_url: string;
};

export type AbsenceRow = {
    date: string;
    status_label: string;
};
