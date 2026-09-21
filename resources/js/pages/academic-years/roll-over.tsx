import { Head, Form, Link, router } from '@inertiajs/react';
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
                },
            ]),
        ),
    );
    const [confirming, setConfirming] = useState(false);

    if (source === null) {
        return (
            <>
                <Head title="Roll over" />
                <div className="space-y-6 p-4">
                    <Heading title="Roll over" />
                    <p className="text-muted-foreground text-sm">
                        No active year — set one first.
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

    const setMapping = (
        classId: number,
        patch: Partial<Mapping>,
    ) => {
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
            <Head title="Roll over" />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        title="Roll over"
                        description={`Promote ${source.name} rosters into the target year — the target becomes the active year.`}
                    />
                    <Button variant="outline" asChild>
                        <Link href={AcademicYearController.index().url}>Back</Link>
                    </Button>
                </div>

                {already_promoted && (
                    <Alert>
                        <AlertTriangle className="h-4 w-4" />
                        <AlertDescription>
                            The selected target year already has enrollments — it was
                            promoted before. Applying again does nothing.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="flex flex-wrap items-end gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="target_year_id">Target year</Label>
                        <select
                            id="target_year_id"
                            defaultValue={target_year_id ?? ''}
                            className={`${SELECT_CLASS} w-72`}
                            onChange={(event) => changeTargetYear(event.target.value)}
                        >
                            {target_years.map((year) => (
                                <option key={year.id} value={year.id}>
                                    {year.name} ({year.starts_at} — {year.ends_at})
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
                                    <Label htmlFor="effective_on">Effective on</Label>
                                    <Input
                                        id="effective_on"
                                        type="date"
                                        name="effective_on"
                                        defaultValue={targetYear?.starts_at ?? ''}
                                        className="w-56"
                                    />
                                    <InputError message={errors.effective_on} />
                                </div>

                                <div className="overflow-x-auto rounded-lg border">
                                    <table className="w-full text-sm">
                                        <thead className="bg-muted/50 text-muted-foreground">
                                            <tr>
                                                <th className="px-4 py-3 text-left font-medium">
                                                    {source.name} class
                                                </th>
                                                <th className="px-4 py-3 text-left font-medium">
                                                    Students
                                                </th>
                                                <th className="px-4 py-3 text-left font-medium">
                                                    Destination in target year
                                                </th>
                                                <th className="px-4 py-3 text-left font-medium">
                                                    Details
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {source_classes.map((schoolClass) => {
                                                const mapping =
                                                    mappings[schoolClass.id] ??
                                                    ({
                                                        mode: 'existing',
                                                        target_class_id: '',
                                                        new_name: schoolClass.name,
                                                        new_teacher_id: '',
                                                    } satisfies Mapping);

                                                return (
                                                    <tr
                                                        key={schoolClass.id}
                                                        className="border-t"
                                                    >
                                                        <td className="px-4 py-3 font-medium">
                                                            {schoolClass.name}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            {schoolClass.students_count}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <select
                                                                name={`mappings[${schoolClass.id}][mode]`}
                                                                value={mapping.mode}
                                                                onChange={(event) =>
                                                                    setMapping(schoolClass.id, {
                                                                        mode: event.target
                                                                            .value as MappingMode,
                                                                    })
                                                                }
                                                                className={`${SELECT_CLASS} w-56`}
                                                            >
                                                                <option value="existing">
                                                                    Map to existing class
                                                                </option>
                                                                <option value="new">
                                                                    Create new class
                                                                </option>
                                                                <option value="none">
                                                                    No successor (alumni)
                                                                </option>
                                                            </select>
                                                        </td>
                                                        <td className="space-y-1 px-4 py-3">
                                                            {mapping.mode === 'existing' && (
                                                                <>
                                                                    <select
                                                                        name={`mappings[${schoolClass.id}][target_class_id]`}
                                                                        value={
                                                                            mapping.target_class_id
                                                                        }
                                                                        onChange={(event) =>
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
                                                                            Choose target class…
                                                                        </option>
                                                                        {target_classes.map(
                                                                            (option) => (
                                                                                <option
                                                                                    key={option.id}
                                                                                    value={option.id}
                                                                                >
                                                                                    {option.name}
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
                                                            {mapping.mode === 'new' && (
                                                                <>
                                                                    <Input
                                                                        name={`mappings[${schoolClass.id}][new_name]`}
                                                                        defaultValue={
                                                                            mapping.new_name
                                                                        }
                                                                        placeholder="Class name in target year"
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
                                                                            Homeroom teacher (optional)
                                                                        </option>
                                                                        {teachers.map((teacher) => (
                                                                            <option
                                                                                key={teacher.id}
                                                                                value={teacher.id}
                                                                            >
                                                                                {teacher.name}
                                                                            </option>
                                                                        ))}
                                                                    </select>
                                                                </>
                                                            )}
                                                            {mapping.mode === 'none' && (
                                                                <span className="text-muted-foreground text-xs">
                                                                    {schoolClass.students_count}{' '}
                                                                    student
                                                                    {schoolClass.students_count ===
                                                                    1
                                                                        ? ''
                                                                        : 's'}{' '}
                                                                    become alumni
                                                                </span>
                                                            )}
                                                        </td>
                                                    </tr>
                                                );
                                            })}

                                            {source_classes.length === 0 && (
                                                <tr className="border-t">
                                                    <td
                                                        colSpan={4}
                                                        className="text-muted-foreground px-4 py-8 text-center"
                                                    >
                                                        No classes in the active year.
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>

                                <div className="flex items-center justify-between gap-4">
                                    <p className="text-muted-foreground text-sm">
                                        {mappedCount} of {source_classes.length} classes have a
                                        successor — unmapped students stay in history as alumni.
                                    </p>
                                    <Button
                                        type="button"
                                        disabled={processing || source_classes.length === 0}
                                        onClick={() => setConfirming(true)}
                                    >
                                        Apply roll-over
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
                                            <DialogTitle>Apply roll-over?</DialogTitle>
                                            <DialogDescription>
                                                All {source.name} enrollments end the day before
                                                the effective date, mapped students start in their
                                                target classes, and{' '}
                                                {targetYear?.name ?? 'the target year'} becomes
                                                the active year. Historical reports keep last
                                                year's attribution.
                                            </DialogDescription>
                                        </DialogHeader>
                                        <DialogFooter className="gap-2">
                                            <DialogClose asChild>
                                                <Button variant="secondary" type="button">
                                                    Cancel
                                                </Button>
                                            </DialogClose>
                                            <Button type="submit" form="roll-over-form" disabled={processing}>
                                                Apply roll-over
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

AcademicYearsRollOver.layout = {
    breadcrumbs: [
        { title: 'Academic Years', href: AcademicYearController.index().url },
        { title: 'Roll over' },
    ],
};
