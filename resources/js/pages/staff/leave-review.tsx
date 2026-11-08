import { Form, Head, Link, router, setLayoutProps } from '@inertiajs/react';
import {
    Check,
    ChevronLeft,
    ChevronRight,
    Download,
    Search,
    X,
} from 'lucide-react';
import { ChangeEvent, useState } from 'react';
import LeaveReviewController from '@/actions/App/Http/Controllers/Staff/LeaveReviewController';
import Heading from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { LEAVE_STATUS_BADGES, LEAVE_TYPE_LABELS } from '@/lib/leave';

const TEXTAREA_CLASS =
    'border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 dark:bg-input/30 flex field-sizing-content min-h-16 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm';

const STATUS_FILTERS = [
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'cancelled', label: 'Cancelled' },
] as const;

type LeaveReviewRow = {
    id: number;
    employee_name: string;
    type: string;
    start_date: string;
    end_date: string;
    reason: string;
    has_attachment: boolean;
    attachment_url: string | null;
    status: string;
    review_note: string | null;
    reviewer_name: string | null;
    submitted_at: string | null;
};

type Paginator = {
    data: LeaveReviewRow[];
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page: number;
    last_page: number;
};

type Props = {
    requests: Paginator;
    filters: { status: string; search: string };
};

function StatusBadge({ status }: { status: string }) {
    return (
        <Badge variant={LEAVE_STATUS_BADGES[status]?.variant ?? 'secondary'}>
            {LEAVE_STATUS_BADGES[status]?.label ?? status}
        </Badge>
    );
}

export default function LeaveReview({ requests, filters }: Props) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            {
                title: t('nav.items.staff_leave'),
                href: LeaveReviewController.index().url,
            },
        ],
    });
    const [rejecting, setRejecting] = useState<LeaveReviewRow | null>(null);

    const submitSearch = (e: ChangeEvent<HTMLInputElement>) => {
        router.get(
            LeaveReviewController.index.url({
                query: { status: filters.status },
            }),
            { search: e.currentTarget.value },
            { preserveState: true, replace: true },
        );
    };

    const approve = (leaveRequest: LeaveReviewRow) => {
        router.put(
            LeaveReviewController.approve({ leave_request: leaveRequest.id })
                .url,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title={t('staff.leave.review.title')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('staff.leave.review.title')}
                    description={t('staff.leave.review.description')}
                />

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-wrap gap-2">
                        {STATUS_FILTERS.map((filter) => (
                            <Button
                                key={filter.value}
                                asChild
                                size="sm"
                                variant={
                                    filters.status === filter.value
                                        ? 'default'
                                        : 'outline'
                                }
                            >
                                <Link
                                    href={LeaveReviewController.index.url({
                                        query: {
                                            status: filter.value,
                                            search: filters.search,
                                        },
                                    })}
                                    preserveState
                                    replace
                                >
                                    {filter.label}
                                </Link>
                            </Button>
                        ))}
                    </div>

                    <div className="relative w-full sm:w-72">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                        <Input
                            defaultValue={filters.search}
                            placeholder="Search employee name…"
                            className="pl-8"
                            onChange={submitSearch}
                        />
                    </div>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('staff.leave.review.table.employee')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('staff.leave.review.table.category')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('staff.leave.review.table.dates')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('staff.leave.review.table.reason')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('staff.leave.review.table.proof')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('staff.leave.review.table.status')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('staff.leave.my.table.review_note')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('staff.leave.review.table.submitted')}
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    {t('staff.leave.review.table.actions')}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {requests.data.map((leaveRequest) => (
                                <tr key={leaveRequest.id} className="border-t">
                                    <td className="px-4 py-3 font-medium">
                                        {leaveRequest.employee_name}
                                    </td>
                                    <td className="px-4 py-3">
                                        {LEAVE_TYPE_LABELS[leaveRequest.type] ??
                                            leaveRequest.type}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3 whitespace-nowrap">
                                        {leaveRequest.start_date ===
                                        leaveRequest.end_date
                                            ? leaveRequest.start_date
                                            : `${leaveRequest.start_date} – ${leaveRequest.end_date}`}
                                    </td>
                                    <td
                                        className="max-w-48 truncate px-4 py-3"
                                        title={leaveRequest.reason}
                                    >
                                        {leaveRequest.reason}
                                    </td>
                                    <td className="px-4 py-3">
                                        {leaveRequest.attachment_url ? (
                                            <a
                                                href={
                                                    leaveRequest.attachment_url
                                                }
                                                className="text-primary inline-flex items-center gap-1 underline-offset-4 hover:underline"
                                            >
                                                <Download className="size-3.5" />
                                                {t(
                                                    'staff.leave.review.table.download',
                                                )}
                                            </a>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <StatusBadge
                                            status={leaveRequest.status}
                                        />
                                    </td>
                                    <td className="text-muted-foreground max-w-40 px-4 py-3">
                                        {leaveRequest.review_note ? (
                                            <span
                                                title={leaveRequest.review_note}
                                            >
                                                {leaveRequest.review_note}
                                                {leaveRequest.reviewer_name && (
                                                    <span className="block text-xs">
                                                        {t(
                                                            'staff.leave.review.table.by',
                                                            {
                                                                reviewer:
                                                                    leaveRequest.reviewer_name,
                                                            },
                                                        )}
                                                    </span>
                                                )}
                                            </span>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3 whitespace-nowrap">
                                        {leaveRequest.submitted_at ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-right whitespace-nowrap">
                                        {leaveRequest.status === 'pending' && (
                                            <div className="inline-flex justify-end gap-2">
                                                <Button
                                                    size="sm"
                                                    onClick={() =>
                                                        approve(leaveRequest)
                                                    }
                                                >
                                                    <Check className="size-4" />
                                                    {t(
                                                        'staff.leave.review.table.approve',
                                                    )}
                                                </Button>
                                                <Button
                                                    variant="destructive"
                                                    size="sm"
                                                    onClick={() =>
                                                        setRejecting(
                                                            leaveRequest,
                                                        )
                                                    }
                                                >
                                                    <X className="size-4" />
                                                    {t(
                                                        'staff.leave.review.table.reject',
                                                    )}
                                                </Button>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}

                            {requests.data.length === 0 && (
                                <tr className="border-t">
                                    <td
                                        colSpan={9}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        {t('staff.leave.review.table.empty')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between">
                    <p className="text-muted-foreground text-sm">
                        {t('common.page_of_total', {
                            current: requests.current_page,
                            last: requests.last_page,
                            total: requests.total,
                        })}
                    </p>

                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!requests.prev_page_url}
                        >
                            <Link href={requests.prev_page_url ?? '#'}>
                                <ChevronLeft className="size-4" />
                                {t('common.previous')}
                            </Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!requests.next_page_url}
                        >
                            <Link href={requests.next_page_url ?? '#'}>
                                {t('common.next')}
                                <ChevronRight className="size-4" />
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>

            <Dialog
                open={rejecting !== null}
                onOpenChange={(open) => !open && setRejecting(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('staff.leave.review.reject_dialog.title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('staff.leave.review.reject_dialog.description', {
                                name: rejecting?.employee_name ?? '',
                                dates:
                                    rejecting?.start_date ===
                                    rejecting?.end_date
                                        ? (rejecting?.start_date ?? '')
                                        : `${rejecting?.start_date} – ${rejecting?.end_date}`,
                            })}
                        </DialogDescription>
                    </DialogHeader>

                    {rejecting && (
                        <Form
                            {...LeaveReviewController.reject.form({
                                leave_request: rejecting.id,
                            })}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="review_note">
                                            {t(
                                                'staff.leave.review.reject_dialog.note',
                                            )}
                                        </Label>
                                        <textarea
                                            id="review_note"
                                            name="review_note"
                                            rows={3}
                                            required
                                            maxLength={1000}
                                            placeholder={t(
                                                'staff.leave.review.reject_dialog.note_placeholder',
                                            )}
                                            className={TEXTAREA_CLASS}
                                        />
                                        <InputError
                                            message={errors.review_note}
                                        />
                                    </div>

                                    <DialogFooter className="gap-2">
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            onClick={() => setRejecting(null)}
                                        >
                                            {t(
                                                'staff.leave.review.reject_dialog.keep',
                                            )}
                                        </Button>
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            disabled={processing}
                                        >
                                            {processing && <Spinner />}
                                            {t(
                                                'staff.leave.review.reject_dialog.confirm',
                                            )}
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
