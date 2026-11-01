import ScannerController from '@/actions/App/Http/Controllers/Attendance/ScannerController';
import AnnouncementReaderController from '@/actions/App/Http/Controllers/Announcements/AnnouncementReaderController';
import { Link, router, usePage } from '@inertiajs/react';
import {
    AlarmClock,
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
import OverpaymentController from '@/actions/App/Http/Controllers/Fees/OverpaymentController';
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
import BellScheduleController from '@/actions/App/Http/Controllers/Timetable/BellScheduleController';
import LeaveReviewController from '@/actions/App/Http/Controllers/Staff/LeaveReviewController';
import MyLeaveController from '@/actions/App/Http/Controllers/Staff/MyLeaveController';
import StaffAttendanceController from '@/actions/App/Http/Controllers/Staff/StaffAttendanceController';
import StaffRecapController from '@/actions/App/Http/Controllers/Staff/StaffRecapController';
import ScheduleController from '@/actions/App/Http/Controllers/Teacher/ScheduleController';
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
import { useTranslation } from '@/hooks/use-translation';
import type { Auth, NavItem, UserRole } from '@/types';

export function AppSidebar() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const { t } = useTranslation();
    const activeRole = auth.active_role ?? auth.user.role;
    const isOperator =
        auth.user.roles?.includes('scanner_operator') ??
        auth.user.role === 'scanner_operator';
    const scannerItem: NavItem = {
        title: t('scanner.title'),
        href: ScannerController.index(),
        icon: QrCode,
    };
    const roles: UserRole[] = auth.user.roles ?? [auth.user.role];

    const mainNavItems: NavItem[] = [
        {
            title: t('nav.items.dashboard'),
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: t('nav.items.announcements'),
            href: AnnouncementReaderController.index().url,
            icon: FileText,
        },
        ...(activeRole === 'admin'
            ? [
                  { title: t('nav.items.users'), href: '/users', icon: Users },
                  {
                      title: t('nav.items.employees'),
                      href: EmployeeController.index().url,
                      icon: Briefcase,
                  },
                  {
                      title: t('nav.items.teachers'),
                      href: TeacherController.index().url,
                      icon: UserRound,
                  },
                  {
                      title: t('nav.items.guardians'),
                      href: GuardianController.index().url,
                      icon: Users,
                  },
                  {
                      title: t('nav.items.students'),
                      href: StudentController.index().url,
                      icon: GraduationCap,
                  },
                  {
                      title: t('nav.items.classes'),
                      href: SchoolClassController.index().url,
                      icon: School,
                  },
                  {
                      title: t('nav.items.subjects'),
                      href: SubjectController.index().url,
                      icon: BookOpen,
                  },
                  {
                      title: t('nav.items.bell_schedules'),
                      href: BellScheduleController.index().url,
                      icon: AlarmClock,
                  },
                  {
                      title: t('nav.items.courses'),
                      href: '/courses',
                      icon: BookMarked,
                  },
                  {
                      title: t('nav.items.learning_objectives'),
                      href: '/objectives',
                      icon: Target,
                  },
                  {
                      title: t('nav.items.academic_years'),
                      href: AcademicYearController.index().url,
                      icon: CalendarRange,
                  },
                  {
                      title: t('nav.items.rfid_cards'),
                      href: RfidCardController.index().url,
                      icon: IdCard,
                  },
                  {
                      title: t('nav.items.non_school_days'),
                      href: NonSchoolDayController.index().url,
                      icon: CalendarOff,
                  },
                  {
                      title: t('nav.items.audit_logs'),
                      href: '/admin/audit-logs',
                      icon: FileText,
                  },
              ]
            : []),
        ...(activeRole === 'principal'
            ? [
                  {
                      title: t('nav.items.classes'),
                      href: SchoolClassController.index().url,
                      icon: School,
                  },
                  {
                      title: t('nav.items.audit_logs'),
                      href: '/admin/audit-logs',
                      icon: FileText,
                  },
              ]
            : []),
        ...(['admin', 'principal'].includes(activeRole)
            ? [
                  {
                      title: t('nav.items.staff_attendance'),
                      href: StaffAttendanceController.index().url,
                      icon: CalendarClock,
                  },
                  {
                      title: t('nav.items.staff_leave'),
                      href: LeaveReviewController.index().url,
                      icon: ClipboardList,
                  },
                  {
                      title: t('nav.items.staff_recap'),
                      href: StaffRecapController.index().url,
                      icon: FileSpreadsheet,
                  },
                  {
                      title: t('nav.items.notification_deliveries'),
                      href: NotificationDeliveryController.index().url,
                      icon: BellRing,
                  },
              ]
            : []),
        ...(activeRole === 'staff'
            ? [
                  {
                      title: t('nav.items.my_qr'),
                      href: myQr().url,
                      icon: QrCode,
                  },
                  {
                      title: t('nav.items.my_leave'),
                      href: MyLeaveController.index().url,
                      icon: Palmtree,
                  },
              ]
            : []),
        ...(activeRole === 'student'
            ? [
                  {
                      title: t('nav.items.my_qr'),
                      href: myQr().url,
                      icon: QrCode,
                  },
                  {
                      title: t('nav.items.my_attendance'),
                      href: myAttendance().url,
                      icon: CalendarCheck,
                  },
                  {
                      title: t('nav.items.my_report'),
                      href: StudentReportController.index().url,
                      icon: ClipboardList,
                  },
                  {
                      title: t('nav.items.my_grades'),
                      href: '/my-grades',
                      icon: FileSpreadsheet,
                  },
                  {
                      title: t('nav.items.my_report_cards'),
                      href: '/my-report-cards',
                      icon: GraduationCap,
                  },
                  {
                      title: t('nav.items.school_fees'),
                      href: StudentFeeController.index().url,
                      icon: Wallet,
                  },
              ]
            : []),
        ...(activeRole === 'teacher'
            ? [
                  {
                      title: t('nav.items.my_qr'),
                      href: myQr().url,
                      icon: QrCode,
                  },
                  {
                      title: t('nav.items.my_leave'),
                      href: MyLeaveController.index().url,
                      icon: Palmtree,
                  },
                  {
                      title: t('nav.items.classes'),
                      href: SchoolClassController.index().url,
                      icon: School,
                  },
                  {
                      title: t('nav.items.my_schedule'),
                      href: ScheduleController.index().url,
                      icon: CalendarClock,
                  },
                  {
                      title: t('nav.items.my_courses'),
                      href: '/courses',
                      icon: BookOpen,
                  },
                  {
                      title: t('nav.items.learning_objectives'),
                      href: '/objectives',
                      icon: Target,
                  },
              ]
            : []),
        ...(activeRole === 'teacher' || activeRole === 'admin'
            ? [
                  {
                      title: t('nav.items.attendance'),
                      href: AttendanceController.index().url,
                      icon: CalendarCheck,
                  },
                  {
                      title: t('nav.items.excuses'),
                      href: ExcuseReviewController.index().url,
                      icon: FileText,
                  },
              ]
            : []),
        ...(activeRole === 'parent'
            ? [
                  {
                      title: t('nav.items.my_excuses'),
                      href: my().url,
                      icon: FileText,
                  },
                  {
                      title: t('nav.items.child_report_cards'),
                      href: ChildReportCardController.landing().url,
                      icon: GraduationCap,
                  },
                  {
                      title: t('nav.items.school_fees'),
                      href: ParentFeeController.index().url,
                      icon: Wallet,
                  },
                  {
                      title: t('nav.items.child_report'),
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
                  title: t('nav.items.chart_of_accounts'),
                  href: AccountController.index().url,
                  icon: Landmark,
              },
              {
                  title: t('nav.items.journals'),
                  href: ManualJournalController.index().url,
                  icon: Receipt,
              },
              {
                  title: t('nav.items.accounting_periods'),
                  href: PeriodController.index().url,
                  icon: CalendarClock,
              },
              {
                  title: t('nav.items.fee_types'),
                  href: FeeTypeController.index().url,
                  icon: Tags,
              },
              {
                  title: t('nav.items.fee_discounts'),
                  href: DiscountController.index().url,
                  icon: Percent,
              },
              {
                  title: t('nav.items.bills'),
                  href: BillController.index().url,
                  icon: ReceiptText,
              },
              {
                  title: t('nav.items.overpayments'),
                  href: OverpaymentController.index().url,
                  icon: Banknote,
              },
              {
                  title: t('nav.items.payments'),
                  href: PaymentController.index().url,
                  icon: CreditCard,
              },
              {
                  title: t('nav.items.transfer_verification'),
                  href: VerificationController.index().url,
                  icon: BadgeCheck,
              },
              ...(['admin', 'finance'].includes(activeRole)
                  ? [
                        {
                            title: t('nav.items.generate_bills'),
                            href: BillGenerationController.index().url,
                            icon: FilePlus2,
                        },
                        {
                            title: t('nav.items.cashier'),
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
                      title: t('nav.items.student_report'),
                      href: StudentReportController.index().url,
                      icon: FileSpreadsheet,
                  },
                  {
                      title: t('nav.items.class_report'),
                      href: ClassReportController.index().url,
                      icon: Table2,
                  },
                  ...(['admin', 'teacher', 'principal'].includes(activeRole)
                      ? [
                            {
                                title: t('nav.items.presence_board'),
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
                      title: t('nav.items.general_journal'),
                      href: ReportController.generalJournal().url,
                      icon: FileText,
                  },
                  {
                      title: t('nav.items.general_ledger'),
                      href: ReportController.generalLedger().url,
                      icon: BookOpen,
                  },
                  {
                      title: t('nav.items.trial_balance'),
                      href: ReportController.trialBalance().url,
                      icon: Scale,
                  },
                  {
                      title: t('nav.items.cash_book'),
                      href: ReportController.cashBook().url,
                      icon: Wallet,
                  },
                  {
                      title: t('nav.items.receivables_reconciliation'),
                      href: ReportController.receivablesReconciliation().url,
                      icon: Landmark,
                  },
                  {
                      title: t('nav.items.student_receivable_cards'),
                      href: ReportController.studentReceivableCard().url,
                      icon: IdCard,
                  },
                  {
                      title: t('nav.items.receivables_aging'),
                      href: ReportController.receivablesAging().url,
                      icon: Table2,
                  },
                  {
                      title: t('nav.items.collections_by_class'),
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
                            <Link
                                href={
                                    isOperator
                                        ? ScannerController.index()
                                        : dashboard()
                                }
                                prefetch
                            >
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
                                        <span className="flex items-center gap-1.5 font-medium">
                                            <span className="text-muted-foreground text-[10px] tracking-wider uppercase">
                                                {t('nav.role_selector.label')}
                                            </span>
                                            {t(`roles.${activeRole}`)}
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
                                            className={`cursor-pointer ${r === activeRole ? 'bg-accent text-accent-foreground font-semibold' : ''}`}
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
                                            {t(`roles.${r}`)}
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </SidebarMenuItem>
                    </SidebarMenu>
                )}
            </SidebarHeader>

            <SidebarContent>
                <NavMain
                    label={t('nav.groups.platform')}
                    items={
                        isOperator
                            ? [scannerItem]
                            : [
                                  ...mainNavItems,
                                  ...(['admin', 'teacher'].includes(activeRole)
                                      ? [scannerItem]
                                      : []),
                              ]
                    }
                />
                {financeNavItems.length > 0 && (
                    <NavMain
                        label={t('nav.groups.finance')}
                        items={financeNavItems}
                    />
                )}
                {reportNavItems.length > 0 && (
                    <NavMain
                        label={t('nav.groups.reports')}
                        items={reportNavItems}
                    />
                )}
            </SidebarContent>

            <SidebarFooter>
                {!isOperator && (
                    <NavFooter
                        items={[
                            {
                                title: t('nav.items.repository'),
                                href: 'https://github.com/laravel/react-starter-kit',
                                icon: FolderGit2,
                            },
                            {
                                title: t('nav.items.documentation'),
                                href: 'https://laravel.com/docs/starter-kits#react',
                                icon: BookOpen,
                            },
                        ]}
                        className="mt-auto"
                    />
                )}
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
