import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    CalendarCheck,
    FolderGit2,
    GraduationCap,
    IdCard,
    LayoutGrid,
    QrCode,
    School,
    UserRound,
    Users,
} from 'lucide-react';
import AttendanceController from '@/actions/App/Http/Controllers/Attendance/AttendanceController';
import SchoolClassController from '@/actions/App/Http/Controllers/Classes/SchoolClassController';
import GuardianController from '@/actions/App/Http/Controllers/Guardians/GuardianController';
import RfidCardController from '@/actions/App/Http/Controllers/RfidCards/RfidCardController';
import StudentController from '@/actions/App/Http/Controllers/Students/StudentController';
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
import { myQr } from '@/routes/attendance';
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
              ]
            : []),
        ...(auth.user.role === 'student'
            ? [
                  {
                      title: 'My QR',
                      href: myQr().url,
                      icon: QrCode,
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
              ]
            : []),
    ];

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
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
