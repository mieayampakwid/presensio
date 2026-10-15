import { Form, Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, XCircle } from 'lucide-react';
import { useState } from 'react';
import MyLeaveController from '@/actions/App/Http/Controllers/Staff/MyLeaveController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
    const [cancelling, setCancelling] = useState<LeaveRequestRow | null>(null);

    const destroy = (leaveRequest: LeaveRequestRow) => {
        router.delete(
            MyLeaveController.destroy({ leave_request: leaveRequest.id }).url,
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="My Leave" />

            <div className="space-y-6 p-4">
                <Heading
                    title="My Leave"
                    description="Request sick leave, izin, cuti, or official duty. The principal or an administrator reviews every submission."
                />

                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>New leave request</CardTitle>
                        <CardDescription>
                            One date range per submission. Attach proof such as
                            a doctor's note or assignment letter (JPG, PNG, or
                            PDF, max 5 MB). Ranges may not overlap a pending or
                            approved request.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...MyLeaveController.store.form()}
                            className="space-y-6"
                        >
                            {({ processing, errors }) => (
                                <div className="space-y-6">
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="type">
                                                Category
                                            </Label>
                                            <select
                                                id="type"
                                                name="type"
                                                defaultValue="sick"
                                                required
                                                className={SELECT_CLASS}
                                            >
                                                <option value="sick">
                                                    Sick (Sakit)
                                                </option>
                                                <option value="leave">
                                                    Leave (Izin)
                                                </option>
                                                <option value="annual_leave">
                                                    Annual Leave (Cuti)
                                                </option>
                                                <option value="official_duty">
                                                    Official Duty (Dinas Luar)
                                                </option>
                                            </select>
                                            <InputError message={errors.type} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="reason">
                                                Reason
                                            </Label>
                                            <textarea
                                                id="reason"
                                                name="reason"
                                                rows={3}
                                                required
                                                maxLength={2000}
                                                placeholder="Tell the school why you will be absent…"
                                                className={TEXTAREA_CLASS}
                                            />
                                            <InputError
                                                message={errors.reason}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="start_date">
                                                First day
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
                                                Last day
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
                                        <Label htmlFor="attachment">
                                            Proof (optional)
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

                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}
                                        Submit request
                                    </Button>
                                </div>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    Dates
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Category
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Reason
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Proof
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Review note
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Submitted
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
                                                Download
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
                                                Cancel
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
                                        No leave requests submitted yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between">
                    <p className="text-muted-foreground text-sm">
                        Page {requests.current_page} of {requests.last_page}
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
                                Previous
                            </Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            disabled={!requests.next_page_url}
                        >
                            <Link href={requests.next_page_url ?? '#'}>
                                Next
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
                        <DialogTitle>Cancel leave request</DialogTitle>
                        <DialogDescription>
                            Withdraw the request for{' '}
                            {cancelling?.start_date === cancelling?.end_date
                                ? cancelling?.start_date
                                : `${cancelling?.start_date} – ${cancelling?.end_date}`}
                            ? Only pending requests can be cancelled.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2">
                        <Button
                            variant="secondary"
                            onClick={() => setCancelling(null)}
                        >
                            Keep request
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
                            Cancel request
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

MyLeave.layout = {
    breadcrumbs: [
        {
            title: 'My Leave',
        },
    ],
};
