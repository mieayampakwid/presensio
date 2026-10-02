import { Link, router, usePage } from '@inertiajs/react';
import {
    BookOpen,
    CalendarCheck,
    CalendarOff,
    CalendarRange,
    ChevronsUpDown,
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
import AcademicYearController from '@/actions/App/Http/Controllers/AcademicYears/AcademicYearController';
import AttendanceController from '@/actions/App/Http/Controllers/Attendance/AttendanceController';
import NonSchoolDayController from '@/actions/App/Http/Controllers/Calendar/NonSchoolDayController';
import SchoolClassController from '@/actions/App/Http/Controllers/Classes/SchoolClassController';
import ExcuseReviewController from '@/actions/App/Http/Controllers/Excuses/ExcuseReviewController';
import GuardianController from '@/actions/App/Http/Controllers/Guardians/GuardianController';
import ClassReportController from '@/actions/App/Http/Controllers/Reports/ClassReportController';
import PresenceBoardController from '@/actions/App/Http/Controllers/Reports/PresenceBoardController';
import StudentReportController from '@/actions/App/Http/Controllers/Reports/StudentReportController';
import RfidCardController from '@/actions/App/Http/Controllers/RfidCards/RfidCardController';
import StudentController from '@/actions/App/Http/Controllers/Students/StudentController';
import TeacherController from '@/actions/App/Http/Controllers/Teachers/TeacherController';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import type { Auth, NavItem, UserRole } from '@/types';

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
    const activeRole = auth.active_role ?? auth.user.role;
    const roles: UserRole[] = auth.user.roles ?? [auth.user.role];

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        ...(activeRole === 'admin'
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
                      title: 'Academic Years',
                      href: AcademicYearController.index().url,
                      icon: CalendarRange,
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
        ...(activeRole === 'student'
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
        ...(activeRole === 'teacher' || activeRole === 'admin'
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
        ...(activeRole === 'parent'
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

    const reportNavItems: NavItem[] = [
        'admin',
        'teacher',
        'principal',
        'counselor',
    ].includes(activeRole)
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
              ...(['admin', 'teacher', 'principal'].includes(activeRole)
                  ? [
                        {
                            title: 'Presence Board',
                            href: PresenceBoardController.index().url,
                            icon: Monitor,
                        },
                    ]
                  : []),
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

                {roles.length > 1 && (
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <SidebarMenuButton className="justify-between border text-xs">
                                        <span className="flex items-center gap-1.5 capitalize font-medium">
                                            <span className="text-[10px] uppercase tracking-wider text-muted-foreground">
                                                Role:
                                            </span>
                                            {activeRole}
                                        </span>
                                        <ChevronsUpDown className="size-3.5 opacity-50" />
                                    </SidebarMenuButton>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="start"
                                    className="w-(--radix-dropdown-menu-trigger-width) min-w-44"
                                >
                                    {roles.map((r) => (
                                        <DropdownMenuItem
                                            key={r}
                                            className={`cursor-pointer capitalize ${r === activeRole ? 'bg-accent text-accent-foreground font-semibold' : ''}`}
                                            onClick={() => {
                                                if (r !== activeRole) {
                                                    router.post('/active-role', {
                                                        role: r,
                                                    });
                                                }
                                            }}
                                        >
                                            {r}
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </SidebarMenuItem>
                    </SidebarMenu>
                )}
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
