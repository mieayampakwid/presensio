import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export type EmployeeFormDefaults = {
    name: string;
    employeeNumber?: string | null;
    phoneNumber?: string | null;
    employmentType?: string;
    position?: string | null;
    workingDays?: number[] | null;
    isActive?: boolean;
    isTeacher?: boolean;
    userId?: number | null;
};

type Option = { value: string; label: string };
type UserOption = { id: number; username: string };

type EmployeeFormProps = {
    action: Record<string, unknown>;
    submitLabel: string;
    defaults: EmployeeFormDefaults;
    employmentTypes?: Option[];
    operationalDays?: number[];
    availableUsers?: UserOption[];
    lockIsTeacher?: boolean;
};

const DAY_NAMES: Record<number, string> = {
    1: 'Senin',
    2: 'Selasa',
    3: 'Rabu',
    4: 'Kamis',
    5: 'Jumat',
    6: 'Sabtu',
    7: 'Minggu',
};

const DEFAULT_EMPLOYMENT_TYPES: Option[] = [
    { value: 'pns', label: 'PNS' },
    { value: 'pppk', label: 'PPPK' },
    { value: 'permanent', label: 'Tetap (GTY/PTY)' },
    { value: 'contract', label: 'Kontrak (GTT/PTT)' },
    { value: 'honorary', label: 'Honorer' },
];

export default function EmployeeForm({
    action,
    submitLabel,
    defaults,
    employmentTypes = DEFAULT_EMPLOYMENT_TYPES,
    operationalDays = [1, 2, 3, 4, 5],
    availableUsers = [],
    lockIsTeacher = false,
}: EmployeeFormProps) {
    const [selectedDays, setSelectedDays] = useState<number[]>(
        defaults.workingDays ?? operationalDays,
    );
    const [allOperational, setAllOperational] = useState<boolean>(
        defaults.workingDays === null || defaults.workingDays === undefined,
    );
    const [isTeacher, setIsTeacher] = useState<boolean>(
        lockIsTeacher ? true : (defaults.isTeacher ?? false),
    );
    const [isActive, setIsActive] = useState<boolean>(
        defaults.isActive ?? true,
    );

    const toggleDay = (day: number) => {
        if (selectedDays.includes(day)) {
            setSelectedDays(selectedDays.filter((d) => d !== day));
        } else {
            setSelectedDays([...selectedDays, day].sort((a, b) => a - b));
        }
    };
    return (
        <Form {...action} className="space-y-6">
            {({ processing, errors }) => (
                <div className="grid max-w-2xl gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="name">
                            Nama Lengkap (dengan gelar)
                        </Label>
                        <Input
                            id="name"
                            name="name"
                            defaultValue={defaults.name}
                            required
                            autoFocus
                            autoComplete="off"
                            placeholder="e.g. Budi Santoso, S.Pd."
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="employee_number">
                                Nomor Identitas Pegawai (NIP / NUPTK / NIY)
                            </Label>
                            <Input
                                id="employee_number"
                                name="employee_number"
                                defaultValue={defaults.employeeNumber ?? ''}
                                autoComplete="off"
                                placeholder="e.g. 197501012000031002"
                            />
                            <InputError message={errors.employee_number} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="phone_number">
                                Nomor Telepon / WhatsApp
                            </Label>
                            <Input
                                id="phone_number"
                                name="phone_number"
                                defaultValue={defaults.phoneNumber ?? ''}
                                autoComplete="off"
                                placeholder="e.g. +6281234567890"
                            />
                            <InputError message={errors.phone_number} />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="employment_type">
                                Status Kepegawaian
                            </Label>
                            <select
                                id="employment_type"
                                name="employment_type"
                                required
                                defaultValue={
                                    defaults.employmentType ?? 'permanent'
                                }
                                className="border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                            >
                                {employmentTypes.map((type) => (
                                    <option key={type.value} value={type.value}>
                                        {type.label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.employment_type} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="position">Jabatan / Posisi</Label>
                            <Input
                                id="position"
                                name="position"
                                defaultValue={defaults.position ?? ''}
                                autoComplete="off"
                                placeholder="e.g. Guru Kelas / Staf TU / Satpam"
                            />
                            <InputError message={errors.position} />
                        </div>
                    </div>

                    {availableUsers.length > 0 && (
                        <div className="grid gap-2">
                            <Label htmlFor="user_id">
                                Akun Login Pengguna (Opsional)
                            </Label>
                            <select
                                id="user_id"
                                name="user_id"
                                defaultValue={defaults.userId ?? ''}
                                className="border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                            >
                                <option value="">
                                    Belum ditautkan ke akun
                                </option>
                                {availableUsers.map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {u.username}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.user_id} />
                        </div>
                    )}

                    <div className="space-y-3 rounded-lg border p-4">
                        <Label>Hari Kerja Pegawai</Label>
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="all_operational"
                                checked={allOperational}
                                onCheckedChange={(checked) => {
                                    const val = Boolean(checked);
                                    setAllOperational(val);
                                    if (val) {
                                        setSelectedDays(operationalDays);
                                    }
                                }}
                            />
                            <label
                                htmlFor="all_operational"
                                className="cursor-pointer text-sm font-medium select-none"
                            >
                                Mengikuti semua hari operasional sekolah
                            </label>
                        </div>

                        {!allOperational && (
                            <div className="pt-2">
                                <p className="text-muted-foreground mb-2 text-xs">
                                    Pilih hari kerja khusus untuk pegawai ini:
                                </p>
                                <div className="flex flex-wrap gap-4">
                                    {operationalDays.map((day) => (
                                        <div
                                            key={day}
                                            className="flex items-center gap-2"
                                        >
                                            <Checkbox
                                                id={`day_${day}`}
                                                checked={selectedDays.includes(
                                                    day,
                                                )}
                                                onCheckedChange={() =>
                                                    toggleDay(day)
                                                }
                                            />
                                            <label
                                                htmlFor={`day_${day}`}
                                                className="cursor-pointer text-sm select-none"
                                            >
                                                {DAY_NAMES[day] ??
                                                    `Hari ${day}`}
                                            </label>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Hidden inputs for form submit */}
                        {!allOperational && (
                            <input
                                type="hidden"
                                name="working_days_custom"
                                value="1"
                            />
                        )}
                        {!allOperational &&
                            selectedDays.map((day) => (
                                <input
                                    key={day}
                                    type="hidden"
                                    name="working_days[]"
                                    value={day}
                                />
                            ))}
                        <InputError message={errors.working_days} />
                    </div>

                    <div className="space-y-4 rounded-lg border p-4">
                        <Label>Konfigurasi Peran & Status</Label>
                        <div className="flex flex-col gap-3">
                            {!lockIsTeacher ? (
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="is_teacher"
                                        checked={isTeacher}
                                        onCheckedChange={(checked) =>
                                            setIsTeacher(Boolean(checked))
                                        }
                                    />
                                    <input
                                        type="hidden"
                                        name="is_teacher"
                                        value={isTeacher ? '1' : '0'}
                                    />
                                    <label
                                        htmlFor="is_teacher"
                                        className="cursor-pointer text-sm select-none"
                                    >
                                        Pegawai ini adalah Guru / Tenaga
                                        Pengajar
                                    </label>
                                </div>
                            ) : (
                                <input
                                    type="hidden"
                                    name="is_teacher"
                                    value="1"
                                />
                            )}
                            <InputError message={errors.is_teacher} />

                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="is_active"
                                    checked={isActive}
                                    onCheckedChange={(checked) =>
                                        setIsActive(Boolean(checked))
                                    }
                                />
                                <input
                                    type="hidden"
                                    name="is_active"
                                    value={isActive ? '1' : '0'}
                                />
                                <label
                                    htmlFor="is_active"
                                    className="cursor-pointer text-sm select-none"
                                >
                                    Status Aktif
                                </label>
                            </div>
                            <InputError message={errors.is_active} />
                        </div>
                    </div>

                    <Button type="submit" disabled={processing}>
                        {processing && <Spinner />}
                        {submitLabel}
                    </Button>
                </div>
            )}
        </Form>
    );
}
