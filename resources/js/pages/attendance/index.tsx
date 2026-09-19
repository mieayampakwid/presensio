import { Head, Form } from '@inertiajs/react';
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

const STATUS_OPTIONS = [
    { value: 'present', label: 'Present' },
    { value: 'late', label: 'Late' },
    { value: 'absent', label: 'Absent' },
    { value: 'sick', label: 'Sick' },
    { value: 'leave', label: 'Leave' },
];

const STATUS_BADGES: Record<
    string,
    {
        label: string;
        variant: 'default' | 'secondary' | 'destructive' | 'outline';
    }
> = {
    present: { label: 'Present', variant: 'default' },
    late: { label: 'Late', variant: 'outline' },
    absent: { label: 'Absent', variant: 'destructive' },
    sick: { label: 'Sick', variant: 'secondary' },
    leave: { label: 'Leave', variant: 'secondary' },
};

const METHOD_LABELS: Record<string, string> = {
    rfid: 'RFID',
    dynamic_qr: 'QR',
    manual_override: 'Manual override',
};

function EditRecordDialog({ row, date }: { row: Row; date: string }) {
    const [open, setOpen] = useState(false);
    const record = row.attendance;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="icon" title="Edit record">
                    <Pencil className="h-4 w-4" />
                    <span className="sr-only">Edit record</span>
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit record — {row.full_name}</DialogTitle>
                    <DialogDescription>
                        {record
                            ? 'Manual edits are stamped as overrides and never notify anyone.'
                            : 'No record exists for this day — saving one now reconstructs it.'}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    {...AttendanceController.updateRecord.form()}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="student_id"
                                value={row.student_id}
                            />
                            <input type="hidden" name="date" value={date} />

                            <div className="grid gap-2">
                                <Label htmlFor={`status-${row.student_id}`}>
                                    Status
                                </Label>
                                <select
                                    id={`status-${row.student_id}`}
                                    name="status"
                                    defaultValue={record?.status ?? 'present'}
                                    className={SELECT_CLASS}
                                >
                                    {STATUS_OPTIONS.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.status} />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor={`in-${row.student_id}`}>
                                        Check-in
                                    </Label>
                                    <Input
                                        id={`in-${row.student_id}`}
                                        type="time"
                                        name="checked_in_at"
                                        defaultValue={
                                            record?.checked_in_at ?? ''
                                        }
                                    />
                                    <InputError
                                        message={errors.checked_in_at}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor={`out-${row.student_id}`}>
                                        Check-out
                                    </Label>
                                    <Input
                                        id={`out-${row.student_id}`}
                                        type="time"
                                        name="checked_out_at"
                                        defaultValue={
                                            record?.checked_out_at ?? ''
                                        }
                                    />
                                    <InputError
                                        message={errors.checked_out_at}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`notes-${row.student_id}`}>
                                    Notes
                                </Label>
                                <Input
                                    id={`notes-${row.student_id}`}
                                    name="notes"
                                    defaultValue={record?.notes ?? ''}
                                    maxLength={255}
                                    placeholder="Why was this record overridden?"
                                />
                                <InputError message={errors.notes} />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Save record
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

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

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <CalendarPlus className="h-4 w-4" />
                    Mark remaining present
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Mark remaining present</DialogTitle>
                    <DialogDescription>
                        This marks the {remaining} student
                        {remaining === 1 ? '' : 's'} without a record as
                        present. Existing records — including sick and leave —
                        are never touched.
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
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Mark {remaining} present
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
    const submitFilters = (
        event: ChangeEvent<HTMLSelectElement | HTMLInputElement>,
    ) => {
        event.currentTarget.form?.requestSubmit();
    };

    return (
        <>
            <Head title="Attendance" />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        title="Attendance"
                        description="Review, correct, and reconstruct the day's records."
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
                        <Label htmlFor="class_id">Class</Label>
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
                        <Label htmlFor="date">Date</Label>
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
                                    Student
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
                                <th className="px-4 py-3 text-left font-medium">
                                    Notes
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
                                                {STATUS_BADGES[
                                                    row.attendance.status
                                                ]?.label ??
                                                    row.attendance.status}
                                            </Badge>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                No record
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
                                            ? (METHOD_LABELS[
                                                  row.attendance.scan_method
                                              ] ?? row.attendance.scan_method)
                                            : '—'}
                                    </td>
                                    <td className="text-muted-foreground max-w-48 truncate px-4 py-3">
                                        {row.attendance?.notes ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end">
                                            <EditRecordDialog
                                                row={row}
                                                date={filters.date}
                                            />
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
                                        No students in this class.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

AttendanceIndex.layout = {
    breadcrumbs: [
        {
            title: 'Attendance',
            href: AttendanceController.index().url,
        },
    ],
};
