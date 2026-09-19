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
        studentNumber: string;
        classId: string;
    };
    classes: ClassOption[];
    guardians: GuardianOption[];
    currentGuardianIds?: number[];
};

export default function StudentForm({
    action,
    submitLabel,
    defaults,
    classes,
    guardians,
    currentGuardianIds = [],
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
                            <div className="grid gap-2">
                                {guardians.map((guardian) => (
                                    <label
                                        key={guardian.id}
                                        className="flex items-center gap-3 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            name="guardian_ids[]"
                                            value={guardian.id}
                                            defaultChecked={currentGuardianIds.includes(
                                                guardian.id,
                                            )}
                                            className="border-input dark:bg-input/30 size-4 shrink-0 rounded-[4px] border shadow-xs outline-none accent-primary"
                                        />
                                        <span>{guardian.name}</span>
                                        <span className="text-muted-foreground">
                                            {guardian.phone_number}
                                        </span>
                                    </label>
                                ))}
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
