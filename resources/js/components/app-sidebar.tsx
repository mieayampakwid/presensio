import { Link, router, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    Banknote,
    BellRing,
    BookMarked,
    BookOpen,
    Briefcase,
    CalendarCheck,
    CalendarClock,
    CalendarOff,
    CalendarRange,
    ChevronsUpDown,
    ClipboardList,
    CreditCard,
    FilePlus2,
    FileSpreadsheet,
    FileText,
    FolderGit2,
    GraduationCap,
    IdCard,
    Landmark,
    LayoutGrid,
    Monitor,
    Palmtree,
    Percent,
    QrCode,
    Receipt,
    ReceiptText,
    Scale,
    School,
    Table2,
    Tags,
    Target,
    UserRound,
    Users,
    Wallet,
} from 'lucide-react';
import AcademicYearController from '@/actions/App/Http/Controllers/AcademicYears/AcademicYearController';
import AttendanceController from '@/actions/App/Http/Controllers/Attendance/AttendanceController';
import NonSchoolDayController from '@/actions/App/Http/Controllers/Calendar/NonSchoolDayController';
import SchoolClassController from '@/actions/App/Http/Controllers/Classes/SchoolClassController';
import EmployeeController from '@/actions/App/Http/Controllers/Employees/EmployeeController';
import ExcuseReviewController from '@/actions/App/Http/Controllers/Excuses/ExcuseReviewController';
import BillController from '@/actions/App/Http/Controllers/Fees/BillController';
import BillGenerationController from '@/actions/App/Http/Controllers/Fees/BillGenerationController';
import CashierController from '@/actions/App/Http/Controllers/Fees/CashierController';
import DiscountController from '@/actions/App/Http/Controllers/Fees/DiscountController';
import FeeTypeController from '@/actions/App/Http/Controllers/Fees/FeeTypeController';
import PaymentController from '@/actions/App/Http/Controllers/Fees/PaymentController';
import VerificationController from '@/actions/App/Http/Controllers/Fees/VerificationController';
import AccountController from '@/actions/App/Http/Controllers/Finance/AccountController';
import ManualJournalController from '@/actions/App/Http/Controllers/Finance/ManualJournalController';
import PeriodController from '@/actions/App/Http/Controllers/Finance/PeriodController';
import ReportController from '@/actions/App/Http/Controllers/Finance/ReportController';
import GuardianController from '@/actions/App/Http/Controllers/Guardians/GuardianController';
import ClassReportController from '@/actions/App/Http/Controllers/Reports/ClassReportController';
import PresenceBoardController from '@/actions/App/Http/Controllers/Reports/PresenceBoardController';
import StudentReportController from '@/actions/App/Http/Controllers/Reports/StudentReportController';
import NotificationDeliveryController from '@/actions/App/Http/Controllers/Admin/NotificationDeliveryController';
import ChildReportCardController from '@/actions/App/Http/Controllers/Parent/ChildReportCardController';
import ParentFeeController from '@/actions/App/Http/Controllers/Parent/FeeController';
import StudentFeeController from '@/actions/App/Http/Controllers/Student/FeeController';
import RfidCardController from '@/actions/App/Http/Controllers/RfidCards/RfidCardController';
import StudentController from '@/actions/App/Http/Controllers/Students/StudentController';
import SubjectController from '@/actions/App/Http/Controllers/Subjects/SubjectController';
import LeaveReviewController from '@/actions/App/Http/Controllers/Staff/LeaveReviewController';
import MyLeaveController from '@/actions/App/Http/Controllers/Staff/MyLeaveController';
import StaffAttendanceController from '@/actions/App/Http/Controllers/Staff/StaffAttendanceController';
import StaffRecapController from '@/actions/App/Http/Controllers/Staff/StaffRecapController';
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
                      title: 'Employees',
                      href: EmployeeController.index().url,
                      icon: Briefcase,
                  },
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
                      title: 'Subjects',
                      href: SubjectController.index().url,
                      icon: BookOpen,
                  },
                  {
                      title: 'Courses',
                      href: '/courses',
                      icon: BookMarked,
                  },
                  {
                      title: 'Learning Objectives',
                      href: '/objectives',
                      icon: Target,
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
                  {
                      title: 'Audit Logs',
                      href: '/admin/audit-logs',
                      icon: FileText,
                  },
              ]
            : []),
        ...(activeRole === 'principal'
            ? [
                  {
                      title: 'Classes',
                      href: SchoolClassController.index().url,
                      icon: School,
                  },
                  {
                      title: 'Audit Logs',
                      href: '/admin/audit-logs',
                      icon: FileText,
                  },
              ]
            : []),
        ...(['admin', 'principal'].includes(activeRole)
            ? [
                  {
                      title: 'Staff Attendance',
                      href: StaffAttendanceController.index().url,
                      icon: CalendarClock,
                  },
                  {
                      title: 'Staff Leave',
                      href: LeaveReviewController.index().url,
                      icon: ClipboardList,
                  },
                  {
                      title: 'Staff Recap',
                      href: StaffRecapController.index().url,
                      icon: FileSpreadsheet,
                  },
                  {
                      title: 'Notification Deliveries',
                      href: NotificationDeliveryController.index().url,
                      icon: BellRing,
                  },
              ]
            : []),
        ...(activeRole === 'staff'
            ? [
                  {
                      title: 'My QR',
                      href: myQr().url,
                      icon: QrCode,
                  },
                  {
                      title: 'My Leave',
                      href: MyLeaveController.index().url,
                      icon: Palmtree,
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
                  {
                      title: 'My Grades',
                      href: '/my-grades',
                      icon: FileSpreadsheet,
                  },
                  {
                      title: 'Rapor Saya',
                      href: '/my-report-cards',
                      icon: GraduationCap,
                  },
                  {
                      title: 'Biaya Sekolah',
                      href: StudentFeeController.index().url,
                      icon: Wallet,
                  },
              ]
            : []),
        ...(activeRole === 'teacher'
            ? [
                  {
                      title: 'My QR',
                      href: myQr().url,
                      icon: QrCode,
                  },
                  {
                      title: 'My Leave',
                      href: MyLeaveController.index().url,
                      icon: Palmtree,
                  },
                  {
                      title: 'Classes',
                      href: SchoolClassController.index().url,
                      icon: School,
                  },
                  {
                      title: 'My Courses',
                      href: '/courses',
                      icon: BookOpen,
                  },
                  {
                      title: 'Learning Objectives',
                      href: '/objectives',
                      icon: Target,
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
                      title: 'Rapor Anak',
                      href: ChildReportCardController.landing().url,
                      icon: GraduationCap,
                  },
                  {
                      title: 'Biaya Sekolah',
                      href: ParentFeeController.index().url,
                      icon: Wallet,
                  },
                  {
                      title: 'Child Report',
                      href: StudentReportController.index().url,
                      icon: ClipboardList,
                  },
              ]
            : []),
    ];

    const financeNavItems: NavItem[] = [
        'admin',
        'finance',
        'principal',
    ].includes(activeRole)
        ? [
              {
                  title: 'Chart of Accounts',
                  href: AccountController.index().url,
                  icon: Landmark,
              },
              {
                  title: 'Journals',
                  href: ManualJournalController.index().url,
                  icon: Receipt,
              },
              {
                  title: 'Accounting Periods',
                  href: PeriodController.index().url,
                  icon: CalendarClock,
              },
              {
                  title: 'Fee Types',
                  href: FeeTypeController.index().url,
                  icon: Tags,
              },
              {
                  title: 'Fee Discounts',
                  href: DiscountController.index().url,
                  icon: Percent,
              },
              {
                  title: 'Bills',
                  href: BillController.index().url,
                  icon: ReceiptText,
              },
              {
                  title: 'Payments',
                  href: PaymentController.index().url,
                  icon: CreditCard,
              },
              {
                  title: 'Verifikasi Transfer',
                  href: VerificationController.index().url,
                  icon: BadgeCheck,
              },
              ...(['admin', 'finance'].includes(activeRole)
                  ? [
                        {
                            title: 'Generate Bills',
                            href: BillGenerationController.index().url,
                            icon: FilePlus2,
                        },
                        {
                            title: 'Cashier',
                            href: CashierController.index().url,
                            icon: Banknote,
                        },
                    ]
                  : []),
          ]
        : [];

    const reportNavItems: NavItem[] = [
        ...(['admin', 'teacher', 'principal', 'counselor'].includes(activeRole)
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
            : []),
        ...(['admin', 'finance', 'principal'].includes(activeRole)
            ? [
                  {
                      title: 'General Journal',
                      href: ReportController.generalJournal().url,
                      icon: FileText,
                  },
                  {
                      title: 'General Ledger',
                      href: ReportController.generalLedger().url,
                      icon: BookOpen,
                  },
                  {
                      title: 'Trial Balance',
                      href: ReportController.trialBalance().url,
                      icon: Scale,
                  },
                  {
                      title: 'Cash Book',
                      href: ReportController.cashBook().url,
                      icon: Wallet,
                  },
                  {
                      title: 'Rekonsiliasi Piutang',
                      href: ReportController.receivablesReconciliation().url,
                      icon: Landmark,
                  },
                  {
                      title: 'Kartu Piutang Siswa',
                      href: ReportController.studentReceivableCard().url,
                      icon: IdCard,
                  },
                  {
                      title: 'Umur Piutang',
                      href: ReportController.receivablesAging().url,
                      icon: Table2,
                  },
                  {
                      title: 'Penerimaan per Kelas',
                      href: ReportController.collectionsByClass().url,
                      icon: Receipt,
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

                {roles.length > 1 && (
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <SidebarMenuButton className="justify-between border text-xs">
                                        <span className="flex items-center gap-1.5 font-medium capitalize">
                                            <span className="text-muted-foreground text-[10px] tracking-wider uppercase">
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
                                                    router.post(
                                                        '/active-role',
                                                        {
                                                            role: r,
                                                        },
                                                    );
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
                {financeNavItems.length > 0 && (
                    <NavMain label="Finance" items={financeNavItems} />
                )}
                {reportNavItems.length > 0 && (
                    <NavMain label="Reports" items={reportNavItems} />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
