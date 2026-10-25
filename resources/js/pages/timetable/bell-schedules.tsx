import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import BellScheduleController from '@/actions/App/Http/Controllers/Timetable/BellScheduleController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import InputError from '@/components/input-error';
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

type PeriodRow = {
    id: number | null;
    kind: 'lesson' | 'break';
    number: string;
    name: string;
    start_time: string;
    end_time: string;
};

type ScheduleRow = {
    id: number;
    name: string;
    weekdays: number[];
    periods: PeriodRow[];
};

type Props = {
    schedules: ScheduleRow[];
    operational_weekdays: number[];
};

const emptyPeriod = (): PeriodRow => ({
    id: null,
    kind: 'lesson',
    number: '',
    name: '',
    start_time: '',
    end_time: '',
});

const allWeekdays = [1, 2, 3, 4, 5, 6, 7];

export default function BellSchedules({
    schedules,
    operational_weekdays,
}: Props) {
    const { t } = useTranslation();
    const [editing, setEditing] = useState<ScheduleRow | null>(null);
    const [isFormOpen, setIsFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<ScheduleRow | null>(null);

    const dayLabel = (day: number): string => t(`timetable.weekdays.${day}`);

    const destroy = (schedule: ScheduleRow): void => {
        router.delete(
            BellScheduleController.destroy.url({ bell_schedule: schedule.id }),
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title={t('timetable.index.title')} />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title={t('timetable.index.title')}
                        description={t('timetable.index.description')}
                    />

                    <Button
                        onClick={() => {
                            setEditing(null);
                            setIsFormOpen(true);
                        }}
                    >
                        {t('timetable.index.create')}
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    {t('timetable.index.columns.name')}
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    {t('timetable.index.columns.days')}
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    {t('timetable.index.columns.periods')}
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    {t('timetable.index.actions')}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {schedules.map((schedule) => {
                                const lessons = schedule.periods.filter(
                                    (p) => p.kind === 'lesson',
                                ).length;
                                const breaks = schedule.periods.filter(
                                    (p) => p.kind === 'break',
                                ).length;

                                return (
                                    <tr key={schedule.id} className="border-t">
                                        <td className="px-4 py-3 font-medium">
                                            {schedule.name}
                                        </td>
                                        <td className="text-muted-foreground px-4 py-3">
                                            {schedule.weekdays
                                                .map(dayLabel)
                                                .join(', ')}
                                        </td>
                                        <td className="text-muted-foreground px-4 py-3">
                                            {t('timetable.index.days_summary', {
                                                lessons,
                                                breaks,
                                            })}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() => {
                                                        setEditing(schedule);
                                                        setIsFormOpen(true);
                                                    }}
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                    <span className="sr-only">
                                                        {t(
                                                            'timetable.form.edit_title',
                                                        )}
                                                    </span>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() =>
                                                        setDeleting(schedule)
                                                    }
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                    <span className="sr-only">
                                                        {t(
                                                            'timetable.index.remove',
                                                        )}
                                                    </span>
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}

                            {schedules.length === 0 && (
                                <tr className="border-t">
                                    <td
                                        colSpan={4}
                                        className="text-muted-foreground px-4 py-8 text-center"
                                    >
                                        {t('timetable.index.empty')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {isFormOpen && (
                <ScheduleFormSheet
                    schedule={editing}
                    operationalWeekdays={operational_weekdays}
                    onClose={() => setIsFormOpen(false)}
                />
            )}

            <Dialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
            >
                <DialogContent>
                    <DialogTitle>{t('timetable.index.remove')}</DialogTitle>
                    <DialogDescription>
                        {t('timetable.index.remove_description', {
                            name: deleting?.name ?? '',
                        })}
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <Button
                            variant="outline"
                            onClick={() => setDeleting(null)}
                        >
                            {t('timetable.index.cancel')}
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
                            {t('timetable.index.remove_confirm')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

type ScheduleFormSheetProps = {
    schedule: ScheduleRow | null;
    operationalWeekdays: number[];
    onClose: () => void;
};

function ScheduleFormSheet({
    schedule,
    operationalWeekdays,
    onClose,
}: ScheduleFormSheetProps) {
    const { t } = useTranslation();
    const isEditing = schedule !== null;

    const dayLabel = (day: number): string => t(`timetable.weekdays.${day}`);

    const form = useForm({
        name: schedule?.name ?? '',
        weekdays: schedule?.weekdays ?? [],
        periods: schedule?.periods.length
            ? schedule.periods
            : [emptyPeriod(), emptyPeriod()],
    });

    const toggleWeekday = (day: number): void => {
        const next = form.data.weekdays.includes(day)
            ? form.data.weekdays.filter((d) => d !== day)
            : [...form.data.weekdays, day].sort((a, b) => a - b);

        form.setData('weekdays', next);
    };

    const updatePeriod = (
        index: number,
        field: keyof PeriodRow,
        value: string,
    ): void => {
        form.setData(
            'periods',
            form.data.periods.map((period, i) =>
                i === index ? { ...period, [field]: value } : period,
            ),
        );
    };

    const setKind = (index: number, kind: 'lesson' | 'break'): void => {
        form.setData(
            'periods',
            form.data.periods.map((period, i) =>
                i === index
                    ? { ...period, kind, number: '', name: '' }
                    : period,
            ),
        );
    };

    const addPeriod = (): void => {
        form.setData('periods', [...form.data.periods, emptyPeriod()]);
    };

    const removePeriod = (index: number): void => {
        form.setData(
            'periods',
            form.data.periods.filter((_, i) => i !== index),
        );
    };

    const submit = (event: React.FormEvent): void => {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            weekdays: data.weekdays.map(Number),
            periods: data.periods.map((period) => ({
                id: period.id,
                kind: period.kind,
                number:
                    period.kind === 'lesson' && period.number !== ''
                        ? Number(period.number)
                        : null,
                name: period.kind === 'break' ? period.name.trim() : '',
                start_time: period.start_time,
                end_time: period.end_time,
            })),
        }));

        if (isEditing) {
            form.put(
                BellScheduleController.update.url({
                    bell_schedule: schedule.id,
                }),
                { onSuccess: onClose },
            );
        } else {
            form.post(BellScheduleController.store.url(), {
                onSuccess: onClose,
            });
        }
    };

    const rowError = (index: number, field: string): string | undefined =>
        (form.errors as Record<string, string | undefined>)[
            `periods.${index}.${field}`
        ];

    return (
        <Sheet open onOpenChange={(open) => !open && onClose()}>
            <SheetContent side="right" className="sm:max-w-xl">
                <SheetHeader>
                    <SheetTitle>
                        {isEditing
                            ? t('timetable.form.edit_title')
                            : t('timetable.form.create_title')}
                    </SheetTitle>
                    <SheetDescription>
                        {isEditing
                            ? t('timetable.form.edit_description')
                            : t('timetable.form.create_description')}
                    </SheetDescription>
                </SheetHeader>

                <form
                    onSubmit={submit}
                    className="flex flex-1 flex-col justify-between space-y-4 overflow-y-auto px-4"
                >
                    <div className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="schedule-name">
                                {t('timetable.form.name')}
                            </Label>
                            <Input
                                id="schedule-name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                required
                                autoFocus
                                autoComplete="off"
                                placeholder={t(
                                    'timetable.form.name_placeholder',
                                )}
                                maxLength={50}
                            />
                            <InputError message={form.errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label>{t('timetable.form.weekdays')}</Label>
                            <div className="flex flex-wrap gap-4">
                                {allWeekdays.map((day) => (
                                    <label
                                        key={day}
                                        className={`flex cursor-pointer items-center gap-1.5 text-sm ${operationalWeekdays.includes(day) ? '' : 'text-muted-foreground/50'}`}
                                    >
                                        <input
                                            type="checkbox"
                                            checked={form.data.weekdays.includes(
                                                day,
                                            )}
                                            onChange={() => toggleWeekday(day)}
                                            className="text-primary focus:ring-primary h-4 w-4 rounded border-gray-300"
                                        />
                                        {dayLabel(day)}
                                    </label>
                                ))}
                            </div>
                            <p className="text-muted-foreground text-xs">
                                {t('timetable.form.weekdays_hint')}
                            </p>
                            <InputError message={form.errors.weekdays} />
                        </div>

                        <div className="grid gap-2">
                            <Label>{t('timetable.form.periods')}</Label>
                            <InputError message={form.errors.periods} />

                            <div className="space-y-2">
                                {form.data.periods.map((period, index) => (
                                    <div
                                        key={index}
                                        className="grid grid-cols-[9rem_1fr_6rem_6rem_2rem] items-start gap-2"
                                    >
                                        <select
                                            value={period.kind}
                                            onChange={(event) =>
                                                setKind(
                                                    index,
                                                    event.target.value as
                                                        | 'lesson'
                                                        | 'break',
                                                )
                                            }
                                            className="border-input dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full rounded-md border bg-transparent px-2 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px]"
                                        >
                                            <option value="lesson">
                                                {t(
                                                    'timetable.form.kind_lesson',
                                                )}
                                            </option>
                                            <option value="break">
                                                {t('timetable.form.kind_break')}
                                            </option>
                                        </select>

                                        <div>
                                            {period.kind === 'lesson' ? (
                                                <Input
                                                    aria-label={t(
                                                        'timetable.form.number',
                                                    )}
                                                    type="number"
                                                    min={1}
                                                    placeholder={t(
                                                        'timetable.form.number',
                                                    )}
                                                    value={period.number}
                                                    onChange={(event) =>
                                                        updatePeriod(
                                                            index,
                                                            'number',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            ) : (
                                                <Input
                                                    aria-label={t(
                                                        'timetable.form.name_label',
                                                    )}
                                                    placeholder={t(
                                                        'timetable.form.name_label',
                                                    )}
                                                    maxLength={50}
                                                    value={period.name}
                                                    onChange={(event) =>
                                                        updatePeriod(
                                                            index,
                                                            'name',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            )}
                                            <InputError
                                                message={
                                                    rowError(index, 'number') ??
                                                    rowError(index, 'name')
                                                }
                                            />
                                        </div>

                                        <div>
                                            <Input
                                                aria-label={t(
                                                    'timetable.form.start',
                                                )}
                                                type="time"
                                                value={period.start_time}
                                                onChange={(event) =>
                                                    updatePeriod(
                                                        index,
                                                        'start_time',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={rowError(
                                                    index,
                                                    'start_time',
                                                )}
                                            />
                                        </div>

                                        <div>
                                            <Input
                                                aria-label={t(
                                                    'timetable.form.end',
                                                )}
                                                type="time"
                                                value={period.end_time}
                                                onChange={(event) =>
                                                    updatePeriod(
                                                        index,
                                                        'end_time',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={rowError(
                                                    index,
                                                    'end_time',
                                                )}
                                            />
                                        </div>

                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="text-destructive hover:text-destructive"
                                            onClick={() => removePeriod(index)}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                            <span className="sr-only">
                                                {t('timetable.index.remove')}
                                            </span>
                                        </Button>
                                    </div>
                                ))}
                            </div>

                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={addPeriod}
                                className="w-fit"
                            >
                                <Plus className="h-4 w-4" />
                                {t('timetable.form.add_period')}
                            </Button>
                        </div>
                    </div>

                    <SheetFooter className="flex flex-row justify-end gap-2 border-t px-0 pt-4">
                        <Button
                            variant="outline"
                            type="button"
                            onClick={onClose}
                        >
                            {t('timetable.index.cancel')}
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner className="mr-2" />}
                            {isEditing
                                ? t('timetable.form.submit_update')
                                : t('timetable.form.submit_create')}
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}

BellSchedules.layout = {
    breadcrumbs: [
        {
            title: 'Bell Schedules',
            href: BellScheduleController.index().url,
        },
    ],
};
