<?php

namespace App\Enums;

enum Ability: string
{
    case ManageMasterData = 'manage-master-data';
    case OverrideAttendance = 'override-attendance';
    case ReviewExcuses = 'review-excuses';
    case ViewAttendanceReports = 'view-attendance-reports';
    case EnterGrades = 'enter-grades';
    case ViewGrades = 'view-grades';
    case PublishReportCards = 'publish-report-cards';
    case ViewReportCards = 'view-report-cards';
    case ManageFees = 'manage-fees';
    case ViewFees = 'view-fees';
    case ViewAuditLog = 'view-audit-log';
    case ManageStaffAttendance = 'manage-staff-attendance';
}
