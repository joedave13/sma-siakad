export type PromotionYearRef = {
    id: number;
    name: string;
};

export type PromotionBatchRef = {
    id: number;
    status: 'draft' | 'applied';
    status_label: string;
    applied_at: string | null;
};

export type PromotionSummary = {
    total: number;
    promoted: number;
    retained: number;
    graduated: number;
    unassigned: number;
};

export type PromotionOutcomeValue = 'promote' | 'retain' | 'graduate';

export type PromotionDecisionRow = {
    id: number;
    student_id: number;
    full_name: string | null;
    nisn: string | null;
    from_class: string | null;
    from_grade_level: number | null;
    outcome: PromotionOutcomeValue;
    to_class: string | null;
    to_class_id: number | null;
    is_overridden: boolean;
    override_reason: string | null;
};

export type PromotionTargetClassroom = {
    id: number;
    name: string;
    grade_level: number;
    major: string | null;
};

export type PromotionIndexProps = {
    from: PromotionYearRef | null;
    to: PromotionYearRef | null;
    missing_year_message: string | null;
    batch: PromotionBatchRef | null;
    summary: PromotionSummary | null;
    decisions: PromotionDecisionRow[];
    target_classes: PromotionTargetClassroom[];
    blockers: string[];
};
