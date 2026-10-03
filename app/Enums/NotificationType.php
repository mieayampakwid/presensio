<?php

namespace App\Enums;

/**
 * Catalog of notification types across Presensio (spec 17 §Decisions).
 */
enum NotificationType: string
{
    case AbsenceAlert = 'absence_alert';
    case ExcuseSubmitted = 'excuse_submitted';
    case ExcuseReviewed = 'excuse_reviewed';
    case ReportCardPublished = 'report_card_published';
    case ReportCardRetracted = 'report_card_retracted';
    case PaymentVerified = 'payment_verified';
    case PaymentRejected = 'payment_rejected';
    case BillDueReminder = 'bill_due_reminder';
    case AnnouncementUrgent = 'announcement_urgent';
    case AnnouncementAckRequired = 'announcement_ack_required';
    case LeaveRequestSubmitted = 'leave_request_submitted';
    case LeaveRequestReviewed = 'leave_request_reviewed';
    case StaffAbsenceDigest = 'staff_absence_digest';

    /**
     * Whether an in-app notification copy is written.
     */
    public function inApp(): bool
    {
        return true;
    }

    /**
     * Default WhatsApp channel toggle for a fresh installation.
     */
    public function defaultWhatsapp(): bool
    {
        return match ($this) {
            self::ReportCardPublished, self::PaymentRejected => true,
            default => false,
        };
    }

    /**
     * Default email channel toggle for a fresh installation.
     */
    public function defaultEmail(): bool
    {
        return false;
    }

    /**
     * Whether admins may enable WhatsApp delivery for this type.
     */
    public function whatsappAllowed(): bool
    {
        return match ($this) {
            self::ExcuseReviewed,
            self::ReportCardPublished,
            self::PaymentVerified,
            self::PaymentRejected,
            self::BillDueReminder,
            self::AnnouncementUrgent => true,
            default => false,
        };
    }

    /**
     * Whether admins may enable email delivery for this type.
     */
    public function emailAllowed(): bool
    {
        return $this->whatsappAllowed();
    }

    /**
     * Whether guardians may opt out of WhatsApp notifications for this type.
     */
    public function optOutAllowed(): bool
    {
        return match ($this) {
            self::ExcuseReviewed,
            self::ReportCardPublished,
            self::PaymentVerified,
            self::BillDueReminder,
            self::AnnouncementUrgent => true,
            default => false,
        };
    }

    /**
     * Whether sends of this type count against the daily WhatsApp quota.
     */
    public function quotaBound(): bool
    {
        return match ($this) {
            self::ExcuseReviewed,
            self::ReportCardPublished,
            self::PaymentVerified,
            self::BillDueReminder,
            self::AnnouncementUrgent => true,
            default => false,
        };
    }

    /**
     * Whether sends of this type are held during quiet hours.
     */
    public function quietHoursBound(): bool
    {
        return match ($this) {
            self::ExcuseReviewed,
            self::ReportCardPublished,
            self::PaymentVerified,
            self::BillDueReminder,
            self::AnnouncementUrgent => true,
            default => false,
        };
    }
}
