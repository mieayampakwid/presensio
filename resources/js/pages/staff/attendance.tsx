import { Head, router, Form } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useState, type ChangeEvent } from 'react';
import StaffAttendanceController from '@/actions/App/Http/Controllers/Staff/StaffAttendanceController';
import Heading from '@/components/heading';
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

const STATUS_COLORS: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
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
            <Head title={`Presensi Pegawai — ${date}`} />

            <div className="space-y-6 p-4">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <Heading
                        title="Presensi Guru & Pegawai"
                        description={`Jam kerja: ${staff_start_time.slice(0, 5)} - ${staff_end_time.slice(0, 5)} ${!is_school_day ? ' (Hari Libur / Non-Operasional)' : ''}`}
                    />

                    <div className="flex items-center gap-2">
                        <Input
                            type="date"
                            value={date}
                            onChange={(e: ChangeEvent<HTMLInputElement>) => navigateDate(e.target.value)}
                            className="w-auto"
                        />
                    </div>
                </div>

                {/* Summary cards */}
                <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-3">
                    <div className="rounded-lg border p-3 bg-card text-card-foreground shadow-xs">
                        <div className="text-xs text-muted-foreground font-medium">Diharapkan</div>
                        <div className="text-2xl font-bold mt-1">{summary.expected}</div>
                    </div>
                    <div className="rounded-lg border p-3 bg-card text-card-foreground shadow-xs">
                        <div className="text-xs text-muted-foreground font-medium">Hadir</div>
                        <div className="text-2xl font-bold mt-1 text-emerald-600 dark:text-emerald-400">
                            {summary.arrived}
                        </div>
                    </div>
                    <div className="rounded-lg border p-3 bg-card text-card-foreground shadow-xs">
                        <div className="text-xs text-muted-foreground font-medium">Terlambat</div>
                        <div className="text-2xl font-bold mt-1 text-amber-600 dark:text-amber-400">
                            {summary.late}
                        </div>
                    </div>
                    <div className="rounded-lg border p-3 bg-card text-card-foreground shadow-xs">
                        <div className="text-xs text-muted-foreground font-medium">Belum Hadir</div>
                        <div className="text-2xl font-bold mt-1 text-muted-foreground">
                            {summary.not_yet_arrived}
                        </div>
                    </div>
                    <div className="rounded-lg border p-3 bg-card text-card-foreground shadow-xs">
                        <div className="text-xs text-muted-foreground font-medium">Izin / Sakit / Cuti</div>
                        <div className="text-2xl font-bold mt-1 text-blue-600 dark:text-blue-400">
                            {summary.on_leave}
                        </div>
                    </div>
                    <div className="rounded-lg border p-3 bg-card text-card-foreground shadow-xs">
                        <div className="text-xs text-muted-foreground font-medium">Alpa</div>
                        <div className="text-2xl font-bold mt-1 text-rose-600 dark:text-rose-400">
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
                        Semua ({summary.expected})
                    </Button>
                    <Button
                        variant={filter === 'not_yet_arrived' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => changeFilter('not_yet_arrived')}
                    >
                        Belum Hadir ({summary.not_yet_arrived})
                    </Button>
                    <Button
                        variant={filter === 'late' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => changeFilter('late')}
                    >
                        Terlambat ({summary.late})
                    </Button>
                    <Button
                        variant={filter === 'absent' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => changeFilter('absent')}
                    >
                        Alpa ({summary.absent})
                    </Button>
                    <Button
                        variant={filter === 'on_leave' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => changeFilter('on_leave')}
                    >
                        Izin / Cuti ({summary.on_leave})
                    </Button>
                    {summary.no_checkout > 0 && (
                        <Button
                            variant={filter === 'no_checkout' ? 'destructive' : 'outline'}
                            size="sm"
                            onClick={() => changeFilter('no_checkout')}
                        >
                            Tidak Absen Pulang ({summary.no_checkout})
                        </Button>
                    )}
                </div>

                {/* Table */}
                <div className="rounded-md border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">Pegawai</th>
                                <th className="px-4 py-3 text-left font-medium">Status</th>
                                <th className="px-4 py-3 text-left font-medium">Jam Masuk</th>
                                <th className="px-4 py-3 text-left font-medium">Jam Pulang</th>
                                <th className="px-4 py-3 text-left font-medium">Keterangan</th>
                                {can_override && <th className="px-4 py-3 text-right font-medium">Aksi</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row.employee_id} className="border-t">
                                    <td className="px-4 py-3 font-medium">
                                        <div className="flex flex-col">
                                            <span className="font-semibold">{row.name}</span>
                                            <div className="flex items-center gap-1.5 text-xs text-muted-foreground mt-0.5">
                                                <span>{row.position ?? '—'}</span>
                                                {row.is_teacher && (
                                                    <Badge variant="secondary" className="text-[10px] h-4 px-1">
                                                        Guru
                                                    </Badge>
                                                )}
                                                {!row.is_expected && (
                                                    <span className="italic text-amber-600 dark:text-amber-400">
                                                        (Luar Jadwal)
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-1.5 flex-wrap">
                                            <Badge variant={STATUS_COLORS[row.status] ?? 'outline'}>
                                                {row.status_label}
                                            </Badge>
                                            {row.no_checkout && (
                                                <Badge variant="destructive" className="text-[10px]">
                                                    Tidak Absen Pulang
                                                </Badge>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-col">
                                            <span>{row.checked_in_at ?? '—'}</span>
                                            {row.late_minutes > 0 && (
                                                <span className="text-xs text-amber-600 dark:text-amber-400">
                                                    +{row.late_minutes} m
                                                </span>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-col">
                                            <span>{row.checked_out_at ?? '—'}</span>
                                            {row.early_leave_minutes > 0 && (
                                                <span className="text-xs text-rose-600 dark:text-rose-400">
                                                    Cepat {row.early_leave_minutes} m
                                                </span>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground text-xs">
                                        <div className="flex flex-col gap-0.5">
                                            {row.scan_method && (
                                                <span>Metode: {row.scan_method}</span>
                                            )}
                                            {row.overridden_by && (
                                                <span className="text-amber-600 dark:text-amber-400">
                                                    Override oleh @{row.overridden_by}
                                                </span>
                                            )}
                                            {row.notes && <span>{row.notes}</span>}
                                            {!row.scan_method && !row.overridden_by && !row.notes && '—'}
                                        </div>
                                    </td>
                                    {can_override && (
                                        <td className="px-4 py-3 text-right">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => setEditingRow(row)}
                                            >
                                                <Pencil className="h-4 w-4 mr-1" />
                                                Edit
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
                                        Tidak ada data presensi untuk filter ini.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Override Dialog */}
            {can_override && (
                <Dialog open={editingRow !== null} onOpenChange={(open) => !open && setEditingRow(null)}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Ubah Presensi Pegawai</DialogTitle>
                            <DialogDescription>
                                Override manual presensi untuk {editingRow?.name} tanggal {date}.
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
                                        <input type="hidden" name="_method" value="put" />
                                        <input type="hidden" name="employee_id" value={editingRow.employee_id} />
                                        <input type="hidden" name="date" value={date} />

                                        <div className="grid gap-2">
                                            <Label htmlFor="status">Status Presensi</Label>
                                            <select
                                                id="status"
                                                name="status"
                                                required
                                                defaultValue={editingRow.status === 'not_yet_arrived' ? 'present' : editingRow.status}
                                                className="border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                                            >
                                                {statuses.map((s) => (
                                                    <option key={s.value} value={s.value}>
                                                        {s.label}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError message={errors.status} />
                                        </div>

                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="grid gap-2">
                                                <Label htmlFor="checked_in_at">Jam Masuk (HH:mm)</Label>
                                                <Input
                                                    id="checked_in_at"
                                                    name="checked_in_at"
                                                    type="time"
                                                    defaultValue={editingRow.checked_in_at ?? ''}
                                                />
                                                <InputError message={errors.checked_in_at} />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="checked_out_at">Jam Pulang (HH:mm)</Label>
                                                <Input
                                                    id="checked_out_at"
                                                    name="checked_out_at"
                                                    type="time"
                                                    defaultValue={editingRow.checked_out_at ?? ''}
                                                />
                                                <InputError message={errors.checked_out_at} />
                                            </div>
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="notes">Catatan Override (Opsional)</Label>
                                            <Input
                                                id="notes"
                                                name="notes"
                                                defaultValue={editingRow.notes ?? ''}
                                                placeholder="e.g. Lupa bawa kartu RFID / Dinas Luar"
                                            />
                                            <InputError message={errors.notes} />
                                        </div>

                                        <DialogFooter className="gap-2 pt-2">
                                            <DialogClose asChild>
                                                <Button variant="secondary" type="button">
                                                    Batal
                                                </Button>
                                            </DialogClose>
                                            <Button type="submit" disabled={processing}>
                                                {processing && <Spinner />}
                                                Simpan Presensi
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

StaffAttendanceIndex.layout = {
    breadcrumbs: [
        {
            title: 'Presensi Pegawai',
            href: StaffAttendanceController.index().url,
        },
    ],
};
