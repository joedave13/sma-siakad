export type SchoolProfileRecord = {
    name: string;
    npsn: string | null;
    address: string;
    city: string;
    postal_code: string | null;
    phone: string | null;
    email: string | null;
    headmaster_name: string;
    headmaster_nip: string;
    logo_path: string | null;
    headmaster_signature_path: string | null;
    logo_url: string | null;
    signature_url: string | null;
};

export type AuditLogRecord = {
    id: number;
    action: string;
    time: string;
    user: string;
    object: string;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
};

export type AuditLogMeta = {
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
