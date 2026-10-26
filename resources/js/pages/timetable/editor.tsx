import { Head, router, useHttp } from '@inertiajs/react';
import { Copy, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import SchoolClassController from '@/actions/App/Http/Controllers/Classes/SchoolClassController';
import ClassTimetableController from '@/actions/App/Http/Controllers/Timetable/ClassTimetableController';
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
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { toast } from 'sonner';

type ClassSubjectOption = {
    id: number;
    subject_name: string;
    teacher_name: string | null;
};

type SemesterOption = {
    id: number;
    name: string;
    number: number;
};

type PeriodCell = {
    id: number;
    kind: 'lesson' | 'break';
    number: number | null;
    name: string | null;
    start_time: string;
    end_time: string;
};

type SlotRow = {
    id: number;
    day_of_week: number;
    lesson_period_id: number;
    period_count: number;
    class_subject_id: number | null;
    activity_name: string | null;
    room: string | null;
    title: string;
    teacher_name: string | null;
    start_time: string;
    end_time: string;
};

type DayGrid = {
    weekday: number;
    periods: PeriodCell[];
    slots: SlotRow[];
};

type Props = {
    school_class: { id: number; name: string };
    class_subjects: ClassSubjectOption[];
    semesters: SemesterOption[];
    semester_id: number;
    read_only: boolean;
    days: DayGrid[];
};

type ConflictItem = {
    type: string;
    message: string;
};

type FormState = {
    type: 'subject' | 'activity';
    class_subject_id: string;
    activity_name: string;
    day_of_week: string;
    lesson_period_id: string;
    period_count: string;
    room: string;
    semester_id?: string;
    confirm_conflicts: boolean;
};

const emptyForm = (): FormState => ({
    type: 'subject',
    class_subject_id: '',
    activity_name: '',
    day_of_week: '',
    lesson_period_id: '',
    period_count: '1',
    room: '',
    confirm_conflicts: false,
});

const selectClass =
    'border-input dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm';

export default function TimetableEditor({
    school_class: schoolClass,
    class_subjects: classSubjects,
    semesters,
    semester_id: semesterId,
    read_only: readOnly,
    days,
}: Props) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<SlotRow | null>(null);
    const [conflicts, setConflicts] = useState<ConflictItem[] | null>(null);
    const [deleting, setDeleting] = useState(false);
    const [copying, setCopying] = useState(false);
    const http = useHttp<FormState, unknown>(emptyForm());
    const errors = http.errors as Record<string, string | undefined>;

    const lessonsOf = (weekday: number): PeriodCell[] =>
        (days.find((day) => day.weekday === weekday)?.periods ?? []).filter(
            (period) => period.kind === 'lesson',
        );

    const changeSemester = (nextId: number): void => {
        router.get(
            ClassTimetableController.edit.url(
                { school_class: schoolClass.id },
                { query: { semester: nextId } },
            ),
        );
    };

    const copyFromPrevious = (): void => {
        router.post(
            ClassTimetableController.copy.url({ school_class: schoolClass.id }),
            { semester_id: semesterId },
            {
                preserveScroll: true,
                onStart: () => setCopying(true),
                onFinish: () => setCopying(false),
                onError: (copyErrors) => {
                    const message =
                        copyErrors.semester_id ?? copyErrors.class_id;
                    if (message) {
                        toast.error(message);
                    }
                },
            },
        );
    };

    const openCreate = (day: DayGrid, period: PeriodCell): void => {
        http.clearErrors();
        http.setData({
            ...emptyForm(),
            semester_id: String(semesterId),
            day_of_week: String(day.weekday),
            lesson_period_id: String(period.id),
            class_subject_id: classSubjects[0]
                ? String(classSubjects[0].id)
                : '',
        });
        setEditing(null);
        setOpen(true);
    };

    const openEdit = (slot: SlotRow): void => {
        if (readOnly) {
            return;
        }

        http.clearErrors();
        http.setData({
            ...emptyForm(),
            semester_id: String(semesterId),
            type: slot.class_subject_id !== null ? 'subject' : 'activity',
            class_subject_id:
                slot.class_subject_id !== null
                    ? String(slot.class_subject_id)
                    : '',
            activity_name: slot.activity_name ?? '',
            day_of_week: String(slot.day_of_week),
            lesson_period_id: String(slot.lesson_period_id),
            period_count: String(slot.period_count),
            room: slot.room ?? '',
        });
        setEditing(slot);
        setOpen(true);
    };

    const closeForm = (): void => {
        setOpen(false);
        setEditing(null);
        setConflicts(null);
    };

    const changeDay = (weekday: string): void => {
        const firstLesson = lessonsOf(Number(weekday))[0];
        http.setData({
            ...http.data,
            day_of_week: weekday,
            lesson_period_id: firstLesson ? String(firstLesson.id) : '',
        });
    };

    const persist = (confirmConflicts: boolean): void => {
        http.setData({
            ...http.data,
            class_subject_id:
                http.data.type === 'subject' ? http.data.class_subject_id : '',
            activity_name:
                http.data.type === 'activity' ? http.data.activity_name : '',
            confirm_conflicts: confirmConflicts,
        });

        const target =
            editing !== null
                ? ClassTimetableController.update.url({
                      school_class: schoolClass.id,
                      slot: editing.id,
                  })
                : ClassTimetableController.store.url({
                      school_class: schoolClass.id,
                  });

        http.post(target, {
            onSuccess: () => closeForm(),
            onHttpException: (response) => {
                if (response.status === 409) {
                    const body =
                        typeof response.data === 'string'
                            ? JSON.parse(response.data)
                            : response.data;
                    setConflicts(
                        (body as { conflicts?: ConflictItem[] }).conflicts ??
                            [],
                    );
                }
            },
        }).catch(() => {
            // 409 conflicts surface through the confirm dialog; 422
            // validation errors fill http.errors.
        });
    };

    const destroy = (): void => {
        if (editing === null) {
            return;
        }
        router.delete(
            ClassTimetableController.destroy.url({
                school_class: schoolClass.id,
                slot: editing.id,
            }),
            { preserveScroll: true },
        );
        closeForm();
        setDeleting(false);
    };

    const generalError = errors.slot ?? errors.class_id ?? errors.semester_id;
    const periodOptions = lessonsOf(Number(http.data.day_of_week));

    const periodLabel = (period: PeriodCell): string => {
        const start =
            period.number !== null
                ? t('timetable.editor.lesson_n', { number: period.number })
                : (period.name ?? '');

        return `${start} (${period.start_time}–${period.end_time})`;
    };

    return (
        <>
            <Head title={t('timetable.editor.title')} />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        title={t('timetable.editor.title')}
                        description={schoolClass.name}
                    />

                    <div className="flex items-end gap-2">
                        <div className="grid gap-2">
                            <Label htmlFor="semester">
                                {t('timetable.editor.semester')}
                            </Label>
                            <select
                                id="semester"
                                value={semesterId}
                                disabled={copying}
                                onChange={(event) =>
                                    changeSemester(Number(event.target.value))
                                }
                                className={`${selectClass} w-56`}
                            >
                                {semesters.map((semester) => (
                                    <option
                                        key={semester.id}
                                        value={semester.id}
                                    >
                                        {semester.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <Button
                            variant="outline"
                            disabled={readOnly || copying}
                            onClick={copyFromPrevious}
                        >
                            {copying && <Spinner className="mr-2" />}
                            <Copy className="mr-2 h-4 w-4" />
                            {t('timetable.editor.copy')}
                        </Button>
                    </div>
                </div>

                {readOnly && (
                    <p className="text-muted-foreground text-sm">
                        {t('timetable.editor.read_only')}
                    </p>
                )}

                <div className="grid gap-4 xl:grid-cols-2">
                    {days.map((day) => (
                        <DayBlock
                            key={day.weekday}
                            day={day}
                            readOnly={readOnly}
                            label={t(`timetable.weekdays.${day.weekday}`)}
                            spanLabel={(count) =>
                                t('timetable.editor.span', { count })
                            }
                            onAdd={(period) => openCreate(day, period)}
                            onEdit={openEdit}
                        />
                    ))}
                </div>
            </div>

            <Dialog open={open} onOpenChange={(next) => !next && closeForm()}>
                <DialogContent>
                    <DialogTitle>
                        {editing !== null
                            ? t('timetable.editor.form.edit_title')
                            : t('timetable.editor.form.create_title')}
                    </DialogTitle>
                    <DialogDescription>{schoolClass.name}</DialogDescription>

                    {generalError && (
                        <p className="text-destructive text-sm">
                            {generalError}
                        </p>
                    )}

                    <div className="space-y-4">
                        <div className="grid gap-2">
                            <Label>{t('timetable.editor.form.type')}</Label>
                            <div className="flex gap-4">
                                {(['subject', 'activity'] as const).map(
                                    (option) => (
                                        <label
                                            key={option}
                                            className="flex cursor-pointer items-center gap-1.5 text-sm"
                                        >
                                            <input
                                                type="radio"
                                                name="slot-type"
                                                checked={
                                                    http.data.type === option
                                                }
                                                onChange={() =>
                                                    http.setData({
                                                        ...http.data,
                                                        type: option,
                                                    })
                                                }
                                                className="text-primary focus:ring-primary h-4 w-4"
                                            />
                                            {t(
                                                `timetable.editor.form.type_${option}`,
                                            )}
                                        </label>
                                    ),
                                )}
                            </div>
                        </div>

                        {http.data.type === 'subject' ? (
                            <div className="grid gap-2">
                                <Label htmlFor="slot-subject">
                                    {t('timetable.editor.form.subject')}
                                </Label>
                                <select
                                    id="slot-subject"
                                    value={http.data.class_subject_id}
                                    onChange={(event) =>
                                        http.setData({
                                            ...http.data,
                                            class_subject_id:
                                                event.target.value,
                                        })
                                    }
                                    className={selectClass}
                                >
                                    <option value="" disabled>
                                        —
                                    </option>
                                    {classSubjects.map((assignment) => (
                                        <option
                                            key={assignment.id}
                                            value={assignment.id}
                                        >
                                            {assignment.subject_name}
                                            {assignment.teacher_name
                                                ? ` — ${assignment.teacher_name}`
                                                : ''}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.class_subject_id} />
                            </div>
                        ) : (
                            <div className="grid gap-2">
                                <Label htmlFor="slot-activity">
                                    {t('timetable.editor.form.activity_name')}
                                </Label>
                                <Input
                                    id="slot-activity"
                                    value={http.data.activity_name}
                                    maxLength={100}
                                    autoComplete="off"
                                    placeholder={t(
                                        'timetable.editor.form.activity_name_placeholder',
                                    )}
                                    onChange={(event) =>
                                        http.setData({
                                            ...http.data,
                                            activity_name: event.target.value,
                                        })
                                    }
                                />
                                <InputError message={errors.class_subject_id} />
                            </div>
                        )}

                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="slot-day">
                                    {t('timetable.editor.form.day')}
                                </Label>
                                <select
                                    id="slot-day"
                                    value={http.data.day_of_week}
                                    onChange={(event) =>
                                        changeDay(event.target.value)
                                    }
                                    className={selectClass}
                                >
                                    <option value="" disabled>
                                        —
                                    </option>
                                    {days.map((day) => (
                                        <option
                                            key={day.weekday}
                                            value={day.weekday}
                                        >
                                            {t(
                                                `timetable.weekdays.${day.weekday}`,
                                            )}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="slot-period">
                                    {t('timetable.editor.form.start_period')}
                                </Label>
                                <select
                                    id="slot-period"
                                    value={http.data.lesson_period_id}
                                    onChange={(event) =>
                                        http.setData({
                                            ...http.data,
                                            lesson_period_id:
                                                event.target.value,
                                        })
                                    }
                                    className={selectClass}
                                >
                                    <option value="" disabled>
                                        —
                                    </option>
                                    {periodOptions.map((period) => (
                                        <option
                                            key={period.id}
                                            value={period.id}
                                        >
                                            {periodLabel(period)}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.lesson_period_id} />
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="slot-period-count">
                                    {t('timetable.editor.form.period_count')}
                                </Label>
                                <Input
                                    id="slot-period-count"
                                    type="number"
                                    min={1}
                                    value={http.data.period_count}
                                    onChange={(event) =>
                                        http.setData({
                                            ...http.data,
                                            period_count: event.target.value,
                                        })
                                    }
                                />
                                <InputError message={errors.period_count} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="slot-room">
                                    {t('timetable.editor.form.room')}
                                </Label>
                                <Input
                                    id="slot-room"
                                    value={http.data.room}
                                    maxLength={50}
                                    autoComplete="off"
                                    placeholder={t(
                                        'timetable.editor.form.room_placeholder',
                                    )}
                                    onChange={(event) =>
                                        http.setData({
                                            ...http.data,
                                            room: event.target.value,
                                        })
                                    }
                                />
                            </div>
                        </div>
                    </div>

                    <DialogFooter className="gap-2">
                        {editing !== null && !readOnly && (
                            <Button
                                variant="ghost"
                                className="text-destructive hover:text-destructive mr-auto"
                                onClick={() => setDeleting(true)}
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                {t('timetable.editor.form.delete')}
                            </Button>
                        )}
                        <Button variant="outline" onClick={closeForm}>
                            {t('timetable.editor.conflicts.cancel')}
                        </Button>
                        <Button
                            disabled={http.processing}
                            onClick={() => persist(false)}
                        >
                            {http.processing && <Spinner className="mr-2" />}
                            {editing !== null
                                ? t('timetable.editor.form.submit_update')
                                : t('timetable.editor.form.submit_create')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={conflicts !== null}
                onOpenChange={(next) => !next && setConflicts(null)}
            >
                <DialogContent>
                    <DialogTitle>
                        {t('timetable.editor.conflicts.title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('timetable.editor.conflicts.description')}
                    </DialogDescription>
                    <ul className="text-sm">
                        {(conflicts ?? []).map((conflict, index) => (
                            <li key={index}>{conflict.message}</li>
                        ))}
                    </ul>
                    <DialogFooter className="gap-2">
                        <Button
                            variant="outline"
                            onClick={() => setConflicts(null)}
                        >
                            {t('timetable.editor.conflicts.cancel')}
                        </Button>
                        <Button onClick={() => persist(true)}>
                            {t('timetable.editor.conflicts.confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={deleting}
                onOpenChange={(next) => !next && setDeleting(false)}
            >
                <DialogContent>
                    <DialogTitle>
                        {t('timetable.editor.form.delete_confirm_title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('timetable.editor.form.delete_confirm_description')}
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <Button
                            variant="outline"
                            onClick={() => setDeleting(false)}
                        >
                            {t('timetable.editor.conflicts.cancel')}
                        </Button>
                        <Button variant="destructive" onClick={destroy}>
                            {t('timetable.editor.form.delete')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

type DayBlockProps = {
    day: DayGrid;
    readOnly: boolean;
    label: string;
    spanLabel: (count: number) => string;
    onAdd: (period: PeriodCell) => void;
    onEdit: (slot: SlotRow) => void;
};

function DayBlock({
    day,
    readOnly,
    label,
    spanLabel,
    onAdd,
    onEdit,
}: DayBlockProps) {
    const covered = new Map<number, SlotRow>();
    for (const slot of day.slots) {
        const index = day.periods.findIndex(
            (period) => period.id === slot.lesson_period_id,
        );
        for (
            let i = 0;
            i < slot.period_count && index + i < day.periods.length;
            i++
        ) {
            covered.set(day.periods[index + i].id, slot);
        }
    }

    return (
        <div className="overflow-hidden rounded-lg border">
            <div className="bg-muted/50 px-4 py-2 text-sm font-medium">
                {label}
            </div>
            <table className="w-full text-sm">
                <tbody>
                    {day.periods.map((period) => {
                        const times = `${period.start_time}–${period.end_time}`;

                        if (period.kind === 'break') {
                            return (
                                <tr key={period.id} className="bg-muted/50">
                                    <td className="text-muted-foreground w-28 px-3 py-2 text-xs tabular-nums">
                                        {times}
                                    </td>
                                    <td className="text-muted-foreground px-3 py-2 text-xs font-medium">
                                        {period.name}
                                    </td>
                                </tr>
                            );
                        }

                        const slot = covered.get(period.id);
                        const isStart =
                            slot !== undefined &&
                            slot.lesson_period_id === period.id;

                        return (
                            <tr key={period.id} className="border-t">
                                <td className="text-muted-foreground w-28 px-3 py-2 text-xs tabular-nums">
                                    {times}
                                </td>
                                <td className="px-2 py-2">
                                    {slot === undefined ? (
                                        !readOnly && (
                                            <button
                                                type="button"
                                                onClick={() => onAdd(period)}
                                                className="text-muted-foreground/60 hover:text-foreground hover:bg-muted/50 flex w-full items-center justify-center gap-1 rounded-md border border-dashed px-2 py-1.5 text-xs"
                                            >
                                                <Plus className="h-3.5 w-3.5" />
                                            </button>
                                        )
                                    ) : (
                                        <button
                                            type="button"
                                            disabled={readOnly}
                                            onClick={() => onEdit(slot)}
                                            className={`hover:bg-muted/50 flex w-full flex-col items-start gap-0.5 rounded-md px-2 py-1 text-left disabled:cursor-default disabled:hover:bg-transparent ${isStart ? '' : 'text-muted-foreground'}`}
                                        >
                                            <span className="text-sm font-medium">
                                                {slot.title}
                                                {slot.room
                                                    ? ` · ${slot.room}`
                                                    : ''}
                                            </span>
                                            <span className="text-muted-foreground text-xs">
                                                {isStart
                                                    ? (slot.teacher_name ??
                                                      times)
                                                    : spanLabel(
                                                          slot.period_count,
                                                      )}
                                            </span>
                                        </button>
                                    )}
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

TimetableEditor.layout = {
    breadcrumbs: [
        {
            title: 'Classes',
            href: SchoolClassController.index().url,
        },
        {
            title: 'Timetable',
        },
    ],
};
