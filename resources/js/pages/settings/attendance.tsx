import { Form, Head } from '@inertiajs/react';
import AttendanceSettingsController from '@/actions/App/Http/Controllers/Settings/AttendanceSettingsController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Settings = {
    school_timezone: string;
    school_start_time: string;
    auto_absent_cron_time: string;
    require_checkout: boolean;
    scan_debounce_minutes: number;
    scan_drift_tolerance_minutes: number;
    school_operational_days: number[];
};

type Props = {
    settings: Settings;
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

export default function AttendanceSettings({ settings }: Props) {
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
                                    Pilih hari-hari kegiatan belajar mengajar berlangsung. Absensi otomatis dan perhitungan ketidakhadiran hanya aktif pada hari operasional.
                                </p>
                                <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 pt-2">
                                    {WEEKDAYS.map((day) => (
                                        <label
                                            key={day.value}
                                            className="flex items-center gap-2 text-sm cursor-pointer"
                                        >
                                            <input
                                                type="checkbox"
                                                name="school_operational_days[]"
                                                value={day.value}
                                                defaultChecked={settings.school_operational_days.includes(day.value)}
                                                className="border-input text-primary focus:ring-primary rounded h-4 w-4"
                                            />
                                            <span>{day.label}</span>
                                        </label>
                                    ))}
                                </div>
                                <InputError message={errors.school_operational_days} />
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
                                <InputError
                                    message={errors.require_checkout}
                                />
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

                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                Save changes
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}
