import { Head, Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, UserRoundX } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { METHOD_LABELS, STATUS_BADGES } from '@/lib/attendance';

type RecordRow = {
    date: string;
    status: string;
    checked_in_at: string | null;
    checked_out_at: string | null;
    scan_method: string | null;
};

type Paginator = {
    data: RecordRow[];
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page: number;
    last_page: number;
};

type Props = {
    school_timezone: string;
    today: Omit<RecordRow, 'date'> | null;
    records: Paginator | null;
};

function StatusBadge({ status }: { status: string }) {
    return (
        <Badge variant={STATUS_BADGES[status]?.variant ?? 'secondary'}>
            {STATUS_BADGES[status]?.label ?? status}
        </Badge>
    );
}

export default function MyAttendance({
    school_timezone,
    today,
    records,
}: Props) {
    return (
        <>
            <Head title="My Attendance" />

            <div className="space-y-6 p-4">
                <Heading
                    title="My Attendance"
                    description="Your check-in history. Times are shown in the school timezone."
                />

                {records === null ? (
                    <Card className="max-w-md">
                        <CardContent className="text-muted-foreground flex flex-col items-center gap-3 py-12 text-center">
                            <UserRoundX className="size-10" />
                            <p className="text-foreground text-sm font-medium">
                                No student profile linked
                            </p>
                            <p className="text-sm">
                                Your account is not linked to a student profile
                                yet. Ask the school office to connect it.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <Card className="max-w-md">
                            <CardHeader className="items-center">
                                <CardTitle>Today</CardTitle>
                                <CardDescription>
                                    Scan times are recorded in the{' '}
                                    {school_timezone} school timezone.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col items-center gap-3">
                                {today ? (
                                    <>
                                        <StatusBadge status={today.status} />
                                        <div className="text-muted-foreground flex gap-6 text-sm">
                                            <span>
                                                In:{' '}
                                                <span className="text-foreground font-medium">
                                                    {today.checked_in_at ??
                                                        '—'}
                                                </span>
                                            </span>
                                            <span>
                                                Out:{' '}
                                                <span className="text-foreground font-medium">
                                                    {today.checked_out_at ??
                                                        '—'}
                                                </span>
                                            </span>
                                        </div>
                                    </>
                                ) : (
                                    <p className="text-muted-foreground text-sm">
                                        No record yet — check in at the
                                        scanner.
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-medium">
                                            Date
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium">
                                            Status
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium">
                                            Check-in
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium">
                                            Check-out
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium">
                                            Method
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {records.data.map((record) => (
                                        <tr
                                            key={record.date}
                                            className="border-t"
                                        >
                                            <td className="px-4 py-3 font-medium">
                                                {record.date}
                                            </td>
                                            <td className="px-4 py-3">
                                                <StatusBadge
                                                    status={record.status}
                                                />
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3">
                                                {record.checked_in_at ?? '—'}
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3">
                                                {record.checked_out_at ?? '—'}
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3">
                                                {record.scan_method
                                                    ? (METHOD_LABELS[
                                                          record.scan_method
                                                      ] ?? record.scan_method)
                                                    : '—'}
                                            </td>
                                        </tr>
                                    ))}

                                    {records.data.length === 0 && (
                                        <tr className="border-t">
                                            <td
                                                colSpan={5}
                                                className="text-muted-foreground px-4 py-8 text-center"
                                            >
                                                No records yet.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="flex items-center justify-between">
                            <p className="text-muted-foreground text-sm">
                                Page {records.current_page} of{' '}
                                {records.last_page}
                            </p>

                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    asChild
                                    disabled={!records.prev_page_url}
                                >
                                    <Link href={records.prev_page_url ?? '#'}>
                                        <ChevronLeft className="h-4 w-4" />
                                        Previous
                                    </Link>
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    asChild
                                    disabled={!records.next_page_url}
                                >
                                    <Link href={records.next_page_url ?? '#'}>
                                        Next
                                        <ChevronRight className="h-4 w-4" />
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

MyAttendance.layout = {
    breadcrumbs: [
        {
            title: 'My Attendance',
        },
    ],
};
