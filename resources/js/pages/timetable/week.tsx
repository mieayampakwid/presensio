import { Head, router } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import SchoolClassController from '@/actions/App/Http/Controllers/Classes/SchoolClassController';
import ClassTimetableViewController from '@/actions/App/Http/Controllers/Timetable/ClassTimetableViewController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

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
    semesters: SemesterOption[];
    semester_id: number;
    days: DayGrid[];
};

const selectClass =
    'border-input dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm';

export default function ClassTimetableWeek({
    school_class: schoolClass,
    semesters,
    semester_id: semesterId,
    days,
}: Props) {
    const { t } = useTranslation();

    const changeSemester = (nextId: number): void => {
        router.get(
            ClassTimetableViewController.show.url(
                { school_class: schoolClass.id },
                { query: { semester: nextId } },
            ),
        );
    };

    return (
        <>
            <Head title={t('timetable.week.title')} />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        title={t('timetable.week.title')}
                        description={schoolClass.name}
                    />

                    <div className="flex items-end gap-2 print:hidden">
                        <div className="grid gap-2">
                            <Label htmlFor="semester">
                                {t('timetable.week.semester')}
                            </Label>
                            <select
                                id="semester"
                                value={semesterId}
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
                            onClick={() => window.print()}
                        >
                            <Printer className="mr-2 h-4 w-4" />
                            {t('timetable.week.print')}
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 xl:grid-cols-2 print:block">
                    {days.map((day) => (
                        <DayBlock
                            key={day.weekday}
                            day={day}
                            label={t(`timetable.weekdays.${day.weekday}`)}
                        />
                    ))}
                </div>
            </div>
        </>
    );
}

type DayBlockProps = {
    day: DayGrid;
    label: string;
};

function DayBlock({ day, label }: DayBlockProps) {
    const { t } = useTranslation();
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
        <div className="overflow-hidden rounded-lg border print:break-inside-avoid">
            <div className="bg-muted/50 px-4 py-2 text-sm font-medium print:bg-white">
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
                                    {slot === undefined ? null : (
                                        <div
                                            className={`flex flex-col items-start gap-0.5 px-2 py-1 ${isStart ? '' : 'text-muted-foreground'}`}
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
                                                    : t(
                                                          'timetable.editor.span',
                                                          {
                                                              count: slot.period_count,
                                                          },
                                                      )}
                                            </span>
                                        </div>
                                    )}
                                </td>
                            </tr>
                        );
                    })}
                    {day.periods.length === 0 && (
                        <tr>
                            <td className="text-muted-foreground px-3 py-3 text-xs">
                                {t('timetable.week.no_slots')}
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

ClassTimetableWeek.layout = {
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
