export type AttendanceStatusOption = {
    value: string;
    label: string;
};

export type AttendanceRosterStudent = {
    id: number;
    nisn: string;
    full_name: string;
};

export type AttendanceSessionState = {
    submitted: boolean;
    submitted_at: string | null;
    submitted_by: string | null;
    editable: boolean;
    edit_deadline: string | null;
    lock_message: string | null;
    entries: Record<string, string>;
};

export type AttendanceSlot = {
    schedule_id: number;
    period_number: number;
    start_time: string;
    end_time: string;
    subject: string;
    classroom: string;
};

export type AttendanceSlotWithState = AttendanceSlot & {
    state: 'none' | 'submitted' | 'locked';
    submitted_at: string | null;
};

export type AttendanceSheetProps = {
    date: string;
    slot: AttendanceSlot;
    roster: AttendanceRosterStudent[];
    session: AttendanceSessionState;
    status_options: AttendanceStatusOption[];
};

export type MissingSession = {
    schedule_id: number;
    period_number: number;
    start_time: string;
    end_time: string;
    subject: string;
    classroom: string;
    teacher: string;
    date: string;
};
