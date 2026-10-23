import { usePage } from '@inertiajs/react';

export type UsePermissionsReturn = {
    can: (permission: string) => boolean;
};

export function usePermissions(): UsePermissionsReturn {
    const { auth } = usePage().props;

    return {
        can: (permission: string) =>
            auth.permissions?.includes(permission) ?? false,
    };
}
