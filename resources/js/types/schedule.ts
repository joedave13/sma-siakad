export type ScheduleClassroomOption = {
    id: number;
    name: string;
};

export type ActiveAcademicYear = {
    id: number;
    name: string;
};

export type ActiveSemesterContext = {
    id: number;
    name: string;
    academic_year: string;
};

export type EnrolledStudent = {
    enrollment_id: number;
    id: number;
    nisn: string;
    full_name: string;
};

export type EnrollmentClassroom = {
    id: number;
    name: string;
    students: EnrolledStudent[];
};

export type UnenrolledStudent = {
    id: number;
    nisn: string;
    full_name: string;
};

export type SubjectOption = {
    id: number;
    code: string;
    name: string;
};

export type TeachingAssignmentRecord = {
    id: number;
    classroom_id: number;
    classroom_name: string;
    subject_id: number;
    subject_name: string;
    teacher_id: number;
    teacher_name: string;
};

export type ScheduleAssignmentRecord = {
    id: number;
    classroom_id: number;
    classroom_name: string;
    subject_name: string;
    teacher_name: string;
};

export type ScheduleSlot = {
    id: number;
    teaching_assignment_id: number;
    day_of_week: string;
    lesson_period_id: number;
};

export type LessonPeriodOption = {
    id: number;
    number: number;
    start_time: string;
    end_time: string;
};

export type DayOption = {
    value: string;
    label: string;
};
