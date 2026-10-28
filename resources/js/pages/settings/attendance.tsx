import { Form, Head, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import AttendanceSettingsController from '@/actions/App/Http/Controllers/Settings/AttendanceSettingsController';
import ShiftController from '@/actions/App/Http/Controllers/Settings/ShiftController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';

type Settings = {
    school_timezone: string;
    school_start_time: string;
    auto_absent_cron_time: string;
    require_checkout: boolean;
    scan_debounce_minutes: number;
    scan_drift_tolerance_minutes: number;
    school_operational_days: number[];
    staff_start_time: string;
    staff_end_time: string;
    staff_absent_sweep_time: string;
};

type ShiftRow = {
    id: number;
    name: string;
    start_time: string;
    end_time: string;
    crosses_midnight: boolean;
    sweep_offset_minutes: number;
};

type Props = {
    settings: Settings;
    shifts: ShiftRow[];
};

const SELECT_CLASS =
    'border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50';

const TIMEZONES = [
    'Asia/Jakarta',
    'Asia/Makassar',
    'Asia/Jayapura',
    'Asia/Singapore',
    'Asia/Tokyo',
    'UTC',
];

const WEEKDAYS = [
    { value: 1, label: 'Senin (Mon)' },
    { value: 2, label: 'Selasa (Tue)' },
    { value: 3, label: 'Rabu (Wed)' },
    { value: 4, label: 'Kamis (Thu)' },
    { value: 5, label: 'Jumat (Fri)' },
    { value: 6, label: 'Sabtu (Sat)' },
    { value: 7, label: 'Minggu (Sun)' },
];

export default function AttendanceSettings({ settings, shifts }: Props) {
    const { t } = useTranslation();
    const { errors } = usePage().props;
    const [editing, setEditing] = useState<ShiftRow | null>(null);
    const [isFormOpen, setIsFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<ShiftRow | null>(null);

    const destroy = (shift: ShiftRow): void => {
        router.delete(ShiftController.destroy.url({ shift: shift.id }), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Attendance settings" />

            <h1 className="sr-only">Attendance settings</h1>

            <Form
                {...AttendanceSettingsController.update.form()}
                options={{ preserveScroll: true }}
                className="space-y-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="space-y-6">
                            <Heading
                                variant="small"
                                title="Attendance"
                                description="School-day anchors for scanning, sweeps, and the dashboard. Changes apply live."
                            />

                            <div className="grid gap-2">
                                <Label htmlFor="school_timezone">
                                    School timezone
                                </Label>
                                <select
                                    id="school_timezone"
                                    name="school_timezone"
                                    defaultValue={settings.school_timezone}
                                    className={`${SELECT_CLASS} max-w-xs`}
                                >
                                    {TIMEZONES.map((zone) => (
                                        <option key={zone} value={zone}>
                                            {zone}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.school_timezone} />
                            </div>

                            <div className="space-y-2 rounded-lg border p-4">
                                <Label className="text-sm font-semibold">
                                    Hari Sekolah Operasional (Operational Days)
                                </Label>
                                <p className="text-muted-foreground text-xs">
                                    Pilih hari-hari kegiatan belajar mengajar
                                    berlangsung. Absensi otomatis dan
                                    perhitungan ketidakhadiran hanya aktif pada
                                    hari operasional.
                                </p>
                                <div className="grid grid-cols-2 gap-3 pt-2 sm:grid-cols-3">
                                    {WEEKDAYS.map((day) => (
                                        <label
                                            key={day.value}
                                            className="flex cursor-pointer items-center gap-2 text-sm"
                                        >
                                            <input
                                                type="checkbox"
                                                name="school_operational_days[]"
                                                value={day.value}
                                                defaultChecked={settings.school_operational_days.includes(
                                                    day.value,
                                                )}
                                                className="border-input text-primary focus:ring-primary h-4 w-4 rounded"
                                            />
                                            <span>{day.label}</span>
                                        </label>
                                    ))}
                                </div>
                                <InputError
                                    message={errors.school_operational_days}
                                />
                            </div>

                            <div className="grid max-w-xs grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="school_start_time">
                                        School starts
                                    </Label>
                                    <Input
                                        id="school_start_time"
                                        type="time"
                                        name="school_start_time"
                                        defaultValue={
                                            settings.school_start_time
                                        }
                                    />
                                    <InputError
                                        message={errors.school_start_time}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="auto_absent_cron_time">
                                        Auto-absent sweep
                                    </Label>
                                    <Input
                                        id="auto_absent_cron_time"
                                        type="time"
                                        name="auto_absent_cron_time"
                                        defaultValue={
                                            settings.auto_absent_cron_time
                                        }
                                    />
                                    <InputError
                                        message={errors.auto_absent_cron_time}
                                    />
                                </div>
                            </div>

                            <div className="flex items-center gap-3">
                                {/* Hidden input first so unchecked submits "0";
                                    FormData keeps the last occurrence per name. */}
                                <input
                                    type="hidden"
                                    name="require_checkout"
                                    value="0"
                                />
                                <input
                                    id="require_checkout"
                                    type="checkbox"
                                    name="require_checkout"
                                    value="1"
                                    defaultChecked={settings.require_checkout}
                                    className="border-input text-primary focus:ring-primary rounded"
                                />
                                <Label
                                    htmlFor="require_checkout"
                                    className="font-normal"
                                >
                                    Require check-out scan at end of day
                                </Label>
                                <InputError message={errors.require_checkout} />
                            </div>

                            <div className="grid max-w-xs grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="scan_debounce_minutes">
                                        Scan debounce (min)
                                    </Label>
                                    <Input
                                        id="scan_debounce_minutes"
                                        type="number"
                                        name="scan_debounce_minutes"
                                        min={0}
                                        max={120}
                                        defaultValue={
                                            settings.scan_debounce_minutes
                                        }
                                    />
                                    <InputError
                                        message={errors.scan_debounce_minutes}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="scan_drift_tolerance_minutes">
                                        Drift tolerance (min)
                                    </Label>
                                    <Input
                                        id="scan_drift_tolerance_minutes"
                                        type="number"
                                        name="scan_drift_tolerance_minutes"
                                        min={0}
                                        max={60}
                                        defaultValue={
                                            settings.scan_drift_tolerance_minutes
                                        }
                                    />
                                    <InputError
                                        message={
                                            errors.scan_drift_tolerance_minutes
                                        }
                                    />
                                </div>
                            </div>

                            <div className="space-y-2 rounded-lg border p-4">
                                <Label className="text-sm font-semibold">
                                    {t('shifts.staff.title')}
                                </Label>
                                <p className="text-muted-foreground text-xs">
                                    {t('shifts.staff.note')}
                                </p>
                                <div className="grid max-w-xs grid-cols-1 gap-4 pt-2 sm:grid-cols-3">
                                    <div className="grid gap-2">
                                        <Label htmlFor="staff_start_time">
                                            {t('shifts.staff.start')}
                                        </Label>
                                        <Input
                                            id="staff_start_time"
                                            type="time"
                                            name="staff_start_time"
                                            defaultValue={
                                                settings.staff_start_time
                                            }
                                        />
                                        <InputError
                                            message={errors.staff_start_time}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="staff_end_time">
                                            {t('shifts.staff.end')}
                                        </Label>
                                        <Input
                                            id="staff_end_time"
                                            type="time"
                                            name="staff_end_time"
                                            defaultValue={
                                                settings.staff_end_time
                                            }
                                        />
                                        <InputError
                                            message={errors.staff_end_time}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="staff_absent_sweep_time">
                                            {t('shifts.staff.sweep')}
                                        </Label>
                                        <Input
                                            id="staff_absent_sweep_time"
                                            type="time"
                                            name="staff_absent_sweep_time"
                                            defaultValue={
                                                settings.staff_absent_sweep_time
                                            }
                                        />
                                        <InputError
                                            message={
                                                errors.staff_absent_sweep_time
                                            }
                                        />
                                    </div>
                                </div>
                            </div>

                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                Save changes
                            </Button>
                        </div>
                    </>
                )}
            </Form>

            <div className="mt-8 space-y-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-base font-semibold">
                            {t('shifts.section.title')}
                        </h2>
                        <p className="text-muted-foreground text-xs">
                            {t('shifts.section.max_note')}
                        </p>
                    </div>
                    <Button
                        onClick={() => {
                            setEditing(null);
                            setIsFormOpen(true);
                        }}
                    >
                        <Plus className="mr-2 h-4 w-4" />
                        {t('shifts.section.create')}
                    </Button>
                </div>

                <InputError
                    message={(errors as Record<string, string>).shift}
                />

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    {t('shifts.section.columns.name')}
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    {t('shifts.section.columns.times')}
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    {t('shifts.section.columns.sweep_offset')}
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    {t('shifts.section.actions')}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {shifts.map((shift) => (
                                <tr key={shift.id} className="border-t">
                                    <td className="px-4 py-3 font-medium">
                                        <div className="flex flex-col">
                                            <span className="font-semibold">
                                                {shift.name}
                                            </span>
                                            {shift.crosses_midnight && (
                                                <span className="text-muted-foreground text-xs">
                                                    {t(
                                                        'shifts.section.crosses_midnight',
                                                    )}
                                                </span>
                                            )}
                                        </div>
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {shift.start_time} - {shift.end_time}
                                    </td>
                                    <td className="text-muted-foreground px-4 py-3">
                                        {t('shifts.section.sweep_offset', {
                                            offset: shift.sweep_offset_minutes,
                                        })}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => {
                                                    setEditing(shift);
                                                    setIsFormOpen(true);
                                                }}
                                            >
                                                <Pencil className="h-4 w-4" />
                                                <span className="sr-only">
                                                    {t(
                                                        'shifts.form.edit_title',
                                                    )}
                                                </span>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="text-destructive hover:text-destructive"
                                                onClick={() =>
                                                    setDeleting(shift)
                                                }
                                            >
                                                <Trash2 className="h-4 w-4" />
                                                <span className="sr-only">
                                                    {t('shifts.section.remove')}
                                                </span>
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}

                            {shifts.length === 0 && (
                                <tr className="border-t">
                                    <td
                                        colSpan={4}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        {t('shifts.section.empty')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {isFormOpen && (
                <ShiftFormSheet
                    shift={editing}
                    onClose={() => setIsFormOpen(false)}
                />
            )}

            <Dialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
            >
                <DialogContent>
                    <DialogTitle>{t('shifts.section.remove')}</DialogTitle>
                    <DialogDescription>
                        {t('shifts.section.remove_description', {
                            name: deleting?.name ?? '',
                        })}
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <Button
                            variant="outline"
                            onClick={() => setDeleting(null)}
                        >
                            {t('shifts.section.cancel')}
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => {
                                if (deleting) {
                                    destroy(deleting);
                                    setDeleting(null);
                                }
                            }}
                        >
                            {t('shifts.section.remove_confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

type ShiftFormSheetProps = {
    shift: ShiftRow | null;
    onClose: () => void;
};

type ShiftFormData = {
    name: string;
    start_time: string;
    end_time: string;
    sweep_offset_minutes: number | '';
};

function ShiftFormSheet({ shift, onClose }: ShiftFormSheetProps) {
    const { t } = useTranslation();
    const isEditing = shift !== null;

    const form = useForm<ShiftFormData>({
        name: shift?.name ?? '',
        start_time: shift?.start_time ?? '',
        end_time: shift?.end_time ?? '',
        sweep_offset_minutes: shift?.sweep_offset_minutes ?? 120,
    });

    const submit = (event: React.FormEvent): void => {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            sweep_offset_minutes: Number(data.sweep_offset_minutes),
        }));

        if (isEditing) {
            form.put(ShiftController.update.url({ shift: shift.id }), {
                onSuccess: onClose,
            });
        } else {
            form.post(ShiftController.store.url(), {
                onSuccess: onClose,
            });
        }
    };

    return (
        <Sheet open onOpenChange={(open) => !open && onClose()}>
            <SheetContent side="right" className="sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>
                        {isEditing
                            ? t('shifts.form.edit_title')
                            : t('shifts.form.create_title')}
                    </SheetTitle>
                    <SheetDescription>
                        {isEditing
                            ? t('shifts.form.edit_description')
                            : t('shifts.form.create_description')}
                    </SheetDescription>
                </SheetHeader>

                <form
                    onSubmit={submit}
                    className="flex flex-1 flex-col justify-between space-y-4 overflow-y-auto px-4"
                >
                    <div className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="shift-name">
                                {t('shifts.form.name')}
                            </Label>
                            <Input
                                id="shift-name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                required
                                autoFocus
                                autoComplete="off"
                                maxLength={100}
                                placeholder={t('shifts.form.name_placeholder')}
                            />
                            <InputError message={form.errors.name} />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="shift-start-time">
                                    {t('shifts.form.start_time')}
                                </Label>
                                <Input
                                    id="shift-start-time"
                                    type="time"
                                    value={form.data.start_time}
                                    onChange={(event) =>
                                        form.setData(
                                            'start_time',
                                            event.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError message={form.errors.start_time} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="shift-end-time">
                                    {t('shifts.form.end_time')}
                                </Label>
                                <Input
                                    id="shift-end-time"
                                    type="time"
                                    value={form.data.end_time}
                                    onChange={(event) =>
                                        form.setData(
                                            'end_time',
                                            event.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError message={form.errors.end_time} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="shift-sweep-offset">
                                {t('shifts.form.sweep_offset_minutes')}
                            </Label>
                            <Input
                                id="shift-sweep-offset"
                                type="number"
                                min={30}
                                max={480}
                                value={form.data.sweep_offset_minutes}
                                onChange={(event) =>
                                    form.setData(
                                        'sweep_offset_minutes',
                                        event.target.value === ''
                                            ? ''
                                            : Number(event.target.value),
                                    )
                                }
                            />
                            <p className="text-muted-foreground text-xs">
                                {t('shifts.form.sweep_offset_hint')}
                            </p>
                            <InputError
                                message={form.errors.sweep_offset_minutes}
                            />
                        </div>
                    </div>

                    <SheetFooter className="flex flex-row justify-end gap-2 border-t px-0 pt-4">
                        <Button
                            variant="outline"
                            type="button"
                            onClick={onClose}
                        >
                            {t('shifts.section.cancel')}
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner className="mr-2" />}
                            {isEditing
                                ? t('shifts.form.submit_update')
                                : t('shifts.form.submit_create')}
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}
