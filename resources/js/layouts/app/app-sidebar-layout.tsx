import { Link, usePage } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import SchoolClassController from '@/actions/App/Http/Controllers/Classes/SchoolClassController';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const { incomplete_class_count } = usePage().props as {
        incomplete_class_count?: number;
    };

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="min-w-0 overflow-x-clip">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {typeof incomplete_class_count === 'number' && incomplete_class_count > 0 && (
                    <div className="bg-amber-500/10 text-amber-900 dark:text-amber-200 border-b border-amber-500/20 px-4 py-2 text-sm flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <AlertTriangle className="h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
                            <span>
                                Ada {incomplete_class_count} kelas belum diatur tingkat kelasnya (grade level).
                            </span>
                        </div>
                        <Link
                            href={SchoolClassController.index().url}
                            className="font-medium underline hover:text-amber-800 dark:hover:text-amber-100"
                        >
                            Lengkapi data kelas
                        </Link>
                    </div>
                )}
                {children}
            </AppContent>
        </AppShell>
    );
}
