import { Head, Form, setLayoutProps } from '@inertiajs/react';
import { CalendarPlus, Pencil } from 'lucide-react';
import { useState, type ChangeEvent } from 'react';
import AttendanceController from '@/actions/App/Http/Controllers/Attendance/AttendanceController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { STATUS_BADGES } from '@/lib/attendance';

type ClassOption = {
    id: number;
    name: string;
};

type AttendanceRow = {
    status: string;
    checked_in_at: string | null;
    checked_out_at: string | null;
    scan_method: string | null;
    notes: string | null;
};

type Row = {
    student_id: number;
    full_name: string;
    student_number: string | null;
    attendance: AttendanceRow | null;
};

type Props = {
    classes: ClassOption[];
    filters: {
        class_id: number | null;
        date: string;
    };
    rows: Row[];
};

const SELECT_CLASS =
    'border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50';

const STATUS_OPTIONS = ['present', 'late', 'absent', 'sick', 'leave'];

function BulkPresentDialog({
    classId,
    date,
    remaining,
}: {
    classId: number | null;
    date: string;
    remaining: number;
}) {
    const [open, setOpen] = useState(false);
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <CalendarPlus className="h-4 w-4" />
                    {t('attendance.index.bulk_present')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t('attendance.index.bulk_present')}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            remaining === 1
                                ? 'attendance.index.bulk_present_description_one'
                                : 'attendance.index.bulk_present_description_other',
                            { count: remaining },
                        )}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...AttendanceController.bulkPresent.form()}
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing }) => (
                        <>
                            <input
                                type="hidden"
                                name="class_id"
                                value={classId ?? ''}
                            />
                            <input type="hidden" name="date" value={date} />

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        {t('common.cancel')}
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {t(
                                        'attendance.index.bulk_present_confirm',
                                        {
                                            count: remaining,
                                        },
                                    )}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function AttendanceIndex({ classes, filters, rows }: Props) {
    const { t } = useTranslation();
    const [editingRecord, setEditingRecord] = useState<{
        row: Row;
        date: string;
    } | null>(null);
    const submitFilters = (
        event: ChangeEvent<HTMLSelectElement | HTMLInputElement>,
    ) => {
        event.currentTarget.form?.requestSubmit();
    };

    setLayoutProps({
        breadcrumbs: [
            {
                title: t('attendance.index.title'),
                href: AttendanceController.index().url,
            },
        ],
    });

    return (
        <>
            <Head title={t('attendance.index.title')} />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        title={t('attendance.index.title')}
                        description={t('attendance.index.description')}
                    />

                    {rows.some((row) => row.attendance === null) && (
                        <BulkPresentDialog
                            classId={filters.class_id}
                            date={filters.date}
                            remaining={
                                rows.filter((row) => row.attendance === null)
                                    .length
                            }
                        />
                    )}
                </div>

                <form className="flex flex-wrap items-end gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="class_id">
                            {t('attendance.index.class')}
                        </Label>
                        <select
                            id="class_id"
                            name="class_id"
                            defaultValue={filters.class_id ?? ''}
                            className={`${SELECT_CLASS} w-56`}
                            onChange={submitFilters}
                        >
                            {classes.map((option) => (
                                <option key={option.id} value={option.id}>
                                    {option.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="date">
                            {t('attendance.index.date')}
                        </Label>
                        <Input
                            id="date"
                            type="date"
                            name="date"
                            defaultValue={filters.date}
                            className="w-44"
                            onChange={submitFilters}
                        />
                    </div>
                </form>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('attendance.index.columns.student')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('attendance.index.columns.status')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('attendance.index.columns.check_in')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('attendance.index.columns.check_out')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('attendance.index.columns.method')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('attendance.index.columns.notes')}
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row.student_id} className="border-t">
                                    <td className="px-4 py-3">
                                        <div className="font-medium">
                                            {row.full_name}
                                        </div>
                                        <div className="text-muted-foreground text-xs">
                                            {row.student_number ?? '—'}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        {row.attendance ? (
                                            <Badge
                                                variant={
                                                    STATUS_BADGES[
                                                        row.attendance.status
                                                    ]?.variant ?? 'secondary'
                                                }
                                            >
                                                {t(
                                                    `attendance.statuses.${row.attendance.status}`,
                                                )}
                                            </Badge>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                {t(
                                                    'attendance.index.no_record',
                                                )}
                                            </span>
                                        )}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {row.attendance?.checked_in_at ?? '—'}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {row.attendance?.checked_out_at ?? '—'}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {row.attendance?.scan_method
                                            ? t(
                                                  `attendance.methods.${row.attendance.scan_method}`,
                                              )
                                            : '—'}
                                    </td>
                                    <td className="text-muted-foreground max-w-48 truncate px-4 py-3">
                                        {row.attendance?.notes ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                title={t(
                                                    'attendance.index.edit_record',
                                                )}
                                                onClick={() =>
                                                    setEditingRecord({
                                                        row,
                                                        date: filters.date,
                                                    })
                                                }
                                            >
                                                <Pencil className="h-4 w-4" />
                                                <span className="sr-only">
                                                    {t(
                                                        'attendance.index.edit_record',
                                                    )}
                                                </span>
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}

                            {rows.length === 0 && (
                                <tr className="border-t">
                                    <td
                                        colSpan={7}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        {t('attendance.index.empty')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
            <Dialog
                open={editingRecord !== null}
                onOpenChange={(open) => !open && setEditingRecord(null)}
            >
                <DialogContent>
                    {editingRecord && (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {t('attendance.index.edit_title', {
                                        name: editingRecord.row.full_name,
                                    })}
                                </DialogTitle>
                                <DialogDescription>
                                    {editingRecord.row.attendance
                                        ? t('attendance.index.edit_description')
                                        : t(
                                              'attendance.index.edit_description_missing',
                                          )}
                                </DialogDescription>
                            </DialogHeader>

                            <Form
                                key={`${editingRecord.row.student_id}-${editingRecord.date}`}
                                {...AttendanceController.updateRecord.form()}
                                onSuccess={() => setEditingRecord(null)}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="student_id"
                                            value={editingRecord.row.student_id}
                                        />
                                        <input
                                            type="hidden"
                                            name="date"
                                            value={editingRecord.date}
                                        />

                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor={`status-${editingRecord.row.student_id}`}
                                            >
                                                {t(
                                                    'attendance.index.columns.status',
                                                )}
                                            </Label>
                                            <select
                                                id={`status-${editingRecord.row.student_id}`}
                                                name="status"
                                                defaultValue={
                                                    editingRecord.row.attendance
                                                        ?.status ?? 'present'
                                                }
                                                className={SELECT_CLASS}
                                            >
                                                {STATUS_OPTIONS.map((value) => (
                                                    <option
                                                        key={value}
                                                        value={value}
                                                    >
                                                        {t(
                                                            `attendance.statuses.${value}`,
                                                        )}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError
                                                message={errors.status}
                                            />
                                        </div>

                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="grid gap-2">
                                                <Label
                                                    htmlFor={`in-${editingRecord.row.student_id}`}
                                                >
                                                    {t(
                                                        'attendance.index.columns.check_in',
                                                    )}
                                                </Label>
                                                <Input
                                                    id={`in-${editingRecord.row.student_id}`}
                                                    type="time"
                                                    name="checked_in_at"
                                                    defaultValue={
                                                        editingRecord.row
                                                            .attendance
                                                            ?.checked_in_at ??
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
                                                <Label
                                                    htmlFor={`out-${editingRecord.row.student_id}`}
                                                >
                                                    {t(
                                                        'attendance.index.columns.check_out',
                                                    )}
                                                </Label>
                                                <Input
                                                    id={`out-${editingRecord.row.student_id}`}
                                                    type="time"
                                                    name="checked_out_at"
                                                    defaultValue={
                                                        editingRecord.row
                                                            .attendance
                                                            ?.checked_out_at ??
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
                                            <Label
                                                htmlFor={`notes-${editingRecord.row.student_id}`}
                                            >
                                                {t(
                                                    'attendance.index.columns.notes',
                                                )}
                                            </Label>
                                            <Input
                                                id={`notes-${editingRecord.row.student_id}`}
                                                name="notes"
                                                defaultValue={
                                                    editingRecord.row.attendance
                                                        ?.notes ?? ''
                                                }
                                                maxLength={255}
                                                placeholder={t(
                                                    'attendance.index.notes_placeholder',
                                                )}
                                            />
                                            <InputError
                                                message={errors.notes}
                                            />
                                        </div>

                                        <DialogFooter className="gap-2">
                                            <Button
                                                variant="secondary"
                                                type="button"
                                                onClick={() =>
                                                    setEditingRecord(null)
                                                }
                                            >
                                                {t('common.cancel')}
                                            </Button>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing && <Spinner />}
                                                {t(
                                                    'attendance.index.save_record',
                                                )}
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
