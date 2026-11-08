import { Form, Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { CalendarCheck, CalendarRange, Pencil } from 'lucide-react';
import { useState, type ChangeEvent } from 'react';
import StaffAttendanceController from '@/actions/App/Http/Controllers/Staff/StaffAttendanceController';
import StaffRecapController from '@/actions/App/Http/Controllers/Staff/StaffRecapController';
import ShiftRosterController from '@/actions/App/Http/Controllers/Staff/ShiftRosterController';
import Heading from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type EmployeeRow = {
    employee_id: number;
    name: string;
    employee_number: string | null;
    position: string | null;
    is_teacher: boolean;
    shift_name: string | null;
    shift_times: string | null;
    is_expected: boolean;
    status: string;
    status_label: string;
    checked_in_at: string | null;
    checked_out_at: string | null;
    late_minutes: number;
    early_leave_minutes: number;
    no_checkout: boolean;
    scan_method: string | null;
    overridden_by: string | null;
    notes: string | null;
    first_period: {
        title: string;
        class_name: string;
        room: string | null;
        start_time: string;
        end_time: string;
    } | null;
};

type Summary = {
    expected: number;
    arrived: number;
    late: number;
    not_yet_arrived: number;
    on_leave: number;
    absent: number;
    no_checkout: number;
};

type StatusOption = {
    value: string;
    label: string;
};

type Props = {
    date: string;
    is_school_day: boolean;
    filter: string;
    rows: EmployeeRow[];
    summary: Summary;
    staff_start_time: string;
    staff_end_time: string;
    can_override: boolean;
    statuses: StatusOption[];
};

const STATUS_COLORS: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    present: 'default',
    late: 'secondary',
    absent: 'destructive',
    sick: 'outline',
    leave: 'outline',
    annual_leave: 'outline',
    official_duty: 'outline',
    not_yet_arrived: 'outline',
};

export default function StaffAttendanceIndex({
    date,
    is_school_day,
    filter,
    rows,
    summary,
    staff_start_time,
    staff_end_time,
    can_override,
    statuses,
}: Props) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            {
                title: t('nav.items.staff_attendance'),
                href: StaffAttendanceController.index().url,
            },
        ],
    });
    const [editingRow, setEditingRow] = useState<EmployeeRow | null>(null);

    const navigateDate = (newDate: string) => {
        router.get(
            StaffAttendanceController.index().url,
            { date: newDate, filter },
            { preserveState: true },
        );
    };

    const changeFilter = (newFilter: string) => {
        router.get(
            StaffAttendanceController.index().url,
            { date, filter: newFilter },
            { preserveState: true },
        );
    };

    return (
        <>
            <Head title={t('staff.attendance.title', { date })} />

            <div className="space-y-6 p-4">
                <div className="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                    <Heading
                        title={t('staff.attendance.heading')}
                        description={t('staff.attendance.description', {
                            start: staff_start_time.slice(0, 5),
                            end: staff_end_time.slice(0, 5),
                            holiday_note: !is_school_day
                                ? t('staff.attendance.holiday_note')
                                : '',
                        })}
                    />

                    <div className="flex items-center gap-2">
                        <Button variant="outline" asChild>
                            <Link
                                href={ShiftRosterController.index.url({
                                    query: { week: date },
                                })}
                            >
                                <CalendarCheck className="mr-2 h-4 w-4" />{' '}
                                {t('staff.attendance.shift_roster')}
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link
                                href={StaffRecapController.index.url({
                                    query: { month: date.slice(0, 7) },
                                })}
                            >
                                <CalendarRange className="mr-2 h-4 w-4" />{' '}
                                {t('staff.attendance.monthly_recap')}
                            </Link>
                        </Button>
                        <Input
                            type="date"
                            value={date}
                            onChange={(e: ChangeEvent<HTMLInputElement>) =>
                                navigateDate(e.target.value)
                            }
                            className="w-auto"
                        />
                    </div>
                </div>

                {/* Summary cards */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-6">
                    <div className="bg-card text-card-foreground rounded-lg border p-3 shadow-xs">
                        <div className="text-muted-foreground text-xs font-medium">
                            {t('staff.attendance.summary.expected')}
                        </div>
                        <div className="mt-1 text-2xl font-bold">
                            {summary.expected}
                        </div>
                    </div>
                    <div className="bg-card text-card-foreground rounded-lg border p-3 shadow-xs">
                        <div className="text-muted-foreground text-xs font-medium">
                            {t('staff.attendance.summary.arrived')}
                        </div>
                        <div className="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                            {summary.arrived}
                        </div>
                    </div>
                    <div className="bg-card text-card-foreground rounded-lg border p-3 shadow-xs">
                        <div className="text-muted-foreground text-xs font-medium">
                            {t('staff.attendance.summary.late')}
                        </div>
                        <div className="mt-1 text-2xl font-bold text-amber-600 dark:text-amber-400">
                            {summary.late}
                        </div>
                    </div>
                    <div className="bg-card text-card-foreground rounded-lg border p-3 shadow-xs">
                        <div className="text-muted-foreground text-xs font-medium">
                            {t('staff.attendance.summary.not_yet_arrived')}
                        </div>
                        <div className="text-muted-foreground mt-1 text-2xl font-bold">
                            {summary.not_yet_arrived}
                        </div>
                    </div>
                    <div className="bg-card text-card-foreground rounded-lg border p-3 shadow-xs">
                        <div className="text-muted-foreground text-xs font-medium">
                            {t('staff.attendance.summary.on_leave')}
                        </div>
                        <div className="mt-1 text-2xl font-bold text-blue-600 dark:text-blue-400">
                            {summary.on_leave}
                        </div>
                    </div>
                    <div className="bg-card text-card-foreground rounded-lg border p-3 shadow-xs">
                        <div className="text-muted-foreground text-xs font-medium">
                            {t('staff.attendance.summary.absent')}
                        </div>
                        <div className="mt-1 text-2xl font-bold text-rose-600 dark:text-rose-400">
                            {summary.absent}
                        </div>
                    </div>
                </div>

                {/* Filter buttons */}
                <div className="flex flex-wrap gap-2">
                    <Button
                        variant={filter === 'all' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => changeFilter('all')}
                    >
                        {t('staff.attendance.filters.all', {
                            count: summary.expected,
                        })}
                    </Button>
                    <Button
                        variant={
                            filter === 'not_yet_arrived' ? 'default' : 'outline'
                        }
                        size="sm"
                        onClick={() => changeFilter('not_yet_arrived')}
                    >
                        {t('staff.attendance.filters.not_yet_arrived', {
                            count: summary.not_yet_arrived,
                        })}
                    </Button>
                    <Button
                        variant={filter === 'late' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => changeFilter('late')}
                    >
                        {t('staff.attendance.filters.late', {
                            count: summary.late,
                        })}
                    </Button>
                    <Button
                        variant={filter === 'absent' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => changeFilter('absent')}
                    >
                        {t('staff.attendance.filters.absent', {
                            count: summary.absent,
                        })}
                    </Button>
                    <Button
                        variant={filter === 'on_leave' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => changeFilter('on_leave')}
                    >
                        {t('staff.attendance.filters.on_leave', {
                            count: summary.on_leave,
                        })}
                    </Button>
                    {summary.no_checkout > 0 && (
                        <Button
                            variant={
                                filter === 'no_checkout'
                                    ? 'destructive'
                                    : 'outline'
                            }
                            size="sm"
                            onClick={() => changeFilter('no_checkout')}
                        >
                            {t('staff.attendance.filters.no_checkout', {
                                count: summary.no_checkout,
                            })}
                        </Button>
                    )}
                </div>

                {/* Table */}
                <div className="rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('staff.attendance.table.employee')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('staff.attendance.table.status')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('staff.attendance.table.check_in')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('staff.attendance.table.check_out')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('staff.attendance.table.notes')}
                                </th>
                                {can_override && (
                                    <th className="px-4 py-3 text-right font-medium">
                                        {t('staff.attendance.table.actions')}
                                    </th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row.employee_id} className="border-t">
                                    <td className="px-4 py-3 font-medium">
                                        <div className="flex flex-col">
                                            <span className="font-semibold">
                                                {row.name}
                                            </span>
                                            <div className="text-muted-foreground mt-0.5 flex items-center gap-1.5 text-xs">
                                                <span>
                                                    {row.position ?? '—'}
                                                </span>
                                                {row.is_teacher && (
                                                    <Badge
                                                        variant="secondary"
                                                        className="h-4 px-1 text-[10px]"
                                                    >
                                                        {t(
                                                            'staff.attendance.table.teacher',
                                                        )}
                                                    </Badge>
                                                )}
                                                {!row.is_expected && (
                                                    <span className="text-amber-600 italic dark:text-amber-400">
                                                        {t(
                                                            'staff.attendance.table.off_schedule',
                                                        )}
                                                    </span>
                                                )}
                                            </div>
                                            {row.shift_name !== null && (
                                                <div className="mt-0.5 text-xs font-medium">
                                                    {row.shift_name}
                                                    {row.shift_times !== null &&
                                                        ` · ${row.shift_times}`}
                                                </div>
                                            )}
                                            {row.first_period && (
                                                <div className="text-muted-foreground mt-1 text-xs">
                                                    {t(
                                                        'staff.attendance.table.first_period',
                                                    )}{' '}
                                                    {row.first_period.title} (
                                                    {
                                                        row.first_period
                                                            .class_name
                                                    }
                                                    ) ·{' '}
                                                    {
                                                        row.first_period
                                                            .start_time
                                                    }
                                                    {row.first_period.room &&
                                                        ` · ${row.first_period.room}`}
                                                </div>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap items-center gap-1.5">
                                            <Badge
                                                variant={
                                                    STATUS_COLORS[row.status] ??
                                                    'outline'
                                                }
                                            >
                                                {row.status_label}
                                            </Badge>
                                            {row.no_checkout && (
                                                <Badge
                                                    variant="destructive"
                                                    className="text-[10px]"
                                                >
                                                    {t(
                                                        'staff.attendance.table.no_checkout',
                                                    )}
                                                </Badge>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-col">
                                            <span>
                                                {row.checked_in_at ?? '—'}
                                            </span>
                                            {row.late_minutes > 0 && (
                                                <span className="text-xs text-amber-600 dark:text-amber-400">
                                                    +
                                                    {t(
                                                        'staff.attendance.table.minutes',
                                                        {
                                                            count: row.late_minutes,
                                                        },
                                                    )}
                                                </span>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-col">
                                            <span>
                                                {row.checked_out_at ?? '—'}
                                            </span>
                                            {row.early_leave_minutes > 0 && (
                                                <span className="text-xs text-rose-600 dark:text-rose-400">
                                                    {t(
                                                        'staff.attendance.table.early',
                                                    )}{' '}
                                                    {t(
                                                        'staff.attendance.table.minutes',
                                                        {
                                                            count: row.early_leave_minutes,
                                                        },
                                                    )}
                                                </span>
                                            )}
                                        </div>
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3 text-xs">
                                        <div className="flex flex-col gap-0.5">
                                            {row.scan_method && (
                                                <span>
                                                    {t(
                                                        'staff.attendance.table.method',
                                                        {
                                                            method: row.scan_method,
                                                        },
                                                    )}
                                                </span>
                                            )}
                                            {row.overridden_by && (
                                                <span className="text-amber-600 dark:text-amber-400">
                                                    {t(
                                                        'staff.attendance.table.override_by',
                                                        {
                                                            user: row.overridden_by,
                                                        },
                                                    )}
                                                </span>
                                            )}
                                            {row.notes && (
                                                <span>{row.notes}</span>
                                            )}
                                            {!row.scan_method &&
                                                !row.overridden_by &&
                                                !row.notes &&
                                                '—'}
                                        </div>
                                    </td>
                                    {can_override && (
                                        <td className="px-4 py-3 text-right">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    setEditingRow(row)
                                                }
                                            >
                                                <Pencil className="mr-1 h-4 w-4" />
                                                {t(
                                                    'staff.attendance.table.edit',
                                                )}
                                            </Button>
                                        </td>
                                    )}
                                </tr>
                            ))}
                            {rows.length === 0 && (
                                <tr className="border-t">
                                    <td
                                        colSpan={can_override ? 6 : 5}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        {t('staff.attendance.table.empty')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Override Dialog */}
            {can_override && (
                <Dialog
                    open={editingRow !== null}
                    onOpenChange={(open) => !open && setEditingRow(null)}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                {t('staff.attendance.dialog.title')}
                            </DialogTitle>
                            <DialogDescription>
                                {t('staff.attendance.dialog.description', {
                                    name: editingRow?.name ?? '',
                                    date,
                                })}
                            </DialogDescription>
                        </DialogHeader>

                        {editingRow && (
                            <Form
                                action={StaffAttendanceController.upsert().url}
                                method="post"
                                onSuccess={() => setEditingRow(null)}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="_method"
                                            value="put"
                                        />
                                        <input
                                            type="hidden"
                                            name="employee_id"
                                            value={editingRow.employee_id}
                                        />
                                        <input
                                            type="hidden"
                                            name="date"
                                            value={date}
                                        />

                                        <div className="grid gap-2">
                                            <Label htmlFor="status">
                                                {t(
                                                    'staff.attendance.dialog.status',
                                                )}
                                            </Label>
                                            <select
                                                id="status"
                                                name="status"
                                                required
                                                defaultValue={
                                                    editingRow.status ===
                                                    'not_yet_arrived'
                                                        ? 'present'
                                                        : editingRow.status
                                                }
                                                className="border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] md:text-sm"
                                            >
                                                {statuses.map((s) => (
                                                    <option
                                                        key={s.value}
                                                        value={s.value}
                                                    >
                                                        {s.label}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError
                                                message={errors.status}
                                            />
                                        </div>

                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="grid gap-2">
                                                <Label htmlFor="checked_in_at">
                                                    {t(
                                                        'staff.attendance.dialog.check_in',
                                                    )}
                                                </Label>
                                                <Input
                                                    id="checked_in_at"
                                                    name="checked_in_at"
                                                    type="time"
                                                    defaultValue={
                                                        editingRow.checked_in_at ??
                                                        ''
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        errors.checked_in_at
                                                    }
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="checked_out_at">
                                                    {t(
                                                        'staff.attendance.dialog.check_out',
                                                    )}
                                                </Label>
                                                <Input
                                                    id="checked_out_at"
                                                    name="checked_out_at"
                                                    type="time"
                                                    defaultValue={
                                                        editingRow.checked_out_at ??
                                                        ''
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        errors.checked_out_at
                                                    }
                                                />
                                            </div>
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="notes">
                                                {t(
                                                    'staff.attendance.dialog.notes',
                                                )}
                                            </Label>
                                            <Input
                                                id="notes"
                                                name="notes"
                                                defaultValue={
                                                    editingRow.notes ?? ''
                                                }
                                                placeholder={t(
                                                    'staff.attendance.dialog.notes_placeholder',
                                                )}
                                            />
                                            <InputError
                                                message={errors.notes}
                                            />
                                        </div>

                                        <DialogFooter className="gap-2 pt-2">
                                            <DialogClose asChild>
                                                <Button
                                                    variant="secondary"
                                                    type="button"
                                                >
                                                    {t('common.cancel')}
                                                </Button>
                                            </DialogClose>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing && <Spinner />}
                                                {t(
                                                    'staff.attendance.dialog.save',
                                                )}
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        )}
                    </DialogContent>
                </Dialog>
            )}
        </>
    );
}
