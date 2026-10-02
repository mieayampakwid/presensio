import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Filter } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

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
        if (filterForm.auditable_type) query.auditable_type = filterForm.auditable_type;
        if (filterForm.auditable_id) query.auditable_id = String(filterForm.auditable_id);
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
            <Head title="Audit Logs" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Audit Logs"
                    description="Append-only record of academic, administrative, and financial mutations."
                />

                <form onSubmit={handleFilter} className="grid grid-cols-1 md:grid-cols-5 gap-3 p-4 border rounded-lg bg-card">
                    <div>
                        <label className="text-xs font-medium text-muted-foreground block mb-1">Entity Type</label>
                        <Input
                            placeholder="e.g. User"
                            value={filterForm.auditable_type}
                            onChange={(e) => setFilterForm({ ...filterForm, auditable_type: e.target.value })}
                            className="text-xs"
                        />
                    </div>
                    <div>
                        <label className="text-xs font-medium text-muted-foreground block mb-1">Entity ID</label>
                        <Input
                            type="number"
                            placeholder="e.g. 1"
                            value={filterForm.auditable_id}
                            onChange={(e) => setFilterForm({ ...filterForm, auditable_id: e.target.value })}
                            className="text-xs"
                        />
                    </div>
                    <div>
                        <label className="text-xs font-medium text-muted-foreground block mb-1">User ID</label>
                        <Input
                            type="number"
                            placeholder="e.g. 1"
                            value={filterForm.user_id}
                            onChange={(e) => setFilterForm({ ...filterForm, user_id: e.target.value })}
                            className="text-xs"
                        />
                    </div>
                    <div>
                        <label className="text-xs font-medium text-muted-foreground block mb-1">From Date</label>
                        <Input
                            type="date"
                            value={filterForm.from}
                            onChange={(e) => setFilterForm({ ...filterForm, from: e.target.value })}
                            className="text-xs"
                        />
                    </div>
                    <div>
                        <label className="text-xs font-medium text-muted-foreground block mb-1">To Date</label>
                        <Input
                            type="date"
                            value={filterForm.to}
                            onChange={(e) => setFilterForm({ ...filterForm, to: e.target.value })}
                            className="text-xs"
                        />
                    </div>
                    <div className="md:col-span-5 flex justify-end gap-2 mt-2">
                        <Button type="button" variant="outline" size="sm" onClick={handleReset}>
                            Reset
                        </Button>
                        <Button type="submit" size="sm" className="gap-1.5">
                            <Filter className="size-3.5" />
                            Filter
                        </Button>
                    </div>
                </form>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">Timestamp</th>
                                <th className="px-4 py-3 text-left font-medium">Actor</th>
                                <th className="px-4 py-3 text-left font-medium">Action</th>
                                <th className="px-4 py-3 text-left font-medium">Target</th>
                                <th className="px-4 py-3 text-left font-medium">Changes</th>
                                <th className="px-4 py-3 text-left font-medium">Reason / IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            {logs.data.map((log) => (
                                <tr key={log.id} className="border-t hover:bg-muted/20">
                                    <td className="px-4 py-3 text-xs whitespace-nowrap">
                                        {new Date(log.created_at).toLocaleString()}
                                    </td>
                                    <td className="px-4 py-3 font-medium">
                                        {log.user ? log.user.username : <span className="text-muted-foreground italic">System</span>}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge variant="outline" className="capitalize text-xs">
                                            {log.action}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 text-xs font-mono">
                                        {shortType(log.auditable_type)} #{log.auditable_id}
                                    </td>
                                    <td className="px-4 py-3 text-xs max-w-xs">
                                        {(log.old_values || log.new_values) ? (
                                            <div className="space-y-1 font-mono text-[11px] bg-muted/60 p-1.5 rounded">
                                                {log.old_values && (
                                                    <div className="text-destructive truncate">
                                                        - {JSON.stringify(log.old_values)}
                                                    </div>
                                                )}
                                                {log.new_values && (
                                                    <div className="text-green-600 truncate">
                                                        + {JSON.stringify(log.new_values)}
                                                    </div>
                                                )}
                                            </div>
                                        ) : (
                                            <span className="text-muted-foreground">—</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-xs text-muted-foreground">
                                        {log.reason && <p className="font-medium text-foreground">"{log.reason}"</p>}
                                        {log.ip_address && <p>{log.ip_address}</p>}
                                    </td>
                                </tr>
                            ))}

                            {logs.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-4 py-8 text-center text-muted-foreground">
                                        No audit log entries found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between text-sm text-muted-foreground">
                    <span>
                        Page {logs.current_page} of {logs.last_page} ({logs.total} entries)
                    </span>
                    <div className="flex gap-2">
                        {logs.prev_page_url ? (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={logs.prev_page_url} preserveState>
                                    <ChevronLeft className="size-4 mr-1" /> Previous
                                </Link>
                            </Button>
                        ) : (
                            <Button variant="outline" size="sm" disabled>
                                <ChevronLeft className="size-4 mr-1" /> Previous
                            </Button>
                        )}

                        {logs.next_page_url ? (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={logs.next_page_url} preserveState>
                                    Next <ChevronRight className="size-4 ml-1" />
                                </Link>
                            </Button>
                        ) : (
                            <Button variant="outline" size="sm" disabled>
                                <ChevronRight className="size-4 ml-1" /> Next
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
