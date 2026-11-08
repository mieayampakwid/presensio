import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Filter } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';

type AuditLogRow = {
    id: number;
    user_id: number | null;
    user: { id: number; username: string } | null;
    action: string;
    auditable_type: string;
    auditable_id: number;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    reason: string | null;
    ip_address: string | null;
    created_at: string;
};

type Paginator = {
    data: AuditLogRow[];
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page: number;
    last_page: number;
    total: number;
};

type Filters = {
    auditable_type?: string;
    auditable_id?: number | string;
    user_id?: number | string;
    from?: string;
    to?: string;
};

type Props = {
    logs: Paginator;
    filters: Filters;
};

export default function AuditLogsIndex({ logs, filters }: Props) {
    const { t, locale } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            { title: t('audit_logs.breadcrumbs.admin') },
            { title: t('audit_logs.breadcrumbs.audit_logs') },
        ],
    });

    const [filterForm, setFilterForm] = useState<Filters>({
        auditable_type: filters.auditable_type ?? '',
        auditable_id: filters.auditable_id ?? '',
        user_id: filters.user_id ?? '',
        from: filters.from ?? '',
        to: filters.to ?? '',
    });

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        const query: Record<string, string> = {};
        if (filterForm.auditable_type)
            query.auditable_type = filterForm.auditable_type;
        if (filterForm.auditable_id)
            query.auditable_id = String(filterForm.auditable_id);
        if (filterForm.user_id) query.user_id = String(filterForm.user_id);
        if (filterForm.from) query.from = filterForm.from;
        if (filterForm.to) query.to = filterForm.to;

        router.get('/admin/audit-logs', query, { preserveState: true });
    };

    const handleReset = () => {
        setFilterForm({
            auditable_type: '',
            auditable_id: '',
            user_id: '',
            from: '',
            to: '',
        });
        router.get('/admin/audit-logs');
    };

    const shortType = (type: string) => {
        const parts = type.split('\\');
        return parts[parts.length - 1] ?? type;
    };

    return (
        <>
            <Head title={t('audit_logs.title')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('audit_logs.title')}
                    description={t('audit_logs.description')}
                />

                <form
                    onSubmit={handleFilter}
                    className="bg-card grid grid-cols-1 gap-3 rounded-lg border p-4 md:grid-cols-5"
                >
                    <div>
                        <label className="text-muted-foreground mb-1 block text-xs font-medium">
                            {t('audit_logs.filters.entity_type')}
                        </label>
                        <Input
                            placeholder={t(
                                'audit_logs.filters.entity_type_placeholder',
                            )}
                            value={filterForm.auditable_type}
                            onChange={(e) =>
                                setFilterForm({
                                    ...filterForm,
                                    auditable_type: e.target.value,
                                })
                            }
                            className="text-xs"
                        />
                    </div>
                    <div>
                        <label className="text-muted-foreground mb-1 block text-xs font-medium">
                            {t('audit_logs.filters.entity_id')}
                        </label>
                        <Input
                            type="number"
                            placeholder={t(
                                'audit_logs.filters.entity_id_placeholder',
                            )}
                            value={filterForm.auditable_id}
                            onChange={(e) =>
                                setFilterForm({
                                    ...filterForm,
                                    auditable_id: e.target.value,
                                })
                            }
                            className="text-xs"
                        />
                    </div>
                    <div>
                        <label className="text-muted-foreground mb-1 block text-xs font-medium">
                            {t('audit_logs.filters.user_id')}
                        </label>
                        <Input
                            type="number"
                            placeholder={t(
                                'audit_logs.filters.user_id_placeholder',
                            )}
                            value={filterForm.user_id}
                            onChange={(e) =>
                                setFilterForm({
                                    ...filterForm,
                                    user_id: e.target.value,
                                })
                            }
                            className="text-xs"
                        />
                    </div>
                    <div>
                        <label className="text-muted-foreground mb-1 block text-xs font-medium">
                            {t('audit_logs.filters.from_date')}
                        </label>
                        <Input
                            type="date"
                            value={filterForm.from}
                            onChange={(e) =>
                                setFilterForm({
                                    ...filterForm,
                                    from: e.target.value,
                                })
                            }
                            className="text-xs"
                        />
                    </div>
                    <div>
                        <label className="text-muted-foreground mb-1 block text-xs font-medium">
                            {t('audit_logs.filters.to_date')}
                        </label>
                        <Input
                            type="date"
                            value={filterForm.to}
                            onChange={(e) =>
                                setFilterForm({
                                    ...filterForm,
                                    to: e.target.value,
                                })
                            }
                            className="text-xs"
                        />
                    </div>
                    <div className="mt-2 flex justify-end gap-2 md:col-span-5">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={handleReset}
                        >
                            {t('audit_logs.filters.reset')}
                        </Button>
                        <Button type="submit" size="sm" className="gap-1.5">
                            <Filter className="size-3.5" />
                            {t('audit_logs.filters.filter')}
                        </Button>
                    </div>
                </form>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('audit_logs.table.timestamp')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('audit_logs.table.actor')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('audit_logs.table.action')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('audit_logs.table.target')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('audit_logs.table.changes')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('audit_logs.table.reason_ip')}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {logs.data.map((log) => (
                                <tr
                                    key={log.id}
                                    className="hover:bg-muted/20 border-t"
                                >
                                    <td className="px-4 py-3 text-xs whitespace-nowrap">
                                        {new Date(
                                            log.created_at,
                                        ).toLocaleString(locale)}
                                    </td>
                                    <td className="px-4 py-3 font-medium">
                                        {log.user ? (
                                            log.user.username
                                        ) : (
                                            <span className="text-muted-foreground italic">
                                                {t('audit_logs.table.system')}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant="outline"
                                            className="text-xs capitalize"
                                        >
                                            {log.action}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs">
                                        {shortType(log.auditable_type)} #
                                        {log.auditable_id}
                                    </td>
                                    <td className="max-w-xs px-4 py-3 text-xs">
                                        {log.old_values || log.new_values ? (
                                            <div className="bg-muted/60 space-y-1 rounded p-1.5 font-mono text-[11px]">
                                                {log.old_values && (
                                                    <div className="text-destructive truncate">
                                                        -{' '}
                                                        {JSON.stringify(
                                                            log.old_values,
                                                        )}
                                                    </div>
                                                )}
                                                {log.new_values && (
                                                    <div className="truncate text-green-600">
                                                        +{' '}
                                                        {JSON.stringify(
                                                            log.new_values,
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3 text-xs">
                                        {log.reason && (
                                            <p className="text-foreground font-medium">
                                                "{log.reason}"
                                            </p>
                                        )}
                                        {log.ip_address && (
                                            <p>{log.ip_address}</p>
                                        )}
                                    </td>
                                </tr>
                            ))}

                            {logs.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        {t('audit_logs.table.empty')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="text-muted-foreground flex items-center justify-between text-sm">
                    <span>
                        {t('audit_logs.pagination.summary', {
                            current: logs.current_page,
                            last: logs.last_page,
                            total: logs.total,
                        })}
                    </span>
                    <div className="flex gap-2">
                        {logs.prev_page_url ? (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={logs.prev_page_url} preserveState>
                                    <ChevronLeft className="mr-1 size-4" />{' '}
                                    {t('common.previous')}
                                </Link>
                            </Button>
                        ) : (
                            <Button variant="outline" size="sm" disabled>
                                <ChevronLeft className="mr-1 size-4" />{' '}
                                {t('common.previous')}
                            </Button>
                        )}

                        {logs.next_page_url ? (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={logs.next_page_url} preserveState>
                                    {t('common.next')}{' '}
                                    <ChevronRight className="ml-1 size-4" />
                                </Link>
                            </Button>
                        ) : (
                            <Button variant="outline" size="sm" disabled>
                                <ChevronRight className="ml-1 size-4" />{' '}
                                {t('common.next')}
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
