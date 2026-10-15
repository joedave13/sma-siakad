export type UserRole = 'admin' | 'teacher' | 'student' | 'parent';

export type User = {
    id: number;
    name: string;
    username: string;
    email: string | null;
    role: UserRole;
    avatar?: string;
};

export type Auth = {
    user: User | null;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
