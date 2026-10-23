export type User = {
    id: number;
    name: string;
    phone: string;
    is_active: boolean;
    team_id: number | null;
    avatar?: string;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type StoreRole = 'owner' | 'employee' | 'super-admin';

export type Store = {
    id: number;
    name: string;
};

export type Auth = {
    user: User;
    role: StoreRole | null;
    roleLabel: string | null;
    store: Store | null;
    permissions: string[];
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
