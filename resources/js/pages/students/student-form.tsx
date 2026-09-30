import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type ClassOption = {
    id: number;
    name: string;
};

type GuardianOption = {
    id: number;
    name: string;
    phone_number: string;
};

type StudentFormProps = {
    action: Record<string, unknown>;
    submitLabel: string;
    defaults: {
        fullName: string;
        nickname: string;
        dob: string;
        gender?: string;
        birthPlace?: string;
        religion?: string;
        address?: string;
        studentNumber: string;
        classId: string;
    };
    classes: ClassOption[];
    guardians: GuardianOption[];
    currentGuardianIds?: number[];
    currentGuardians?: { id: number; relationship_type: string }[];
};

export default function StudentForm({
    action,
    submitLabel,
    defaults,
    classes,
    guardians,
    currentGuardianIds = [],
    currentGuardians,
}: StudentFormProps) {
    return (
        <Form {...action} className="space-y-6">
            {({ processing, errors }) => (
                <div className="grid gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="full_name">Full name</Label>
                        <Input
                            id="full_name"
                            name="full_name"
                            defaultValue={defaults.fullName}
                            required
                            autoFocus
                            autoComplete="off"
                            placeholder="Full name as on school records"
                        />
                        <InputError message={errors.full_name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="nickname">Nickname (optional)</Label>
                        <Input
                            id="nickname"
                            name="nickname"
                            defaultValue={defaults.nickname}
                            autoComplete="off"
                        />
                        <InputError message={errors.nickname} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="dob">Date of birth</Label>
                        <Input
                            id="dob"
                            name="dob"
                            type="date"
                            defaultValue={defaults.dob}
                            required
                        />
                        <InputError message={errors.dob} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="gender">Gender</Label>
                        <select
                            id="gender"
                            name="gender"
                            defaultValue={defaults.gender ?? 'L'}
                            required
                            className="border-input dark:bg-input/30 flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] md:text-sm"
                        >
                            <option value="L">Laki-laki (L)</option>
                            <option value="P">Perempuan (P)</option>
                        </select>
                        <InputError message={errors.gender} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="birth_place">Birth place (optional)</Label>
                        <Input
                            id="birth_place"
                            name="birth_place"
                            defaultValue={defaults.birthPlace ?? ''}
                            placeholder="e.g. Jakarta"
                            autoComplete="off"
                        />
                        <InputError message={errors.birth_place} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="religion">Religion (optional)</Label>
                        <select
                            id="religion"
                            name="religion"
                            defaultValue={defaults.religion ?? ''}
                            className="border-input dark:bg-input/30 flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] md:text-sm"
                        >
                            <option value="">Select religion</option>
                            <option value="Islam">Islam</option>
                            <option value="Kristen">Kristen</option>
                            <option value="Katolik">Katolik</option>
                            <option value="Hindu">Hindu</option>
                            <option value="Buddha">Buddha</option>
                            <option value="Konghucu">Konghucu</option>
                        </select>
                        <InputError message={errors.religion} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="address">Address (optional)</Label>
                        <textarea
                            id="address"
                            name="address"
                            defaultValue={defaults.address ?? ''}
                            rows={3}
                            placeholder="Home address"
                            className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 dark:bg-input/30 flex field-sizing-content min-h-16 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                        />
                        <InputError message={errors.address} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="student_number">
                            Student number (optional)
                        </Label>
                        <Input
                            id="student_number"
                            name="student_number"
                            defaultValue={defaults.studentNumber}
                            autoComplete="off"
                            placeholder="NIS — leading zeros are kept"
                        />
                        <InputError message={errors.student_number} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="class_id">Class (optional)</Label>
                        <select
                            id="class_id"
                            name="class_id"
                            defaultValue={defaults.classId}
                            className="border-input dark:bg-input/30 flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] md:text-sm"
                        >
                            <option value="">No class yet</option>
                            {classes.map((schoolClass) => (
                                <option key={schoolClass.id} value={schoolClass.id}>
                                    {schoolClass.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.class_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label>Guardians</Label>
                        {guardians.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                No guardians recorded yet. Create guardians
                                first to link them here.
                            </p>
                        ) : (
                            <div className="grid gap-3">
                                {guardians.map((guardian) => {
                                    const isLinked = currentGuardians
                                        ? currentGuardians.some((g) => g.id === guardian.id)
                                        : currentGuardianIds.includes(guardian.id);
                                    const relationshipType =
                                        currentGuardians?.find((g) => g.id === guardian.id)
                                            ?.relationship_type ?? 'guardian';

                                    return (
                                        <div
                                            key={guardian.id}
                                            className="border-input dark:bg-input/10 flex flex-wrap items-center justify-between gap-3 rounded-md border p-3 text-sm"
                                        >
                                            <label className="flex cursor-pointer items-center gap-3">
                                                <input
                                                    type="checkbox"
                                                    name="guardian_ids[]"
                                                    value={guardian.id}
                                                    defaultChecked={isLinked}
                                                    className="border-input dark:bg-input/30 size-4 shrink-0 rounded-[4px] border shadow-xs outline-none accent-primary"
                                                />
                                                <span className="font-medium">{guardian.name}</span>
                                                <span className="text-muted-foreground">
                                                    {guardian.phone_number}
                                                </span>
                                            </label>
                                            <div className="flex items-center gap-2">
                                                <Label
                                                    htmlFor={`rel_${guardian.id}`}
                                                    className="text-muted-foreground text-xs"
                                                >
                                                    Relationship
                                                </Label>
                                                <select
                                                    id={`rel_${guardian.id}`}
                                                    name={`guardian_relationships[${guardian.id}]`}
                                                    defaultValue={relationshipType}
                                                    className="border-input dark:bg-input/30 flex h-8 rounded-md border bg-transparent px-2 py-0.5 text-xs shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[2px]"
                                                >
                                                    <option value="father">Ayah (Father)</option>
                                                    <option value="mother">Ibu (Mother)</option>
                                                    <option value="guardian">Wali (Guardian)</option>
                                                </select>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                        <InputError message={errors.guardian_ids} />
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
