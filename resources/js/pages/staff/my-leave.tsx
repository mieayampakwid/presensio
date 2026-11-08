import { Form, Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Plus, XCircle } from 'lucide-react';
import { useState } from 'react';
import MyLeaveController from '@/actions/App/Http/Controllers/Staff/MyLeaveController';
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

const SELECT_CLASS =
    'border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50';

const TEXTAREA_CLASS =
    'border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 dark:bg-input/30 flex field-sizing-content min-h-16 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm';

type LeaveRequestRow = {
    id: number;
    type: string;
    start_date: string;
    end_date: string;
    reason: string;
    has_attachment: boolean;
    attachment_url: string | null;
    status: string;
    review_note: string | null;
    submitted_at: string | null;
};

type Paginator = {
    data: LeaveRequestRow[];
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page: number;
    last_page: number;
};

type Props = {
    requests: Paginator;
};

function StatusBadge({ status }: { status: string }) {
    return (
        <Badge variant={LEAVE_STATUS_BADGES[status]?.variant ?? 'secondary'}>
            {LEAVE_STATUS_BADGES[status]?.label ?? status}
        </Badge>
    );
}

export default function MyLeave({ requests }: Props) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            {
                title: t('nav.items.my_leave'),
                href: MyLeaveController.index().url,
            },
        ],
    });
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [cancelling, setCancelling] = useState<LeaveRequestRow | null>(null);
    const destroy = (leaveRequest: LeaveRequestRow) => {
        router.delete(
            MyLeaveController.destroy({ leave_request: leaveRequest.id }).url,
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title={t('staff.leave.my.title')} />

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title={t('staff.leave.my.title')}
                        description={t('staff.leave.my.description')}
                    />
                    <Button onClick={() => setIsCreateOpen(true)}>
                        <Plus className="mr-1.5 h-4 w-4" />
                        {t('staff.leave.my.new_request')}
                    </Button>
                </div>

                <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                    <DialogContent className="sm:max-w-lg">
                        <DialogHeader>
                            <DialogTitle>
                                {t('staff.leave.my.dialog.title')}
                            </DialogTitle>
                            <DialogDescription>
                                {t('staff.leave.my.dialog.help')}
                            </DialogDescription>
                        </DialogHeader>

                        <Form
                            {...MyLeaveController.store.form()}
                            onSuccess={() => setIsCreateOpen(false)}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <div className="space-y-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="type">
                                            {t(
                                                'staff.leave.my.dialog.category',
                                            )}
                                        </Label>
                                        <select
                                            id="type"
                                            name="type"
                                            defaultValue="sick"
                                            required
                                            className={SELECT_CLASS}
                                        >
                                            <option value="sick">
                                                {t('staff.leave.types.sick')}
                                            </option>
                                            <option value="leave">
                                                {t('staff.leave.types.leave')}
                                            </option>
                                            <option value="annual_leave">
                                                {t(
                                                    'staff.leave.types.annual_leave',
                                                )}
                                            </option>
                                            <option value="official_duty">
                                                {t(
                                                    'staff.leave.types.official_duty',
                                                )}
                                            </option>
                                        </select>
                                        <InputError message={errors.type} />
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="start_date">
                                                {t(
                                                    'staff.leave.my.dialog.first_day',
                                                )}
                                            </Label>
                                            <Input
                                                id="start_date"
                                                name="start_date"
                                                type="date"
                                                required
                                            />
                                            <InputError
                                                message={errors.start_date}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="end_date">
                                                {t(
                                                    'staff.leave.my.dialog.last_day',
                                                )}
                                            </Label>
                                            <Input
                                                id="end_date"
                                                name="end_date"
                                                type="date"
                                                required
                                            />
                                            <InputError
                                                message={errors.end_date}
                                            />
                                        </div>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="reason">
                                            {t('staff.leave.my.dialog.reason')}
                                        </Label>
                                        <textarea
                                            id="reason"
                                            name="reason"
                                            rows={3}
                                            required
                                            maxLength={2000}
                                            placeholder={t(
                                                'staff.leave.my.dialog.reason_placeholder',
                                            )}
                                            className={TEXTAREA_CLASS}
                                        />
                                        <InputError message={errors.reason} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="attachment">
                                            {t('staff.leave.my.dialog.proof')}
                                        </Label>
                                        <Input
                                            id="attachment"
                                            name="attachment"
                                            type="file"
                                            accept=".pdf,.jpg,.jpeg,.png"
                                        />
                                        <InputError
                                            message={errors.attachment}
                                        />
                                    </div>
                                    <DialogFooter className="gap-2 sm:gap-0">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                setIsCreateOpen(false)
                                            }
                                        >
                                            {t('common.cancel')}
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing && <Spinner />}
                                            {t('staff.leave.my.dialog.submit')}
                                        </Button>
                                    </DialogFooter>
                                </div>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('staff.leave.my.table.dates')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('staff.leave.my.table.category')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('staff.leave.my.table.reason')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('staff.leave.my.table.proof')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('staff.leave.my.table.status')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('staff.leave.my.table.review_note')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('staff.leave.my.table.submitted')}
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {requests.data.map((leaveRequest) => (
                                <tr key={leaveRequest.id} className="border-t">
                                    <td className="text-muted-foreground px-4 py-3 whitespace-nowrap">
                                        {leaveRequest.start_date ===
                                        leaveRequest.end_date
                                            ? leaveRequest.start_date
                                            : `${leaveRequest.start_date} – ${leaveRequest.end_date}`}
                                    </td>
                                    <td className="px-4 py-3">
                                        {LEAVE_TYPE_LABELS[leaveRequest.type] ??
                                            leaveRequest.type}
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
                                                className="text-primary underline-offset-4 hover:underline"
                                            >
                                                {t(
                                                    'staff.leave.my.table.download',
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
                                    <td className="text-muted-foreground max-w-48 px-4 py-3">
                                        {leaveRequest.review_note ?? '—'}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3 whitespace-nowrap">
                                        {leaveRequest.submitted_at ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {leaveRequest.status === 'pending' && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    setCancelling(leaveRequest)
                                                }
                                            >
                                                <XCircle className="h-4 w-4" />
                                                {t('common.cancel')}
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            ))}

                            {requests.data.length === 0 && (
                                <tr className="border-t">
                                    <td
                                        colSpan={8}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        {t('staff.leave.my.table.empty')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between">
                    <p className="text-muted-foreground text-sm">
                        {t('common.page_of', {
                            current: requests.current_page,
                            last: requests.last_page,
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
                                <ChevronLeft className="h-4 w-4" />
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
                                <ChevronRight className="h-4 w-4" />
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>

            <Dialog
                open={cancelling !== null}
                onOpenChange={(open) => !open && setCancelling(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('staff.leave.my.cancel_dialog.title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('staff.leave.my.cancel_dialog.description', {
                                dates:
                                    cancelling?.start_date ===
                                    cancelling?.end_date
                                        ? (cancelling?.start_date ?? '')
                                        : `${cancelling?.start_date} – ${cancelling?.end_date}`,
                            })}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2">
                        <Button
                            variant="secondary"
                            onClick={() => setCancelling(null)}
                        >
                            {t('staff.leave.my.cancel_dialog.keep')}
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => {
                                if (cancelling) {
                                    destroy(cancelling);
                                    setCancelling(null);
                                }
                            }}
                        >
                            {t('staff.leave.my.cancel_dialog.confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
