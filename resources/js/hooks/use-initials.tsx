import { useCallback } from 'react';

export type GetInitialsFn = (fullName: string) => string;

function getInitial(name: string): string {
    return Array.from(name)[0] ?? '';
}

export function useInitials(): GetInitialsFn {
    return useCallback((identifier: string): string => {
        // Usernames are identity numbers (NIS/NIP/NIK), not names — take the
        // first two characters rather than splitting on spaces.
        const trimmed = identifier.trim();

        if (trimmed.length === 0) {
            return '';
        }

        if (Array.from(trimmed).length === 1) {
            return getInitial(trimmed).toUpperCase();
        }

        return Array.from(trimmed)
            .slice(0, 2)
            .join('')
            .toUpperCase();
    }, []);
}
