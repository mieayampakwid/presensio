import { Head, usePage } from '@inertiajs/react';
import { Search, UserRoundX } from 'lucide-react';
import { useState, type ChangeEvent } from 'react';
import StudentReportController from '@/actions/App/Http/Controllers/Reports/StudentReportController';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { METHOD_LABELS, STATUS_BADGES } from '@/lib/attendance';
import type { Auth } from '@/types';

type StudentOption = {
    id: number;
    full_name: string;
    student_number: string | null;
};

type Counts = {
    present: number;
    late: number;
    absent: number;
    sick: number;
    leave: number;
};

type Summary = {
    counts: Counts;
    rate: number | null;
};

type RecordRow = {
    date: string;
    status: string;
    checked_in_at: string | null;
    checked_out_at: string | null;
    scan_method: string | null;
};

type Props = {
    students: StudentOption[];
    filters: {
        student_id: number | null;
        from: string;
        to: string;
        include_excused: boolean;
    };
    summary: Summary | null;
    records: RecordRow[] | null;
};

const SELECT_CLASS =
    'border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50';

const COUNT_CARDS: { key: keyof Counts; }[] = [
    { key: 'present' },
    { key: 'late' },
    { key: 'absent' },
    { key: 'sick' },
    { key: 'leave' },
];

export default function StudentReport({
    students,
    filters,
    summary,
    records,
}: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const [search, setSearch] = useState('');

    const submitFilters = (
        event: ChangeEvent<HTMLSelectElement | HTMLInputElement>,
    ) => {
        event.currentTarget.form?.requestSubmit();
    };

    const query: Record<string, string | number> = {
        from: filters.from,
        to: filters.to,
    };
    if (filters.student_id) {
        query.student_id = filters.student_id;
    }
    if (filters.include_excused) {
        query.include_excused = 1;
    }

    const term = search.trim().toLowerCase();
    const options = students.filter(
        (student) =>
            term === '' ||
            student.full_name.toLowerCase().includes(term) ||
            (student.student_number ?? '').toLowerCase().includes(term),
    );

    return (
        <>
            <Head title="Student Report" />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        title="Student Report"
                        description="Status counts, the attendance rate, and the day-by-day records."
                    />

                    {filters.student_id !== null && (
                        <Button
                            variant="outline"
                            onClick={() => {
                                window.location.href =
                                    StudentReportController.export({
                                        query,
                                    }).url;
                            }}
                        >
                            Export CSV
                        </Button>
                    )}
                </div>

                {students.length === 0 ? (
                    <Card className="max-w-md">
                        <CardContent className="text-muted-foreground flex flex-col items-center gap-3 py-12 text-center">
                            <UserRoundX className="size-10" />
                            <p className="text-foreground text-sm font-medium">
                                {auth.user.role === 'student' ||
                                auth.user.role === 'parent'
                                    ? 'No student profile linked'
                                    : 'No students yet'}
                            </p>
                            <p className="text-sm">
                                {auth.user.role === 'student' ||
                                auth.user.role === 'parent'
                                    ? 'Your account is not linked to a student profile yet. Ask the school office to connect it.'
                                    : 'Add students to see their attendance reports.'}
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <div className="flex flex-wrap items-end gap-4">
                            <form className="flex flex-wrap items-end gap-4">
                                {auth.user.role !== 'student' && (
                                    <div className="grid gap-2">
                                        <Label htmlFor="student-search">
                                            Find student
                                        </Label>
                                        <div className="relative">
                                            <Search className="text-muted-foreground pointer-events-none absolute top-2.5 left-2.5 size-4" />
                                            <Input
                                                id="student-search"
                                                value={search}
                                                onChange={(event) =>
                                                    setSearch(
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="Name or NIS…"
                                                className="w-56 pl-8"
                                            />
                                        </div>
                                    </div>
                                )}

                                {auth.user.role !== 'student' && (
                                    <div className="grid gap-2">
                                        <Label htmlFor="student_id">
                                            Student
                                        </Label>
                                        <select
                                            id="student_id"
                                            name="student_id"
                                            defaultValue={
                                                filters.student_id ?? ''
                                            }
                                            className={`${SELECT_CLASS} w-64`}
                                            onChange={submitFilters}
                                        >
                                            {options.map((student) => (
                                                <option
                                                    key={student.id}
                                                    value={student.id}
                                                >
                                                    {student.full_name}
                                                    {student.student_number
                                                        ? ` — ${student.student_number}`
                                                        : ''}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                )}

                                <div className="grid gap-2">
                                    <Label htmlFor="from">From</Label>
                                    <Input
                                        id="from"
                                        type="date"
                                        name="from"
                                        defaultValue={filters.from}
                                        className="w-44"
                                        onChange={submitFilters}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="to">To</Label>
                                    <Input
                                        id="to"
                                        type="date"
                                        name="to"
                                        defaultValue={filters.to}
                                        className="w-44"
                                        onChange={submitFilters}
                                    />
                                </div>

                                <div className="flex items-center gap-2 pb-2">
                                    <input
                                        type="checkbox"
                                        id="include_excused"
                                        name="include_excused"
                                        value="1"
                                        defaultChecked={
                                            filters.include_excused
                                        }
                                        onChange={submitFilters}
                                        className="accent-primary size-4"
                                    />
                                    <Label htmlFor="include_excused">
                                        Include sick/leave in rate
                                    </Label>
                                </div>
                            </form>
                        </div>

                        {summary === null ? (
                            <p className="text-muted-foreground text-sm">
                                Pick a student to see their report.
                            </p>
                        ) : (
                            <>
                                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                                    {COUNT_CARDS.map(({ key }) => (
                                        <Card key={key}>
                                            <CardHeader className="pb-2">
                                                <CardDescription>
                                                    {
                                                        STATUS_BADGES[key]
                                                            ?.label
                                                    }
                                                </CardDescription>
                                                <CardTitle className="text-3xl">
                                                    {summary.counts[key]}
                                                </CardTitle>
                                            </CardHeader>
                                        </Card>
                                    ))}
                                    <Card>
                                        <CardHeader className="pb-2">
                                            <CardDescription>
                                                Attendance rate
                                            </CardDescription>
                                            <CardTitle className="text-3xl">
                                                {summary.rate === null
                                                    ? '—'
                                                    : `${summary.rate}%`}
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent className="text-muted-foreground text-xs">
                                            (present + late)
                                            {filters.include_excused
                                                ? ' ÷ all records'
                                                : ' ÷ present + late + absent'}
                                        </CardContent>
                                    </Card>
                                </div>

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
                                            {records?.map((record) => (
                                                <tr
                                                    key={record.date}
                                                    className="border-t"
                                                >
                                                    <td className="px-4 py-3">
                                                        {record.date}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <Badge
                                                            variant={
                                                                STATUS_BADGES[
                                                                    record
                                                                        .status
                                                                ]?.variant ??
                                                                'secondary'
                                                            }
                                                        >
                                                            {STATUS_BADGES[
                                                                record.status
                                                            ]?.label ??
                                                                record.status}
                                                        </Badge>
                                                    </td>
                                                    <td className="text-muted-foreground px-4 py-3">
                                                        {record.checked_in_at ??
                                                            '—'}
                                                    </td>
                                                    <td className="text-muted-foreground px-4 py-3">
                                                        {record.checked_out_at ??
                                                            '—'}
                                                    </td>
                                                    <td className="text-muted-foreground px-4 py-3">
                                                        {record.scan_method
                                                            ? (METHOD_LABELS[
                                                                  record
                                                                      .scan_method
                                                              ] ??
                                                              record.scan_method)
                                                            : '—'}
                                                    </td>
                                                </tr>
                                            ))}

                                            {(records?.length ?? 0) === 0 && (
                                                <tr className="border-t">
                                                    <td
                                                        colSpan={5}
                                                        className="text-muted-foreground px-4 py-8 text-center"
                                                    >
                                                        No records in this
                                                        period.
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </>
                        )}
                    </>
                )}
            </div>
        </>
    );
}

StudentReport.layout = {
    breadcrumbs: [
        {
            title: 'Student Report',
            href: StudentReportController.index().url,
        },
    ],
};
