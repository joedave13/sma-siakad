export type ScheduleDayPeriod = {
    period_number: number;
    start_time: string;
    end_time: string;
    subject: string;
    teacher: string;
};

export type ScheduleDay = {
    day: string;
    label: string;
    periods: ScheduleDayPeriod[];
};

export type SchedulePayload = {
    no_active_year: boolean;
    no_active_semester: boolean;
    not_enrolled: boolean;
    classroom: { id: number; name: string; grade_level: number } | null;
    days: ScheduleDay[];
};

export type AttendanceSummary = {
    sick: number;
    permitted: number;
    absent: number;
    late: number;
    school_days: number;
    present_days: number;
    percentage: number;
};

export type AttendanceDayPeriod = {
    period_number: number;
    subject: string;
    status_label: string;
    note: string | null;
};

export type AttendanceDay = {
    date: string;
    status: string;
    status_label: string;
    is_late: boolean;
    is_overridden: boolean;
    periods: AttendanceDayPeriod[];
};

export type AttendancePayload = {
    no_active_semester: boolean;
    not_enrolled: boolean;
    summary: AttendanceSummary | null;
    days: AttendanceDay[];
};

export type GradesRow = {
    subject: string;
    score: number | null;
    kkm: number;
    predicate: string;
    passed: boolean;
    missing: string[];
};

export type GradesPayload = {
    no_active_semester: boolean;
    not_enrolled: boolean;
    semester: { name: string; academic_year: string } | null;
    classroom: { id: number; name: string } | null;
    rows: GradesRow[];
};

export type ReportCardRow = {
    semester_id: number;
    term: string;
    academic_year: string;
    published_at: string;
    download_url: string;
};

export type ReportCardsPayload = {
    rows: ReportCardRow[];
};

export type ChildOption = {
    id: number;
    name: string;
    classroom: string | null;
};

export type NotificationsShared = {
    unread_count: number;
} | null;

export type NotificationListItem = {
    id: string;
    title: string;
    message: string;
    read: boolean;
    created_at: string;
};

export type NotificationMeta = {
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
