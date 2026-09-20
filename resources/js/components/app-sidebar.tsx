import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    CalendarCheck,
    CalendarOff,
    ClipboardList,
    FileSpreadsheet,
    FileText,
    FolderGit2,
    GraduationCap,
    IdCard,
    LayoutGrid,
    Monitor,
    QrCode,
    School,
    Table2,
    UserRound,
    Users,
} from 'lucide-react';
import AttendanceController from '@/actions/App/Http/Controllers/Attendance/AttendanceController';
import ExcuseReviewController from '@/actions/App/Http/Controllers/Excuses/ExcuseReviewController';
import NonSchoolDayController from '@/actions/App/Http/Controllers/Calendar/NonSchoolDayController';
import SchoolClassController from '@/actions/App/Http/Controllers/Classes/SchoolClassController';
import ClassReportController from '@/actions/App/Http/Controllers/Reports/ClassReportController';
import GuardianController from '@/actions/App/Http/Controllers/Guardians/GuardianController';
import PresenceBoardController from '@/actions/App/Http/Controllers/Reports/PresenceBoardController';
import RfidCardController from '@/actions/App/Http/Controllers/RfidCards/RfidCardController';
import StudentController from '@/actions/App/Http/Controllers/Students/StudentController';
import StudentReportController from '@/actions/App/Http/Controllers/Reports/StudentReportController';
import TeacherController from '@/actions/App/Http/Controllers/Teachers/TeacherController';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { myAttendance, myQr } from '@/routes/attendance';
import { my } from '@/routes/excuses';
import type { Auth, NavItem } from '@/types';

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth } = usePage<{ auth: Auth }>().props;

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        ...(auth.user.role === 'admin'
            ? [
                  { title: 'Users', href: '/users', icon: Users },
                  {
                      title: 'Teachers',
                      href: TeacherController.index().url,
                      icon: UserRound,
                  },
                  {
                      title: 'Guardians',
                      href: GuardianController.index().url,
                      icon: Users,
                  },
                  {
                      title: 'Students',
                      href: StudentController.index().url,
                      icon: GraduationCap,
                  },
                  {
                      title: 'Classes',
                      href: SchoolClassController.index().url,
                      icon: School,
                  },
                  {
                      title: 'RFID Cards',
                      href: RfidCardController.index().url,
                      icon: IdCard,
                  },
                  {
                      title: 'Non-School Days',
                      href: NonSchoolDayController.index().url,
                      icon: CalendarOff,
                  },
              ]
            : []),
        ...(auth.user.role === 'student'
            ? [
                  {
                      title: 'My QR',
                      href: myQr().url,
                      icon: QrCode,
                  },
                  {
                      title: 'My Attendance',
                      href: myAttendance().url,
                      icon: CalendarCheck,
                  },
                  {
                      title: 'My Report',
                      href: StudentReportController.index().url,
                      icon: ClipboardList,
                  },
              ]
            : []),
        ...(auth.user.role === 'teacher' || auth.user.role === 'admin'
            ? [
                  {
                      title: 'Attendance',
                      href: AttendanceController.index().url,
                      icon: CalendarCheck,
                  },
                  {
                      title: 'Excuses',
                      href: ExcuseReviewController.index().url,
                      icon: FileText,
                  },
              ]
            : []),
        ...(auth.user.role === 'parent'
            ? [
                  {
                      title: 'My Excuses',
                      href: my().url,
                      icon: FileText,
                  },
                  {
                      title: 'Child Report',
                      href: StudentReportController.index().url,
                      icon: ClipboardList,
                  },
              ]
            : []),
    ];

    // Teacher/admin only — the three report screens grouped under their
    // own sidebar section.
    const reportNavItems: NavItem[] =
        auth.user.role === 'teacher' || auth.user.role === 'admin'
            ? [
                  {
                      title: 'Student Report',
                      href: StudentReportController.index().url,
                      icon: FileSpreadsheet,
                  },
                  {
                      title: 'Class Report',
                      href: ClassReportController.index().url,
                      icon: Table2,
                  },
                  {
                      title: 'Presence Board',
                      href: PresenceBoardController.index().url,
                      icon: Monitor,
                  },
              ]
            : [];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
                <NavMain label="Reports" items={reportNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
