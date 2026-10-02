import { router, usePage } from '@inertiajs/react';
import type { HTMLAttributes } from 'react';
import LocaleController from '@/actions/App/Http/Controllers/Settings/LocaleController';
import { cn } from '@/lib/utils';

export default function LanguageSwitcher({
    className = '',
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    const { locale, locales } = usePage().props;

    return (
        <div
            className={cn(
                'inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800',
                className,
            )}
            {...props}
        >
            {locales.map(({ value, label }) => (
                <button
                    key={value}
                    type="button"
                    onClick={() =>
                        router.put(
                            LocaleController.update().url,
                            { locale: value },
                            { preserveScroll: true },
                        )
                    }
                    className={cn(
                        'flex items-center rounded-md px-3.5 py-1.5 text-sm transition-colors',
                        locale === value
                            ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
                            : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
                    )}
                >
                    {label}
                </button>
            ))}
        </div>
    );
}
