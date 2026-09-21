import { Head, Link, usePage } from '@inertiajs/react';
import { UserRoundX } from 'lucide-react';
import AttendanceController from '@/actions/App/Http/Controllers/Attendance/AttendanceController';
import ExcuseReviewController from '@/actions/App/Http/Controllers/Excuses/ExcuseReviewController';
import ClassReportController from '@/actions/App/Http/Controllers/Reports/ClassReportController';
import PresenceBoardController from '@/actions/App/Http/Controllers/Reports/PresenceBoardController';
import StudentReportController from '@/actions/App/Http/Controllers/Reports/StudentReportController';
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
import { METHOD_LABELS, STATUS_BADGES } from '@/lib/attendance';
import { EXCUSE_STATUS_BADGES, EXCUSE_TYPE_LABELS } from '@/lib/excuses';
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
};

type TodayRow = {
    status: string;
    checked_in_at: string | null;
    checked_out_at: string | null;
    scan_method: string | null;
};

type Props = {
    is_school_day: boolean;
    board: Board | null;
    pending_excuses: number | null;
    children: ChildCard[] | null;
    student: { today: TodayRow | null } | null;
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

function StaffSection({
    board,
    pendingExcuses,
    role,
}: {
    board: Board;
    pendingExcuses: number;
    role: UserRole;
}) {
    return (
        <>
            {board.totals !== null && (
                <Link href={PresenceBoardController.index().url}>
                    <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-muted-foreground text-sm font-medium">
                                    In building — evacuation headcount
                                </CardTitle>
                                <CardTitle className="text-4xl">
                                    {board.totals.in_building}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-muted-foreground text-sm font-medium">
                                    Checked out
                                </CardTitle>
                                <CardTitle className="text-4xl">
                                    {board.totals.checked_out}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-muted-foreground text-sm font-medium">
                                    Not checked in
                                </CardTitle>
                                <CardTitle className="text-4xl">
                                    {board.totals.not_checked_in}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-muted-foreground text-sm font-medium">
                                    Enrolled
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
                                Class
                            </th>
                            <th className="px-4 py-3 text-left font-medium">
                                Enrolled
                            </th>
                            <th className="px-4 py-3 text-left font-medium">
                                In building
                            </th>
                            <th className="px-4 py-3 text-left font-medium">
                                Checked out
                            </th>
                            <th className="px-4 py-3 text-left font-medium">
                                Not checked in
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {board.classes.map((schoolClass) => (
                            <tr key={schoolClass.id} className="border-t">
                                <td className="px-4 py-3 font-medium">
                                    <Link
                                        href={
                                            PresenceBoardController.index()
                                                .url
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
                                    No classes assigned to you.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <div className="grid gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-muted-foreground text-sm font-medium">
                            Pending excuses
                        </CardTitle>
                        <CardTitle className="text-4xl">
                            {pendingExcuses}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={ExcuseReviewController.index().url}>
                                Review queue
                            </Link>
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-muted-foreground text-sm font-medium">
                            Quick links
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={AttendanceController.index().url}>
                                Attendance
                            </Link>
                        </Button>
                        {role === 'teacher' && (
                            <Button variant="outline" size="sm" asChild>
                                <Link
                                    href={PresenceBoardController.index().url}
                                >
                                    Presence Board
                                </Link>
                            </Button>
                        )}
                        <Button variant="outline" size="sm" asChild>
                            <Link href={StudentReportController.index().url}>
                                Student Report
                            </Link>
                        </Button>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={ClassReportController.index().url}>
                                Class Report
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function ParentSection({ cards }: { cards: ChildCard[] | null }) {
    if (cards === null) {
        return (
            <PortalEmptyState
                title="No guardian profile linked"
                body="Your account is not linked to a guardian profile yet. Ask the school office to connect it."
            />
        );
    }

    if (cards.length === 0) {
        return (
            <PortalEmptyState
                title="No children linked"
                body="Your guardian profile is not linked to any children yet. Ask the school office to connect them."
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
                                    No record yet
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
                        <div className="flex gap-2">
                            <Button variant="outline" size="sm" asChild>
                                <Link href={my().url}>My Excuses</Link>
                            </Button>
                            <Button variant="outline" size="sm" asChild>
                                <Link
                                    href={StudentReportController.index({
                                        query: {
                                            student_id: child.id,
                                        },
                                    }).url}
                                >
                                    Child report
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
    if (profile === null) {
        return (
            <PortalEmptyState
                title="No student profile linked"
                body="Your account is not linked to a student profile yet. Ask the school office to connect it."
            />
        );
    }

    return (
        <Card className="max-w-md">
            <CardHeader className="items-center">
                <CardTitle>Today</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col items-center gap-3">
                {profile.today ? (
                    <>
                        <StatusBadge status={profile.today.status} />
                        <div className="text-muted-foreground flex gap-6 text-sm">
                            <span>
                                In:{' '}
                                <span className="text-foreground font-medium">
                                    {profile.today.checked_in_at ?? '—'}
                                </span>
                            </span>
                            <span>
                                Out:{' '}
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
                            <Link href={myAttendance().url}>My Attendance</Link>
                        </Button>
                    </>
                ) : (
                    <p className="text-muted-foreground text-sm">
                        No record yet — check in at the scanner.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

export default function Dashboard({
    is_school_day,
    board,
    pending_excuses,
    children,
    student,
}: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const role = auth.user.role;

    return (
        <>
            <Head title="Dashboard" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Dashboard"
                    description="Today at a glance — every widget deep-links to its own page."
                />

                {!is_school_day && (
                    <p className="text-muted-foreground rounded-lg border px-4 py-3 text-sm">
                        Not a school day — attendance is not being taken.
                    </p>
                )}

                {(role === 'admin' || role === 'teacher') &&
                    board !== null && (
                        <StaffSection
                            board={board}
                            pendingExcuses={pending_excuses ?? 0}
                            role={role}
                        />
                    )}

                {role === 'parent' && <ParentSection cards={children} />}

                {role === 'student' && <StudentSection profile={student} />}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
