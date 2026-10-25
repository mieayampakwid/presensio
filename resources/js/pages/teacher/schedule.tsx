import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { useTranslation } from '@/hooks/use-translation';

type ScheduleSlot = {
    id: number;
    class_name: string;
    title: string;
    room: string | null;
    start_time: string;
    end_time: string;
    status: 'current' | 'next' | null;
};

type ScheduleDay = {
    weekday: number;
    slots: ScheduleSlot[];
};

type Props = {
    days: ScheduleDay[];
};

export default function TeacherSchedule({ days }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('timetable.schedule.title')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('timetable.schedule.title')}
                    description={t('timetable.schedule.description')}
                />

                <div className="grid gap-4 xl:grid-cols-2">
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
    day: ScheduleDay;
    label: string;
};

function DayBlock({ day, label }: DayBlockProps) {
    const { t } = useTranslation();

    return (
        <div className="overflow-hidden rounded-lg border">
            <div className="bg-muted/50 px-4 py-2 text-sm font-medium">
                {label}
            </div>
            {day.slots.length === 0 ? (
                <p className="text-muted-foreground px-4 py-3 text-xs">
                    {t('timetable.schedule.no_slots')}
                </p>
            ) : (
                <table className="w-full text-sm">
                    <tbody>
                        {day.slots.map((slot) => (
                            <tr key={slot.id} className="border-t">
                                <td className="text-muted-foreground w-28 px-3 py-2 text-xs tabular-nums">
                                    {slot.start_time}–{slot.end_time}
                                </td>
                                <td className="px-2 py-2">
                                    <div className="flex flex-col items-start gap-0.5 px-2 py-1">
                                        <span className="flex items-center gap-2 text-sm font-medium">
                                            {slot.title}
                                            {slot.room ? ` · ${slot.room}` : ''}
                                            {slot.status === 'current' && (
                                                <span className="bg-primary text-primary-foreground rounded-full px-2 py-0.5 text-[10px] font-medium">
                                                    {t(
                                                        'timetable.schedule.current',
                                                    )}
                                                </span>
                                            )}
                                            {slot.status === 'next' && (
                                                <span className="text-primary border-primary rounded-full border px-2 py-0.5 text-[10px] font-medium">
                                                    {t(
                                                        'timetable.schedule.next',
                                                    )}
                                                </span>
                                            )}
                                        </span>
                                        <span className="text-muted-foreground text-xs">
                                            {slot.class_name}
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </div>
    );
}

TeacherSchedule.layout = {
    breadcrumbs: [
        {
            title: 'My Schedule',
        },
    ],
};
