import AnnouncementReaderController from '@/actions/App/Http/Controllers/Announcements/AnnouncementReaderController';
import { Head, Link, setLayoutProps, usePage } from '@inertiajs/react';
import { UserRoundX } from 'lucide-react';
import ChildGradeController from '@/actions/App/Http/Controllers/Parent/ChildGradeController';
import ChildReportCardController from '@/actions/App/Http/Controllers/Parent/ChildReportCardController';
import AttendanceController from '@/actions/App/Http/Controllers/Attendance/AttendanceController';
import ExcuseReviewController from '@/actions/App/Http/Controllers/Excuses/ExcuseReviewController';
import ClassReportController from '@/actions/App/Http/Controllers/Reports/ClassReportController';
import PresenceBoardController from '@/actions/App/Http/Controllers/Reports/PresenceBoardController';
import StudentReportController from '@/actions/App/Http/Controllers/Reports/StudentReportController';
import LeaveReviewController from '@/actions/App/Http/Controllers/Staff/LeaveReviewController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { METHOD_LABELS, STATUS_BADGES } from '@/lib/attendance';
import { EXCUSE_STATUS_BADGES, EXCUSE_TYPE_LABELS } from '@/lib/excuses';
import { LEAVE_TYPE_LABELS } from '@/lib/leave';
import { my } from '@/routes/excuses';
import { myAttendance } from '@/routes/attendance';
import { dashboard } from '@/routes';
import { Auth, UserRole } from '@/types';

type Totals = {
    enrolled: number;
    in_building: number;
    checked_out: number;
    not_checked_in: number;
};

type ClassCounts = {
    id: number;
    name: string;
    enrolled: number;
    in_building: number;
    checked_out: number;
    not_checked_in: number;
};

type Board = {
    totals: Totals | null;
    classes: ClassCounts[];
};

type ChildCard = {
    id: number;
    full_name: string;
    class_name: string | null;
    today_status: string | null;
    latest_excuse: {
        type: string;
        status: string;
        start_date: string;
        end_date: string;
    } | null;
    today_slots: ScheduleSlot[] | null;
};

type TodayRow = {
    status: string;
    checked_in_at: string | null;
    checked_out_at: string | null;
    scan_method: string | null;
};

type LeaveToday = {
    status: string;
    type: string;
    start_date: string;
    end_date: string;
};

type ScheduleSlot = {
    id: number;
    class_name: string;
    title: string;
    teacher_name: string | null;
    room: string | null;
    start_time: string;
    end_time: string;
    status: 'current' | 'next' | null;
};

type Props = {
    is_school_day: boolean;
    board: Board | null;
    pending_excuses: number | null;
    pending_leave_requests: number | null;
    announcementSummary: {
        items: { id: number; title: string; published_at: string | null }[];
        unacknowledged_count: number;
    };
    employee_leave_today: LeaveToday | null;
    children: ChildCard[] | null;
    student: { today: TodayRow | null } | null;
    today_schedule: ScheduleSlot[] | null;
};

function StatusBadge({ status }: { status: string }) {
    return (
        <Badge variant={STATUS_BADGES[status]?.variant ?? 'secondary'}>
            {STATUS_BADGES[status]?.label ?? status}
        </Badge>
    );
}

function PortalEmptyState({ title, body }: { title: string; body: string }) {
    return (
        <Card className="max-w-md">
            <CardContent className="text-muted-foreground flex flex-col items-center gap-3 py-12 text-center">
                <UserRoundX className="size-10" />
                <p className="text-foreground text-sm font-medium">{title}</p>
                <p className="text-sm">{body}</p>
            </CardContent>
        </Card>
    );
}

function TodayScheduleList({ slots }: { slots: ScheduleSlot[] }) {
    const { t } = useTranslation();

    if (slots.length === 0) {
        return (
            <p className="text-muted-foreground text-sm">
                {t('timetable.today.empty')}
            </p>
        );
    }

    return (
        <ul className="divide-y">
            {slots.map((slot) => (
                <li
                    key={slot.id}
                    className="flex flex-wrap items-center gap-2 py-2 text-sm"
                >
                    <span className="text-muted-foreground w-24 shrink-0 text-xs tabular-nums">
                        {slot.start_time}–{slot.end_time}
                    </span>
                    <span className="flex flex-wrap items-center gap-2 font-medium">
                        {slot.title}
                        {slot.room ? ` · ${slot.room}` : ''}
                        {slot.status === 'current' && (
                            <span className="bg-primary text-primary-foreground rounded-full px-2 py-0.5 text-[10px] font-medium">
                                {t('timetable.schedule.current')}
                            </span>
                        )}
                        {slot.status === 'next' && (
                            <span className="text-primary border-primary rounded-full border px-2 py-0.5 text-[10px] font-medium">
                                {t('timetable.schedule.next')}
                            </span>
                        )}
                    </span>
                    {slot.teacher_name && (
                        <span className="text-muted-foreground text-xs">
                            {slot.teacher_name}
                        </span>
                    )}
                </li>
            ))}
        </ul>
    );
}

function StaffSection({
    board,
    pendingExcuses,
    pendingLeaveRequests,
    role,
}: {
    board: Board;
    pendingExcuses: number;
    pendingLeaveRequests: number | null;
    role: UserRole;
}) {
    const { t } = useTranslation();

    return (
        <>
            {board.totals !== null && (
                <Link href={PresenceBoardController.index().url}>
                    <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-muted-foreground text-sm font-medium">
                                    {t('dashboard.in_building_headcount')}
                                </CardTitle>
                                <CardTitle className="text-4xl">
                                    {board.totals.in_building}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-muted-foreground text-sm font-medium">
                                    {t('dashboard.checked_out')}
                                </CardTitle>
                                <CardTitle className="text-4xl">
                                    {board.totals.checked_out}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-muted-foreground text-sm font-medium">
                                    {t('dashboard.not_checked_in')}
                                </CardTitle>
                                <CardTitle className="text-4xl">
                                    {board.totals.not_checked_in}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-muted-foreground text-sm font-medium">
                                    {t('dashboard.enrolled')}
                                </CardTitle>
                                <CardTitle className="text-4xl">
                                    {board.totals.enrolled}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                    </div>
                </Link>
            )}

            <div className="overflow-x-auto rounded-lg border">
                <table className="w-full text-sm">
                    <thead className="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th className="px-4 py-3 text-left font-medium">
                                {t('dashboard.class_column')}
                            </th>
                            <th className="px-4 py-3 text-left font-medium">
                                {t('dashboard.enrolled')}
                            </th>
                            <th className="px-4 py-3 text-left font-medium">
                                {t('dashboard.in_building')}
                            </th>
                            <th className="px-4 py-3 text-left font-medium">
                                {t('dashboard.checked_out')}
                            </th>
                            <th className="px-4 py-3 text-left font-medium">
                                {t('dashboard.not_checked_in')}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {board.classes.map((schoolClass) => (
                            <tr key={schoolClass.id} className="border-t">
                                <td className="px-4 py-3 font-medium">
                                    <Link
                                        href={
                                            PresenceBoardController.index().url
                                        }
                                        className="hover:underline"
                                    >
                                        {schoolClass.name}
                                    </Link>
                                </td>
                                <td className="text-muted-foreground px-4 py-3">
                                    {schoolClass.enrolled}
                                </td>
                                <td className="text-muted-foreground px-4 py-3">
                                    {schoolClass.in_building}
                                </td>
                                <td className="text-muted-foreground px-4 py-3">
                                    {schoolClass.checked_out}
                                </td>
                                <td className="text-muted-foreground px-4 py-3">
                                    {schoolClass.not_checked_in}
                                </td>
                            </tr>
                        ))}

                        {board.classes.length === 0 && (
                            <tr className="border-t">
                                <td
                                    colSpan={5}
                                    className="text-muted-foreground px-4 py-8 text-center"
                                >
                                    {t('dashboard.empty_classes')}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <div className="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-muted-foreground text-sm font-medium">
                            {t('dashboard.pending_excuses')}
                        </CardTitle>
                        <CardTitle className="text-4xl">
                            {pendingExcuses}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={ExcuseReviewController.index().url}>
                                {t('dashboard.excuse_review_queue')}
                            </Link>
                        </Button>
                    </CardContent>
                </Card>

                {pendingLeaveRequests !== null && (
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-muted-foreground text-sm font-medium">
                                {t('dashboard.pending_leave')}
                            </CardTitle>
                            <CardTitle className="text-4xl">
                                {pendingLeaveRequests}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={LeaveReviewController.index().url}>
                                    {t('dashboard.leave_review_queue')}
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-muted-foreground text-sm font-medium">
                            {t('dashboard.quick_links')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={AttendanceController.index().url}>
                                {t('dashboard.quick_link.attendance')}
                            </Link>
                        </Button>
                        {role === 'teacher' && (
                            <Button variant="outline" size="sm" asChild>
                                <Link
                                    href={PresenceBoardController.index().url}
                                >
                                    {t('dashboard.quick_link.presence_board')}
                                </Link>
                            </Button>
                        )}
                        <Button variant="outline" size="sm" asChild>
                            <Link href={StudentReportController.index().url}>
                                {t('dashboard.quick_link.student_report')}
                            </Link>
                        </Button>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={ClassReportController.index().url}>
                                {t('dashboard.quick_link.class_report')}
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function ParentSection({ cards }: { cards: ChildCard[] | null }) {
    const { t } = useTranslation();

    if (cards === null) {
        return (
            <PortalEmptyState
                title={t('dashboard.parent.no_guardian')}
                body={t('dashboard.parent.no_guardian_body')}
            />
        );
    }

    if (cards.length === 0) {
        return (
            <PortalEmptyState
                title={t('dashboard.parent.no_children')}
                body={t('dashboard.parent.no_children_body')}
            />
        );
    }

    return (
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {cards.map((child) => (
                <Card key={child.id}>
                    <CardHeader className="pb-2">
                        <div className="flex items-center justify-between gap-2">
                            <CardTitle>{child.full_name}</CardTitle>
                            {child.today_status ? (
                                <StatusBadge status={child.today_status} />
                            ) : (
                                <span className="text-muted-foreground text-xs">
                                    {t('dashboard.parent.no_record')}
                                </span>
                            )}
                        </div>
                        <CardDescription>
                            {child.class_name ?? '—'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {child.latest_excuse && (
                            <p className="text-muted-foreground flex flex-wrap items-center gap-2 text-sm">
                                <span>
                                    {EXCUSE_TYPE_LABELS[
                                        child.latest_excuse.type
                                    ] ?? child.latest_excuse.type}{' '}
                                    {child.latest_excuse.start_date} –{' '}
                                    {child.latest_excuse.end_date}
                                </span>
                                <Badge
                                    variant={
                                        EXCUSE_STATUS_BADGES[
                                            child.latest_excuse.status
                                        ]?.variant ?? 'secondary'
                                    }
                                >
                                    {EXCUSE_STATUS_BADGES[
                                        child.latest_excuse.status
                                    ]?.label ?? child.latest_excuse.status}
                                </Badge>
                            </p>
                        )}
                        {child.today_slots !== null && (
                            <div className="space-y-1">
                                <p className="text-muted-foreground text-xs font-medium">
                                    {t('timetable.today.title')}
                                </p>
                                <TodayScheduleList slots={child.today_slots} />
                            </div>
                        )}
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" size="sm" asChild>
                                <Link href={my().url}>
                                    {t('dashboard.parent.my_excuses')}
                                </Link>
                            </Button>
                            <Button variant="outline" size="sm" asChild>
                                <Link
                                    href={
                                        StudentReportController.index({
                                            query: {
                                                student_id: child.id,
                                            },
                                        }).url
                                    }
                                >
                                    {t('dashboard.parent.child_report')}
                                </Link>
                            </Button>
                            <Button variant="outline" size="sm" asChild>
                                <Link
                                    href={
                                        ChildGradeController.index({
                                            student: child.id,
                                        }).url
                                    }
                                >
                                    {t('dashboard.parent.grades')}
                                </Link>
                            </Button>
                            <Button variant="outline" size="sm" asChild>
                                <Link
                                    href={
                                        ChildReportCardController.index({
                                            student: child.id,
                                        }).url
                                    }
                                >
                                    {t('dashboard.parent.report_card')}
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}

function StudentSection({
    profile,
}: {
    profile: { today: TodayRow | null } | null;
}) {
    const { t } = useTranslation();

    if (profile === null) {
        return (
            <PortalEmptyState
                title={t('dashboard.student.no_profile')}
                body={t('dashboard.student.no_profile_body')}
            />
        );
    }

    return (
        <Card className="max-w-md">
            <CardHeader className="items-center">
                <CardTitle>{t('dashboard.student.today')}</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col items-center gap-3">
                {profile.today ? (
                    <>
                        <StatusBadge status={profile.today.status} />
                        <div className="text-muted-foreground flex gap-6 text-sm">
                            <span>
                                {t('dashboard.student.check_in')}{' '}
                                <span className="text-foreground font-medium">
                                    {profile.today.checked_in_at ?? '—'}
                                </span>
                            </span>
                            <span>
                                {t('dashboard.student.check_out')}{' '}
                                <span className="text-foreground font-medium">
                                    {profile.today.checked_out_at ?? '—'}
                                </span>
                            </span>
                        </div>
                        {profile.today.scan_method && (
                            <p className="text-muted-foreground text-xs">
                                {METHOD_LABELS[profile.today.scan_method] ??
                                    profile.today.scan_method}
                            </p>
                        )}
                        <Button variant="outline" size="sm" asChild>
                            <Link href={myAttendance().url}>
                                {t('dashboard.student.my_attendance')}
                            </Link>
                        </Button>
                    </>
                ) : (
                    <p className="text-muted-foreground text-sm">
                        {t('dashboard.student.no_record')}
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

function EmployeeLeaveBanner({ leave }: { leave: LeaveToday }) {
    const { t } = useTranslation();

    return (
        <Card>
            <CardContent className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p className="text-sm font-medium">
                        {t('dashboard.employee.on_leave_today')}
                    </p>
                    <p className="text-muted-foreground text-sm">
                        {leave.start_date} – {leave.end_date}
                    </p>
                </div>
                <Badge variant="secondary">
                    {LEAVE_TYPE_LABELS[leave.type] ?? leave.type}
                </Badge>
            </CardContent>
        </Card>
    );
}

export default function Dashboard({
    is_school_day,
    board,
    pending_excuses,
    pending_leave_requests,
    announcementSummary,
    employee_leave_today,
    children,
    student,
    today_schedule,
}: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const { t } = useTranslation();
    const role = auth.active_role ?? auth.user.role;
    const isStaff = ['admin', 'principal', 'teacher', 'counselor'].includes(
        role,
    );

    setLayoutProps({
        breadcrumbs: [
            {
                title: t('dashboard.title'),
                href: dashboard(),
            },
        ],
    });

    return (
        <>
            <Head title={t('dashboard.title')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('dashboard.title')}
                    description={t('dashboard.description')}
                />

                {!is_school_day && (
                    <p className="text-muted-foreground rounded-lg border px-4 py-3 text-sm">
                        {t('dashboard.not_school_day')}
                    </p>
                )}

                <section
                    className="space-y-3 rounded-lg border p-5"
                    aria-label={t('dashboard.announcements.title')}
                >
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 className="font-semibold">
                            {t('dashboard.announcements.title')}{' '}
                            {announcementSummary.unacknowledged_count > 0 && (
                                <Badge variant="secondary">
                                    {t(
                                        'dashboard.announcements.needs_acknowledgment',
                                        {
                                            count: announcementSummary.unacknowledged_count,
                                        },
                                    )}
                                </Badge>
                            )}
                        </h2>
                        <Link
                            className="text-sm underline underline-offset-4"
                            href={AnnouncementReaderController.index()}
                        >
                            {t('dashboard.announcements.view_all')}
                        </Link>
                    </div>
                    {announcementSummary.items.length ? (
                        <ul className="divide-y">
                            {announcementSummary.items.map((item) => (
                                <li
                                    key={item.id}
                                    className="flex flex-wrap justify-between gap-2 py-3"
                                >
                                    <Link
                                        className="text-sm font-medium hover:underline"
                                        href={AnnouncementReaderController.show(
                                            item.id,
                                        )}
                                    >
                                        {item.title}
                                    </Link>
                                    <span className="text-muted-foreground text-xs">
                                        {item.published_at}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-muted-foreground text-sm">
                            {t('dashboard.announcements.empty')}
                        </p>
                    )}
                </section>
                {employee_leave_today !== null && (
                    <EmployeeLeaveBanner leave={employee_leave_today} />
                )}

                {isStaff && board !== null && (
                    <StaffSection
                        board={board}
                        pendingExcuses={pending_excuses ?? 0}
                        pendingLeaveRequests={pending_leave_requests}
                        role={role}
                    />
                )}

                {role === 'parent' && <ParentSection cards={children} />}

                {role === 'student' && <StudentSection profile={student} />}

                {role === 'student' && today_schedule !== null && (
                    <Card className="max-w-md">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-muted-foreground text-sm font-medium">
                                {t('timetable.today.title')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <TodayScheduleList slots={today_schedule} />
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}
