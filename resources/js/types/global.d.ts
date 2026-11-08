import type { Auth } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            locale: string;
            incomplete_class_count?: number;
            pending_config_warnings?: {
                id: string;
                message: string;
                action_label: string;
                action_url: string;
            }[];
            locales: { value: string; label: string }[];
            translations: Record<string, unknown>;
            [key: string]: unknown;
        };
    }
}
