import { Head, Link, setLayoutProps } from '@inertiajs/react';
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
import { useTranslation } from '@/hooks/use-translation';
import { STATUS_BADGES } from '@/lib/attendance';

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
    const { t } = useTranslation();

    return (
        <Badge variant={STATUS_BADGES[status]?.variant ?? 'secondary'}>
            {t(`attendance.statuses.${status}`)}
        </Badge>
    );
}

export default function MyAttendance({
    school_timezone,
    today,
    records,
}: Props) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            {
                title: t('attendance.my_attendance.title'),
            },
        ],
    });

    return (
        <>
            <Head title={t('attendance.my_attendance.title')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('attendance.my_attendance.title')}
                    description={t('attendance.my_attendance.description')}
                />

                {records === null ? (
                    <Card className="max-w-md">
                        <CardContent className="text-muted-foreground flex flex-col items-center gap-3 py-12 text-center">
                            <UserRoundX className="size-10" />
                            <p className="text-foreground text-sm font-medium">
                                {t('attendance.no_profile.title')}
                            </p>
                            <p className="text-sm">
                                {t('attendance.no_profile.description')}
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <Card className="max-w-md">
                            <CardHeader className="items-center">
                                <CardTitle>
                                    {t('attendance.my_attendance.today')}
                                </CardTitle>
                                <CardDescription>
                                    {t('attendance.timezone_note', {
                                        timezone: school_timezone,
                                    })}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col items-center gap-3">
                                {today ? (
                                    <>
                                        <StatusBadge status={today.status} />
                                        <div className="text-muted-foreground flex gap-6 text-sm">
                                            <span>
                                                {t(
                                                    'attendance.my_attendance.in',
                                                )}{' '}
                                                <span className="text-foreground font-medium">
                                                    {today.checked_in_at ?? '—'}
                                                </span>
                                            </span>
                                            <span>
                                                {t(
                                                    'attendance.my_attendance.out',
                                                )}{' '}
                                                <span className="text-foreground font-medium">
                                                    {today.checked_out_at ??
                                                        '—'}
                                                </span>
                                            </span>
                                        </div>
                                    </>
                                ) : (
                                    <p className="text-muted-foreground text-sm">
                                        {t(
                                            'attendance.my_attendance.no_record_today',
                                        )}
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-medium">
                                            {t(
                                                'attendance.my_attendance.columns.date',
                                            )}
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium">
                                            {t(
                                                'attendance.my_attendance.columns.status',
                                            )}
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium">
                                            {t(
                                                'attendance.my_attendance.columns.check_in',
                                            )}
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium">
                                            {t(
                                                'attendance.my_attendance.columns.check_out',
                                            )}
                                        </th>
                                        <th className="px-4 py-3 text-left font-medium">
                                            {t(
                                                'attendance.my_attendance.columns.method',
                                            )}
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
                                                    ? t(
                                                          `attendance.methods.${record.scan_method}`,
                                                      )
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
                                                {t(
                                                    'attendance.my_attendance.empty',
                                                )}
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="flex items-center justify-between">
                            <p className="text-muted-foreground text-sm">
                                {t('common.page_of', {
                                    current: records.current_page,
                                    last: records.last_page,
                                })}
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
                                        {t('common.previous')}
                                    </Link>
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    asChild
                                    disabled={!records.next_page_url}
                                >
                                    <Link href={records.next_page_url ?? '#'}>
                                        {t('common.next')}
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
