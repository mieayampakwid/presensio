import { Head, Link, router } from '@inertiajs/react';
import {
    CalendarClock,
    ChevronLeft,
    ChevronRight,
    Download,
} from 'lucide-react';
import { type ChangeEvent } from 'react';
import StaffAttendanceController from '@/actions/App/Http/Controllers/Staff/StaffAttendanceController';
import StaffRecapController from '@/actions/App/Http/Controllers/Staff/StaffRecapController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type RecapRow = {
    employee_id: number;
    name: string;
    employee_number: string | null;
    position: string | null;
    is_teacher: boolean;
    expected_days: number;
    extra_days: number;
    hadir: number;
    terlambat_count: number;
    terlambat_minutes: number;
    pulang_cepat_count: number;
    pulang_cepat_minutes: number;
    tidak_absen_pulang: number;
    sakit: number;
    izin: number;
    cuti: number;
    dinas_luar: number;
    alpa: number;
    rate: number;
};

type Summary = {
    employees: number;
    expected_days: number;
    extra_days: number;
    hadir: number;
    terlambat_count: number;
    terlambat_minutes: number;
    pulang_cepat_count: number;
    pulang_cepat_minutes: number;
    tidak_absen_pulang: number;
    sakit: number;
    izin: number;
    cuti: number;
    dinas_luar: number;
    alpa: number;
    rate: number;
};

type Props = {
    month: string;
    start_date: string;
    end_date: string;
    rows: RecapRow[];
    summary: Summary;
};

const RATE_COLORS: Record<'good' | 'fair' | 'poor', string> = {
    good: 'bg-emerald-600 text-white hover:bg-emerald-600',
    fair: 'bg-amber-500 text-white hover:bg-amber-500',
    poor: 'bg-rose-600 text-white hover:bg-rose-600',
};

function rateColor(rate: number): string {
    if (rate >= 90) return RATE_COLORS.good;
    if (rate >= 75) return RATE_COLORS.fair;
    return RATE_COLORS.poor;
}

function navigateMonth(month: string) {
    router.get(
        StaffRecapController.index.url({ query: { month } }),
        {},
        { preserveState: true },
    );
}

function shiftMonth(month: string, months: number): string {
    const [year, mon] = month.split('-').map(Number);
    const shifted = new Date(Date.UTC(year, mon - 1 + months, 1));

    return `${shifted.getUTCFullYear()}-${String(shifted.getUTCMonth() + 1).padStart(2, '0')}`;
}

function SummaryCard({
    label,
    value,
    tone,
}: {
    label: string;
    value: string | number;
    tone?: string;
}) {
    return (
        <div className="bg-card text-card-foreground rounded-lg border p-3 shadow-xs">
            <div className="text-muted-foreground text-xs font-medium">
                {label}
            </div>
            <div className={`mt-1 text-2xl font-bold ${tone ?? ''}`}>
                {value}
            </div>
        </div>
    );
}

export default function StaffRecap({ month, rows, summary }: Props) {
    const changeMonth = (e: ChangeEvent<HTMLInputElement>) => {
        if (e.target.value) {
            navigateMonth(e.target.value);
        }
    };

    return (
        <>
            <Head title={`Rekap Presensi Pegawai — ${month}`} />

            <div className="space-y-6 p-4">
                <div className="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                    <Heading
                        title="Rekap Presensi Pegawai"
                        description="Rekap bulanan kehadiran seluruh pegawai aktif beserta ekspor CSV."
                    />

                    <Button variant="outline" asChild>
                        <a
                            href={StaffRecapController.export.url({
                                query: { month },
                            })}
                            download
                        >
                            <Download className="mr-2 h-4 w-4" /> Ekspor CSV
                        </a>
                    </Button>
                </div>

                {/* Sub-navigation between the daily overview and this recap */}
                <div className="flex flex-wrap items-center gap-2">
                    <Button asChild size="sm" variant="outline">
                        <Link href={StaffAttendanceController.index().url}>
                            <CalendarClock className="mr-2 h-4 w-4" /> Presensi
                            Harian
                        </Link>
                    </Button>
                    <Button asChild size="sm" variant="default">
                        <Link
                            href={StaffRecapController.index.url({
                                query: { month },
                            })}
                        >
                            Rekap Bulanan
                        </Link>
                    </Button>
                </div>

                {/* Month filter */}
                <div className="bg-card flex flex-wrap items-center gap-2 rounded-lg border p-4">
                    <Button
                        aria-label="Bulan sebelumnya"
                        size="icon"
                        variant="outline"
                        onClick={() => navigateMonth(shiftMonth(month, -1))}
                    >
                        <ChevronLeft className="h-4 w-4" />
                    </Button>
                    <Input
                        type="month"
                        value={month}
                        onChange={changeMonth}
                        className="w-auto"
                    />
                    <Button
                        aria-label="Bulan berikutnya"
                        size="icon"
                        variant="outline"
                        onClick={() => navigateMonth(shiftMonth(month, 1))}
                    >
                        <ChevronRight className="h-4 w-4" />
                    </Button>
                </div>

                {/* Summary cards */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-6">
                    <SummaryCard label="Pegawai" value={summary.employees} />
                    <SummaryCard
                        label="Hari Kerja"
                        value={summary.expected_days}
                    />
                    <SummaryCard
                        label="Hadir"
                        value={summary.hadir}
                        tone="text-emerald-600 dark:text-emerald-400"
                    />
                    <SummaryCard
                        label="Terlambat"
                        value={`${summary.terlambat_count}× / ${summary.terlambat_minutes}m`}
                        tone="text-amber-600 dark:text-amber-400"
                    />
                    <SummaryCard
                        label="Alpa"
                        value={summary.alpa}
                        tone="text-rose-600 dark:text-rose-400"
                    />
                    <SummaryCard
                        label="Rata-rata Kehadiran"
                        value={`${summary.rate}%`}
                    />
                </div>

                {/* Recap table */}
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="px-3 py-2 text-left font-medium">
                                    Nama
                                </th>
                                <th className="px-3 py-2 text-center font-medium">
                                    Hari Kerja
                                </th>
                                <th className="px-3 py-2 text-center font-medium">
                                    Hadir
                                </th>
                                <th className="px-3 py-2 text-center font-medium">
                                    Terlambat
                                </th>
                                <th className="px-3 py-2 text-center font-medium">
                                    Pulang Cepat
                                </th>
                                <th className="px-3 py-2 text-center font-medium">
                                    Tidak Absen Pulang
                                </th>
                                <th className="px-3 py-2 text-center font-medium">
                                    Sakit
                                </th>
                                <th className="px-3 py-2 text-center font-medium">
                                    Izin
                                </th>
                                <th className="px-3 py-2 text-center font-medium">
                                    Cuti
                                </th>
                                <th className="px-3 py-2 text-center font-medium">
                                    Dinas Luar
                                </th>
                                <th className="px-3 py-2 text-center font-medium">
                                    Alpa
                                </th>
                                <th className="px-3 py-2 text-center font-medium">
                                    Extra
                                </th>
                                <th className="px-3 py-2 text-center font-medium">
                                    Kehadiran
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row.employee_id} className="border-t">
                                    <td className="px-3 py-2">
                                        <div className="font-medium">
                                            {row.name}
                                            {row.is_teacher ? (
                                                <span className="text-muted-foreground ml-1 text-xs">
                                                    (Guru)
                                                </span>
                                            ) : null}
                                        </div>
                                        <div className="text-muted-foreground text-xs">
                                            {row.employee_number ?? '—'}
                                            {row.position
                                                ? ` · ${row.position}`
                                                : ''}
                                        </div>
                                    </td>
                                    <td className="px-3 py-2 text-center">
                                        {row.expected_days}
                                    </td>
                                    <td className="px-3 py-2 text-center font-medium text-emerald-600 dark:text-emerald-400">
                                        {row.hadir}
                                    </td>
                                    <td className="px-3 py-2 text-center">
                                        {row.terlambat_count}
                                        <span className="text-muted-foreground text-xs">
                                            {' '}
                                            × · {row.terlambat_minutes}m
                                        </span>
                                    </td>
                                    <td className="px-3 py-2 text-center">
                                        {row.pulang_cepat_count}
                                        <span className="text-muted-foreground text-xs">
                                            {' '}
                                            × · {row.pulang_cepat_minutes}m
                                        </span>
                                    </td>
                                    <td className="px-3 py-2 text-center">
                                        {row.tidak_absen_pulang}
                                    </td>
                                    <td className="px-3 py-2 text-center">
                                        {row.sakit}
                                    </td>
                                    <td className="px-3 py-2 text-center">
                                        {row.izin}
                                    </td>
                                    <td className="px-3 py-2 text-center">
                                        {row.cuti}
                                    </td>
                                    <td className="px-3 py-2 text-center">
                                        {row.dinas_luar}
                                    </td>
                                    <td className="px-3 py-2 text-center">
                                        {row.alpa}
                                    </td>
                                    <td className="px-3 py-2 text-center">
                                        {row.extra_days}
                                    </td>
                                    <td className="px-3 py-2 text-center">
                                        <Badge className={rateColor(row.rate)}>
                                            {row.rate}%
                                        </Badge>
                                    </td>
                                </tr>
                            ))}
                            {rows.length === 0 && (
                                <tr>
                                    <td
                                        className="text-muted-foreground px-3 py-6 text-center"
                                        colSpan={13}
                                    >
                                        Tidak ada pegawai aktif.
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
