import { Head, Form, Link, router, setLayoutProps } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { useState } from 'react';
import AcademicYearController from '@/actions/App/Http/Controllers/AcademicYears/AcademicYearController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
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
import { useTranslation } from '@/hooks/use-translation';

type YearOption = {
    id: number;
    name: string;
    starts_at: string;
    ends_at: string;
};

type ClassOption = {
    id: number;
    name: string;
};

type SourceClass = {
    id: number;
    name: string;
    students_count: number;
};

type Teacher = {
    id: number;
    name: string;
};

type Props = {
    source: { id: number; name: string } | null;
    target_years: YearOption[];
    target_year_id: number | null;
    target_classes: ClassOption[];
    source_classes: SourceClass[];
    teachers: Teacher[];
    already_promoted: boolean;
};

type MappingMode = 'existing' | 'new' | 'none';

type Mapping = {
    mode: MappingMode;
    target_class_id: string;
    new_name: string;
    new_teacher_id: string;
    copy_subjects: boolean;
};

const SELECT_CLASS =
    'border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50';

export default function AcademicYearsRollOver({
    source,
    target_years,
    target_year_id,
    target_classes,
    source_classes,
    teachers,
    already_promoted,
}: Props) {
    const [mappings, setMappings] = useState<Record<number, Mapping>>(() =>
        Object.fromEntries(
            source_classes.map((schoolClass) => [
                schoolClass.id,
                {
                    mode: 'existing' as MappingMode,
                    target_class_id: '',
                    new_name: schoolClass.name,
                    new_teacher_id: '',
                    copy_subjects: true,
                },
            ]),
        ),
    );
    const [confirming, setConfirming] = useState(false);
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [
            {
                title: t('academic_years.index.title'),
                href: AcademicYearController.index().url,
            },
            { title: t('academic_years.roll_over.title') },
        ],
    });

    if (source === null) {
        return (
            <>
                <Head title={t('academic_years.roll_over.title')} />
                <div className="space-y-6 p-4">
                    <Heading title={t('academic_years.roll_over.title')} />
                    <p className="text-muted-foreground text-sm">
                        {t('academic_years.roll_over.no_active_year')}
                    </p>
                </div>
            </>
        );
    }

    const targetYear = target_years.find((year) => year.id === target_year_id);

    const changeTargetYear = (yearId: string) => {
        router.get(
            AcademicYearController.rollOver().url,
            { target_year_id: yearId },
            { preserveState: true },
        );
    };

    const setMapping = (classId: number, patch: Partial<Mapping>) => {
        setMappings((current) => ({
            ...current,
            [classId]: { ...current[classId], ...patch },
        }));
    };

    const mappedCount = source_classes.filter(
        (schoolClass) => mappings[schoolClass.id]?.mode !== 'none',
    ).length;

    return (
        <>
            <Head title={t('academic_years.roll_over.title')} />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        title={t('academic_years.roll_over.title')}
                        description={t('academic_years.roll_over.description', {
                            source: source.name,
                        })}
                    />
                    <Button variant="outline" asChild>
                        <Link href={AcademicYearController.index().url}>
                            {t('academic_years.roll_over.back')}
                        </Link>
                    </Button>
                </div>

                {already_promoted && (
                    <Alert>
                        <AlertTriangle className="h-4 w-4" />
                        <AlertDescription>
                            {t('academic_years.roll_over.already_promoted')}
                        </AlertDescription>
                    </Alert>
                )}

                <div className="flex flex-wrap items-end gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="target_year_id">
                            {t('academic_years.roll_over.target_year')}
                        </Label>
                        <select
                            id="target_year_id"
                            defaultValue={target_year_id ?? ''}
                            className={`${SELECT_CLASS} w-72`}
                            onChange={(event) =>
                                changeTargetYear(event.target.value)
                            }
                        >
                            {target_years.map((year) => (
                                <option key={year.id} value={year.id}>
                                    {year.name} ({year.starts_at} —{' '}
                                    {year.ends_at})
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                {target_year_id !== null && (
                    <Form
                        id="roll-over-form"
                        {...AcademicYearController.applyRollOver.form()}
                    >
                        {({ processing, errors }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="target_year_id"
                                    value={target_year_id}
                                />

                                <div className="grid gap-2">
                                    <Label htmlFor="effective_on">
                                        {t(
                                            'academic_years.roll_over.effective_on',
                                        )}
                                    </Label>
                                    <Input
                                        id="effective_on"
                                        type="date"
                                        name="effective_on"
                                        defaultValue={
                                            targetYear?.starts_at ?? ''
                                        }
                                        className="w-56"
                                    />
                                    <InputError message={errors.effective_on} />
                                </div>

                                <div className="overflow-x-auto rounded-lg border">
                                    <table className="w-full text-sm">
                                        <thead className="bg-muted/50 text-muted-foreground">
                                            <tr>
                                                <th className="px-4 py-3 text-left font-medium">
                                                    {t(
                                                        'academic_years.roll_over.columns.source_class',
                                                        { year: source.name },
                                                    )}
                                                </th>
                                                <th className="px-4 py-3 text-left font-medium">
                                                    {t(
                                                        'academic_years.roll_over.columns.students',
                                                    )}
                                                </th>
                                                <th className="px-4 py-3 text-left font-medium">
                                                    {t(
                                                        'academic_years.roll_over.columns.destination',
                                                    )}
                                                </th>
                                                <th className="px-4 py-3 text-left font-medium">
                                                    {t(
                                                        'academic_years.roll_over.columns.details',
                                                    )}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {source_classes.map(
                                                (schoolClass) => {
                                                    const mapping =
                                                        mappings[
                                                            schoolClass.id
                                                        ] ??
                                                        ({
                                                            mode: 'existing',
                                                            target_class_id: '',
                                                            new_name:
                                                                schoolClass.name,
                                                            new_teacher_id: '',
                                                            copy_subjects: true,
                                                        } satisfies Mapping);

                                                    return (
                                                        <tr
                                                            key={schoolClass.id}
                                                            className="border-t"
                                                        >
                                                            <td className="px-4 py-3 font-medium">
                                                                {
                                                                    schoolClass.name
                                                                }
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                {
                                                                    schoolClass.students_count
                                                                }
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                <select
                                                                    name={`mappings[${schoolClass.id}][mode]`}
                                                                    value={
                                                                        mapping.mode
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        setMapping(
                                                                            schoolClass.id,
                                                                            {
                                                                                mode: event
                                                                                    .target
                                                                                    .value as MappingMode,
                                                                            },
                                                                        )
                                                                    }
                                                                    className={`${SELECT_CLASS} w-56`}
                                                                >
                                                                    <option value="existing">
                                                                        {t(
                                                                            'academic_years.roll_over.mode_existing',
                                                                        )}
                                                                    </option>
                                                                    <option value="new">
                                                                        {t(
                                                                            'academic_years.roll_over.mode_new',
                                                                        )}
                                                                    </option>
                                                                    <option value="none">
                                                                        {t(
                                                                            'academic_years.roll_over.mode_none',
                                                                        )}
                                                                    </option>
                                                                </select>
                                                            </td>
                                                            <td className="space-y-1 px-4 py-3">
                                                                {mapping.mode ===
                                                                    'existing' && (
                                                                    <>
                                                                        <select
                                                                            name={`mappings[${schoolClass.id}][target_class_id]`}
                                                                            value={
                                                                                mapping.target_class_id
                                                                            }
                                                                            onChange={(
                                                                                event,
                                                                            ) =>
                                                                                setMapping(
                                                                                    schoolClass.id,
                                                                                    {
                                                                                        target_class_id:
                                                                                            event
                                                                                                .target
                                                                                                .value,
                                                                                    },
                                                                                )
                                                                            }
                                                                            className={`${SELECT_CLASS} w-56`}
                                                                        >
                                                                            <option value="">
                                                                                {t(
                                                                                    'academic_years.roll_over.target_class_placeholder',
                                                                                )}
                                                                            </option>
                                                                            {target_classes.map(
                                                                                (
                                                                                    option,
                                                                                ) => (
                                                                                    <option
                                                                                        key={
                                                                                            option.id
                                                                                        }
                                                                                        value={
                                                                                            option.id
                                                                                        }
                                                                                    >
                                                                                        {
                                                                                            option.name
                                                                                        }
                                                                                    </option>
                                                                                ),
                                                                            )}
                                                                        </select>
                                                                        <InputError
                                                                            message={
                                                                                errors[
                                                                                    `mappings.${schoolClass.id}.target_class_id`
                                                                                ]
                                                                            }
                                                                        />
                                                                    </>
                                                                )}
                                                                {mapping.mode ===
                                                                    'new' && (
                                                                    <>
                                                                        <Input
                                                                            name={`mappings[${schoolClass.id}][new_name]`}
                                                                            defaultValue={
                                                                                mapping.new_name
                                                                            }
                                                                            placeholder={t(
                                                                                'academic_years.roll_over.new_class_name_placeholder',
                                                                            )}
                                                                            className="w-56"
                                                                        />
                                                                        <select
                                                                            name={`mappings[${schoolClass.id}][new_teacher_id]`}
                                                                            defaultValue={
                                                                                mapping.new_teacher_id
                                                                            }
                                                                            className={`${SELECT_CLASS} w-56`}
                                                                        >
                                                                            <option value="">
                                                                                {t(
                                                                                    'academic_years.roll_over.new_homeroom_teacher',
                                                                                )}
                                                                            </option>
                                                                            {teachers.map(
                                                                                (
                                                                                    teacher,
                                                                                ) => (
                                                                                    <option
                                                                                        key={
                                                                                            teacher.id
                                                                                        }
                                                                                        value={
                                                                                            teacher.id
                                                                                        }
                                                                                    >
                                                                                        {
                                                                                            teacher.name
                                                                                        }
                                                                                    </option>
                                                                                ),
                                                                            )}
                                                                        </select>
                                                                    </>
                                                                )}
                                                                {mapping.mode ===
                                                                    'none' && (
                                                                    <span className="text-muted-foreground text-xs">
                                                                        {t(
                                                                            schoolClass.students_count ===
                                                                                1
                                                                                ? 'academic_years.roll_over.alumni_one'
                                                                                : 'academic_years.roll_over.alumni_many',
                                                                            {
                                                                                count: schoolClass.students_count,
                                                                            },
                                                                        )}
                                                                    </span>
                                                                )}
                                                                {mapping.mode !==
                                                                    'none' && (
                                                                    <label className="text-muted-foreground flex items-center gap-1.5 text-xs whitespace-nowrap">
                                                                        <input
                                                                            type="hidden"
                                                                            name={`mappings[${schoolClass.id}][copy_subjects]`}
                                                                            value="0"
                                                                        />
                                                                        <input
                                                                            type="checkbox"
                                                                            name={`mappings[${schoolClass.id}][copy_subjects]`}
                                                                            value="1"
                                                                            defaultChecked={
                                                                                mapping.copy_subjects
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                setMapping(
                                                                                    schoolClass.id,
                                                                                    {
                                                                                        copy_subjects:
                                                                                            e
                                                                                                .target
                                                                                                .checked,
                                                                                    },
                                                                                )
                                                                            }
                                                                            className="h-3.5 w-3.5 rounded border-gray-300"
                                                                        />
                                                                        <span>
                                                                            {t(
                                                                                'academic_years.roll_over.copy_subjects',
                                                                            )}
                                                                        </span>
                                                                    </label>
                                                                )}
                                                            </td>
                                                        </tr>
                                                    );
                                                },
                                            )}

                                            {source_classes.length === 0 && (
                                                <tr className="border-t">
                                                    <td
                                                        colSpan={4}
                                                        className="text-muted-foreground px-4 py-8 text-center"
                                                    >
                                                        {t(
                                                            'academic_years.roll_over.empty',
                                                        )}
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>

                                <div className="flex items-center justify-between gap-4">
                                    <p className="text-muted-foreground text-sm">
                                        {t(
                                            'academic_years.roll_over.successor_summary',
                                            {
                                                mapped: mappedCount,
                                                total: source_classes.length,
                                            },
                                        )}
                                    </p>
                                    <Button
                                        type="button"
                                        disabled={
                                            processing ||
                                            source_classes.length === 0
                                        }
                                        onClick={() => setConfirming(true)}
                                    >
                                        {t('academic_years.roll_over.apply')}
                                    </Button>
                                </div>

                                <Dialog
                                    open={confirming}
                                    onOpenChange={(isOpen) => {
                                        if (!processing) {
                                            setConfirming(isOpen);
                                        }
                                    }}
                                >
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle>
                                                {t(
                                                    'academic_years.roll_over.confirm_title',
                                                )}
                                            </DialogTitle>
                                            <DialogDescription>
                                                {t(
                                                    'academic_years.roll_over.confirm_description',
                                                    {
                                                        source: source.name,
                                                        target:
                                                            targetYear?.name ??
                                                            t(
                                                                'academic_years.roll_over.default_target',
                                                            ),
                                                    },
                                                )}
                                            </DialogDescription>
                                        </DialogHeader>
                                        <DialogFooter className="gap-2">
                                            <DialogClose asChild>
                                                <Button
                                                    variant="secondary"
                                                    type="button"
                                                >
                                                    {t('common.cancel')}
                                                </Button>
                                            </DialogClose>
                                            <Button
                                                type="submit"
                                                form="roll-over-form"
                                                disabled={processing}
                                            >
                                                {t(
                                                    'academic_years.roll_over.apply',
                                                )}
                                            </Button>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </>
    );
}
