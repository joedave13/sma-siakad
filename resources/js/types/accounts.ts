export type GenderValue = 'male' | 'female';

export type StudentStatusValue =
    | 'active'
    | 'graduated'
    | 'transferred'
    | 'dropped';

export type StudentAccountRecord = {
    id: number;
    user_id: number;
    nisn: string;
    nis: string | null;
    full_name: string;
    gender: GenderValue;
    birth_date: string | null;
    birth_place: string | null;
    address: string | null;
    status: StudentStatusValue;
    is_active: boolean;
};

export type TeacherAccountRecord = {
    id: number;
    user_id: number;
    nip: string;
    full_name: string;
    phone: string | null;
    is_active: boolean;
};

export type GuardianChildRecord = {
    id: number;
    nisn: string;
    full_name: string;
    relationship: string;
};

export type GuardianAccountRecord = {
    id: number;
    user_id: number;
    telepon: string;
    full_name: string;
    is_active: boolean;
    children: GuardianChildRecord[];
};

export type StudentOption = {
    id: number;
    nisn: string;
    full_name: string;
};
