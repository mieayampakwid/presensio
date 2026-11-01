import { Form, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';

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
    cancelUrl: string;
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

const RELIGIONS = [
    'Islam',
    'Kristen',
    'Katolik',
    'Hindu',
    'Buddha',
    'Konghucu',
];

const RELATIONSHIPS = ['father', 'mother', 'guardian'];

export default function StudentForm({
    action,
    cancelUrl,
    submitLabel,
    defaults,
    classes,
    guardians,
    currentGuardianIds = [],
    currentGuardians,
}: StudentFormProps) {
    const { t } = useTranslation();

    return (
        <Form {...action} className="space-y-6">
            {({ processing, errors }) => (
                <div className="grid gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="full_name">
                            {t('students.form.full_name')}
                        </Label>
                        <Input
                            id="full_name"
                            name="full_name"
                            defaultValue={defaults.fullName}
                            required
                            autoFocus
                            autoComplete="off"
                            placeholder={t(
                                'students.form.full_name_placeholder',
                            )}
                        />
                        <InputError message={errors.full_name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="nickname">
                            {t('students.form.nickname')}
                        </Label>
                        <Input
                            id="nickname"
                            name="nickname"
                            defaultValue={defaults.nickname}
                            autoComplete="off"
                        />
                        <InputError message={errors.nickname} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="dob">{t('students.form.dob')}</Label>
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
                        <Label htmlFor="gender">
                            {t('students.form.gender')}
                        </Label>
                        <select
                            id="gender"
                            name="gender"
                            defaultValue={defaults.gender ?? 'L'}
                            required
                            className="border-input dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-base shadow-xs outline-none focus-visible:ring-[3px] md:text-sm"
                        >
                            <option value="L">{t('common.gender.male')}</option>
                            <option value="P">
                                {t('common.gender.female')}
                            </option>
                        </select>
                        <InputError message={errors.gender} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="birth_place">
                            {t('students.form.birth_place')}
                        </Label>
                        <Input
                            id="birth_place"
                            name="birth_place"
                            defaultValue={defaults.birthPlace ?? ''}
                            placeholder={t(
                                'students.form.birth_place_placeholder',
                            )}
                            autoComplete="off"
                        />
                        <InputError message={errors.birth_place} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="religion">
                            {t('students.form.religion')}
                        </Label>
                        <select
                            id="religion"
                            name="religion"
                            defaultValue={defaults.religion ?? ''}
                            className="border-input dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-base shadow-xs outline-none focus-visible:ring-[3px] md:text-sm"
                        >
                            <option value="">
                                {t('students.form.religion_placeholder')}
                            </option>
                            {RELIGIONS.map((religion) => (
                                <option key={religion} value={religion}>
                                    {t(`students.form.religions.${religion}`)}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.religion} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="address">
                            {t('students.form.address')}
                        </Label>
                        <textarea
                            id="address"
                            name="address"
                            defaultValue={defaults.address ?? ''}
                            rows={3}
                            placeholder={t('students.form.address_placeholder')}
                            className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 dark:bg-input/30 flex field-sizing-content min-h-16 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                        />
                        <InputError message={errors.address} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="student_number">
                            {t('students.form.student_number')}
                        </Label>
                        <Input
                            id="student_number"
                            name="student_number"
                            defaultValue={defaults.studentNumber}
                            autoComplete="off"
                            placeholder={t(
                                'students.form.student_number_placeholder',
                            )}
                        />
                        <InputError message={errors.student_number} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="class_id">
                            {t('students.form.class')}
                        </Label>
                        <select
                            id="class_id"
                            name="class_id"
                            defaultValue={defaults.classId}
                            className="border-input dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-base shadow-xs outline-none focus-visible:ring-[3px] md:text-sm"
                        >
                            <option value="">
                                {t('students.form.class_empty')}
                            </option>
                            {classes.map((schoolClass) => (
                                <option
                                    key={schoolClass.id}
                                    value={schoolClass.id}
                                >
                                    {schoolClass.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.class_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label>{t('students.form.guardians')}</Label>
                        {guardians.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                {t('students.form.guardians_empty')}
                            </p>
                        ) : (
                            <div className="grid gap-3">
                                {guardians.map((guardian) => {
                                    const isLinked = currentGuardians
                                        ? currentGuardians.some(
                                              (g) => g.id === guardian.id,
                                          )
                                        : currentGuardianIds.includes(
                                              guardian.id,
                                          );
                                    const relationshipType =
                                        currentGuardians?.find(
                                            (g) => g.id === guardian.id,
                                        )?.relationship_type ?? 'guardian';

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
                                                    className="border-input dark:bg-input/30 accent-primary size-4 shrink-0 rounded-[4px] border shadow-xs outline-none"
                                                />
                                                <span className="font-medium">
                                                    {guardian.name}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    {guardian.phone_number}
                                                </span>
                                            </label>
                                            <div className="flex items-center gap-2">
                                                <Label
                                                    htmlFor={`rel_${guardian.id}`}
                                                    className="text-muted-foreground text-xs"
                                                >
                                                    {t(
                                                        'students.form.relationship',
                                                    )}
                                                </Label>
                                                <select
                                                    id={`rel_${guardian.id}`}
                                                    name={`guardian_relationships[${guardian.id}]`}
                                                    defaultValue={
                                                        relationshipType
                                                    }
                                                    className="border-input dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-8 rounded-md border bg-transparent px-2 py-0.5 text-xs shadow-xs outline-none focus-visible:ring-[2px]"
                                                >
                                                    {RELATIONSHIPS.map(
                                                        (relationship) => (
                                                            <option
                                                                key={
                                                                    relationship
                                                                }
                                                                value={
                                                                    relationship
                                                                }
                                                            >
                                                                {t(
                                                                    `students.form.relationships.${relationship}`,
                                                                )}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                        <InputError message={errors.guardian_ids} />
                    </div>

                    <div className="flex gap-3">
                        <Button variant="outline" asChild>
                            <Link href={cancelUrl}>{t('common.cancel')}</Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {submitLabel}
                        </Button>
                    </div>
                </div>
            )}
        </Form>
    );
}
