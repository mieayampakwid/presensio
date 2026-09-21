import { Head } from '@inertiajs/react';
import { type ChangeEvent } from 'react';
import ClassReportController from '@/actions/App/Http/Controllers/Reports/ClassReportController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { STATUS_BADGES } from '@/lib/attendance';

type ClassOption = {
    id: number;
    name: string;
};

type YearOption = {
    id: number;
    name: string;
    is_active: boolean;
};

type Counts = {
    present: number;
    late: number;
    absent: number;
    sick: number;
    leave: number;
};

type Row = {
    id: number;
    student_number: string | null;
    full_name: string;
    counts: Counts;
    rate: number | null;
    statuses: Record<string, string>;
};

type Report = {
    dates: string[];
    non_school_dates: string[];
    rows: Row[];
} | null;

type Props = {
    years: YearOption[];
    classes: ClassOption[];
    filters: {
        year_id: number | null;
        class_id: number | null;
        from: string;
        to: string;
        include_excused: boolean;
    };
    report: Report;
};

const SELECT_CLASS =
    'border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50';

/** Badge-variant semantics translated to grid cells (per-cell Badges would
 * be far too heavy at 366 days × N students). */
const VARIANT_CELL: Record<
    'default' | 'secondary' | 'destructive' | 'outline',
    string
> = {
    default: 'bg-primary',
    secondary: 'bg-secondary',
    destructive: 'bg-destructive',
    outline: 'border border-foreground/40',
};

const SUMMARY_COLUMNS: { key: keyof Counts; label: string }[] = [
    { key: 'present', label: 'Present' },
    { key: 'late', label: 'Late' },
    { key: 'absent', label: 'Absent' },
    { key: 'sick', label: 'Sick' },
    { key: 'leave', label: 'Leave' },
];

export default function ClassReport({ years, classes, filters, report }: Props) {
    const submitFilters = (
        event: ChangeEvent<HTMLSelectElement | HTMLInputElement>,
    ) => {
        event.currentTarget.form?.requestSubmit();
    };

    const query: Record<string, string | number> = {
        from: filters.from,
        to: filters.to,
    };
    if (filters.class_id) {
        query.class_id = filters.class_id;
    }
    if (filters.include_excused) {
        query.include_excused = 1;
    }

    const nonSchool = new Set(report?.non_school_dates ?? []);

    return (
        <>
            <Head title="Class Report" />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        title="Class Report"
                        description="Per-student counts and the students × dates grid."
                    />

                    {filters.class_id !== null && (
                        <Button
                            variant="outline"
                            onClick={() => {
                                window.location.href =
                                    ClassReportController.export({
                                        query,
                                    }).url;
                            }}
                        >
                            Export CSV
                        </Button>
                    )}
                </div>

                <form className="flex flex-wrap items-end gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="year_id">Academic year</Label>
                        <select
                            id="year_id"
                            name="year_id"
                            defaultValue={filters.year_id ?? ''}
                            className={`${SELECT_CLASS} w-56`}
                            onChange={submitFilters}
                        >
                            {years.map((year) => (
                                <option key={year.id} value={year.id}>
                                    {year.name}
                                    {year.is_active ? ' — active' : ''}
                                </option>
                            ))}
                        </select>
                    </div>

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
                            defaultChecked={filters.include_excused}
                            onChange={submitFilters}
                            className="accent-primary size-4"
                        />
                        <Label htmlFor="include_excused">
                            Include sick/leave in rate
                        </Label>
                    </div>
                </form>

                {report === null ? (
                    <p className="text-muted-foreground text-sm">
                        No classes yet.
                    </p>
                ) : (
                    <>
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-medium">
                                            Student
                                        </th>
                                        {SUMMARY_COLUMNS.map((column) => (
                                            <th
                                                key={column.key}
                                                className="px-4 py-3 text-left font-medium"
                                            >
                                                {column.label}
                                            </th>
                                        ))}
                                        <th className="px-4 py-3 text-left font-medium">
                                            Rate
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {report.rows.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="border-t"
                                        >
                                            <td className="px-4 py-3">
                                                <div className="font-medium">
                                                    {row.full_name}
                                                </div>
                                                <div className="text-muted-foreground text-xs">
                                                    {row.student_number ??
                                                        '—'}
                                                </div>
                                            </td>
                                            {SUMMARY_COLUMNS.map(
                                                (column) => (
                                                    <td
                                                        key={column.key}
                                                        className="px-4 py-3"
                                                    >
                                                        {row.counts[column.key]}
                                                    </td>
                                                ),
                                            )}
                                            <td className="px-4 py-3">
                                                {row.rate === null
                                                    ? '—'
                                                    : `${row.rate}%`}
                                            </td>
                                        </tr>
                                    ))}

                                    {report.rows.length === 0 && (
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

                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="sticky left-0 bg-muted/50 px-4 py-3 text-left font-medium">
                                            Student
                                        </th>
                                        {report.dates.map((date) => (
                                            <th
                                                key={date}
                                                className="text-muted-foreground px-1 py-2 text-center font-normal"
                                                title={date}
                                            >
                                                <div className="w-6 text-[10px] whitespace-nowrap">
                                                    {date.slice(8)}
                                                </div>
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {report.rows.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="border-t"
                                        >
                                            <td className="sticky left-0 bg-background px-4 py-2 font-medium whitespace-nowrap">
                                                {row.full_name}
                                            </td>
                                            {report.dates.map((date) => {
                                                const status =
                                                    row.statuses[date];
                                                const isNonSchool =
                                                    nonSchool.has(date);
                                                const badge =
                                                    status === undefined
                                                        ? undefined
                                                        : STATUS_BADGES[
                                                              status
                                                          ];

                                                return (
                                                    <td
                                                        key={date}
                                                        title={`${date} — ${
                                                            badge?.label ??
                                                            (isNonSchool
                                                                ? 'Non-school day'
                                                                : 'No record')
                                                        }`}
                                                        className={`size-6 min-w-6 p-0 text-center ${
                                                            isNonSchool
                                                                ? 'bg-muted/40'
                                                                : badge
                                                                  ? VARIANT_CELL[
                                                                        badge
                                                                            .variant
                                                                    ]
                                                                  : ''
                                                        }`}
                                                    />
                                                );
                                            })}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="text-muted-foreground flex flex-wrap items-center gap-4 text-xs">
                            {Object.entries(STATUS_BADGES).map(
                                ([status, badge]) => (
                                    <span
                                        key={status}
                                        className="flex items-center gap-1.5"
                                    >
                                        <span
                                            className={`inline-block size-3 rounded-sm ${
                                                VARIANT_CELL[badge.variant]
                                            }`}
                                        />
                                        {badge.label}
                                    </span>
                                ),
                            )}
                            <span className="flex items-center gap-1.5">
                                <span className="bg-muted/60 inline-block size-3 rounded-sm" />
                                Non-school day
                            </span>
                            <span className="flex items-center gap-1.5">
                                <span className="border-border inline-block size-3 rounded-sm border" />
                                No record
                            </span>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

ClassReport.layout = {
    breadcrumbs: [
        {
            title: 'Class Report',
            href: ClassReportController.index().url,
        },
    ],
};
