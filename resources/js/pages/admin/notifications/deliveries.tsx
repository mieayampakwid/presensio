import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

type DeliveryItem = {
    id: number;
    dedupe_key: string;
    type_key: string;
    channel: string;
    recipient_type: string;
    recipient_id: number;
    recipient_contact: string | null;
    status: string;
    scheduled_for: string | null;
    attempts: number;
    provider_message_id: string | null;
    error_message: string | null;
    sent_at: string | null;
    created_at: string | null;
};

type Paginator = {
    data: DeliveryItem[];
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page: number;
    last_page: number;
    total: number;
};

type TypeOption = {
    value: string;
    label: string;
};

type Props = {
    deliveries: Paginator;
    filters: {
        type: string | null;
        channel: string | null;
        status: string | null;
        date: string | null;
    };
    types: TypeOption[];
    channels: string[];
    statuses: string[];
};

const SELECT_CLASS =
    'border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50';

export default function NotificationDeliveries({
    deliveries,
    filters,
    types,
    channels,
    statuses,
}: Props) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            { title: t('notifications.deliveries.breadcrumbs.admin') },
            { title: t('notifications.deliveries.breadcrumbs.delivery_logs') },
        ],
    });
    const handleFilterChange = (key: string, value: string) => {
        router.get(
            '/notification-deliveries',
            {
                ...filters,
                [key]: value || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    return (
        <>
            <Head title={t('notifications.deliveries.title')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('notifications.deliveries.title')}
                    description={t('notifications.deliveries.description')}
                />

                <div className="bg-card border-border grid gap-4 rounded-lg border p-4 sm:grid-cols-2 md:grid-cols-4">
                    <div className="grid gap-1.5">
                        <Label htmlFor="filter-type">
                            {t('notifications.deliveries.filters.type')}
                        </Label>
                        <select
                            id="filter-type"
                            className={SELECT_CLASS}
                            value={filters.type ?? ''}
                            onChange={(e) =>
                                handleFilterChange('type', e.target.value)
                            }
                        >
                            <option value="">
                                {t(
                                    'notifications.deliveries.filters.all_types',
                                )}
                            </option>
                            {types.map((t) => (
                                <option key={t.value} value={t.value}>
                                    {t.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="filter-channel">
                            {t('notifications.deliveries.filters.channel')}
                        </Label>
                        <select
                            id="filter-channel"
                            className={SELECT_CLASS}
                            value={filters.channel ?? ''}
                            onChange={(e) =>
                                handleFilterChange('channel', e.target.value)
                            }
                        >
                            <option value="">
                                {t(
                                    'notifications.deliveries.filters.all_channels',
                                )}
                            </option>
                            {channels.map((c) => (
                                <option key={c} value={c}>
                                    {c.toUpperCase()}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="filter-status">
                            {t('notifications.deliveries.filters.status')}
                        </Label>
                        <select
                            id="filter-status"
                            className={SELECT_CLASS}
                            value={filters.status ?? ''}
                            onChange={(e) =>
                                handleFilterChange('status', e.target.value)
                            }
                        >
                            <option value="">
                                {t(
                                    'notifications.deliveries.filters.all_statuses',
                                )}
                            </option>
                            {statuses.map((s) => (
                                <option key={s} value={s}>
                                    {s}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="filter-date">
                            {t('notifications.deliveries.filters.date')}
                        </Label>
                        <Input
                            id="filter-date"
                            type="date"
                            value={filters.date ?? ''}
                            onChange={(e) =>
                                handleFilterChange('date', e.target.value)
                            }
                        />
                    </div>
                </div>

                <div className="border-border overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted text-muted-foreground border-b text-xs font-medium uppercase">
                            <tr>
                                <th className="px-4 py-3">
                                    {t('notifications.deliveries.table.time')}
                                </th>
                                <th className="px-4 py-3">
                                    {t('notifications.deliveries.table.type')}
                                </th>
                                <th className="px-4 py-3">
                                    {t(
                                        'notifications.deliveries.table.channel',
                                    )}
                                </th>
                                <th className="px-4 py-3">
                                    {t(
                                        'notifications.deliveries.table.recipient',
                                    )}
                                </th>
                                <th className="px-4 py-3">
                                    {t('notifications.deliveries.table.status')}
                                </th>
                                <th className="px-4 py-3">
                                    {t(
                                        'notifications.deliveries.table.attempts',
                                    )}
                                </th>
                                <th className="px-4 py-3">
                                    {t(
                                        'notifications.deliveries.table.details_error',
                                    )}
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-border divide-y">
                            {deliveries.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="text-muted-foreground p-8 text-center text-sm"
                                    >
                                        {t(
                                            'notifications.deliveries.table.empty',
                                        )}
                                    </td>
                                </tr>
                            ) : (
                                deliveries.data.map((item) => (
                                    <tr
                                        key={item.id}
                                        className="hover:bg-muted/30"
                                    >
                                        <td className="text-muted-foreground px-4 py-3 text-xs whitespace-nowrap">
                                            {item.created_at}
                                        </td>
                                        <td className="px-4 py-3 font-mono text-xs font-medium">
                                            {item.type_key}
                                        </td>
                                        <td className="px-4 py-3 text-xs font-semibold uppercase">
                                            {item.channel}
                                        </td>
                                        <td className="px-4 py-3 text-xs">
                                            <div className="font-medium">
                                                {item.recipient_contact ?? '—'}
                                            </div>
                                            <div className="text-muted-foreground text-[10px]">
                                                {item.recipient_type} #
                                                {item.recipient_id}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            <span
                                                className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
                                                    item.status === 'sent'
                                                        ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                                        : item.status ===
                                                            'pending'
                                                          ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400'
                                                          : item.status ===
                                                              'failed'
                                                            ? 'bg-destructive/10 text-destructive'
                                                            : 'bg-muted text-muted-foreground'
                                                }`}
                                            >
                                                {item.status}
                                            </span>
                                            {item.scheduled_for && (
                                                <div className="text-muted-foreground mt-0.5 text-[10px]">
                                                    {t(
                                                        'notifications.deliveries.table.scheduled',
                                                    )}{' '}
                                                    {item.scheduled_for}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-center text-xs font-medium">
                                            {item.attempts}
                                        </td>
                                        <td className="text-muted-foreground max-w-xs truncate px-4 py-3 text-xs">
                                            {item.error_message ? (
                                                <span
                                                    className="text-destructive font-mono text-[11px]"
                                                    title={item.error_message}
                                                >
                                                    {item.error_message}
                                                </span>
                                            ) : item.provider_message_id ? (
                                                <span
                                                    className="font-mono text-[10px]"
                                                    title={
                                                        item.provider_message_id
                                                    }
                                                >
                                                    {t(
                                                        'notifications.deliveries.table.id',
                                                    )}{' '}
                                                    {item.provider_message_id}
                                                </span>
                                            ) : (
                                                '—'
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {deliveries.last_page > 1 && (
                    <div className="flex items-center justify-between border-t pt-4">
                        <span className="text-muted-foreground text-xs">
                            {t('common.page_of_total', {
                                current: deliveries.current_page,
                                last: deliveries.last_page,
                                total: deliveries.total,
                            })}
                        </span>
                        <div className="flex items-center gap-2">
                            {deliveries.prev_page_url ? (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={deliveries.prev_page_url}>
                                        <ChevronLeft className="mr-1 size-4" />
                                        {t('common.previous')}
                                    </Link>
                                </Button>
                            ) : (
                                <Button variant="outline" size="sm" disabled>
                                    <ChevronLeft className="mr-1 size-4" />
                                    {t('common.previous')}
                                </Button>
                            )}

                            {deliveries.next_page_url ? (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={deliveries.next_page_url}>
                                        {t('common.next')}
                                        <ChevronRight className="ml-1 size-4" />
                                    </Link>
                                </Button>
                            ) : (
                                <Button variant="outline" size="sm" disabled>
                                    {t('common.next')}
                                    <ChevronRight className="ml-1 size-4" />
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}
