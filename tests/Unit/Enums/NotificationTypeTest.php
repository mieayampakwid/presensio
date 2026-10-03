<?php

namespace Tests\Unit\Enums;

use App\Enums\NotificationType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NotificationTypeTest extends TestCase
{
    public function test_catalog_has_all_thirteen_types(): void
    {
        $cases = NotificationType::cases();
        $this->assertCount(13, $cases);

        $keys = array_map(fn (NotificationType $t) => $t->value, $cases);
        $expected = [
            'absence_alert',
            'excuse_submitted',
            'excuse_reviewed',
            'report_card_published',
            'report_card_retracted',
            'payment_verified',
            'payment_rejected',
            'bill_due_reminder',
            'announcement_urgent',
            'announcement_ack_required',
            'leave_request_submitted',
            'leave_request_reviewed',
            'staff_absence_digest',
        ];

        $this->assertSame($expected, $keys);
    }

    #[DataProvider('catalogProvider')]
    public function test_catalog_row_spec_properties(
        NotificationType $type,
        bool $inApp,
        bool $defaultWhatsapp,
        bool $defaultEmail,
        bool $whatsappAllowed,
        bool $optOutAllowed,
        bool $quotaBound,
        bool $quietHoursBound
    ): void {
        $this->assertSame($inApp, $type->inApp(), "{$type->value} inApp");
        $this->assertSame($defaultWhatsapp, $type->defaultWhatsapp(), "{$type->value} defaultWhatsapp");
        $this->assertSame($defaultEmail, $type->defaultEmail(), "{$type->value} defaultEmail");
        $this->assertSame($whatsappAllowed, $type->whatsappAllowed(), "{$type->value} whatsappAllowed");
        $this->assertSame($optOutAllowed, $type->optOutAllowed(), "{$type->value} optOutAllowed");
        $this->assertSame($quotaBound, $type->quotaBound(), "{$type->value} quotaBound");
        $this->assertSame($quietHoursBound, $type->quietHoursBound(), "{$type->value} quietHoursBound");
    }

    /**
     * @return array<string, array{0: NotificationType, 1: bool, 2: bool, 3: bool, 4: bool, 5: bool, 6: bool, 7: bool}>
     */
    public static function catalogProvider(): array
    {
        return [
            'absence_alert' => [
                NotificationType::AbsenceAlert,
                true, false, false, false, false, false, false,
            ],
            'excuse_submitted' => [
                NotificationType::ExcuseSubmitted,
                true, false, false, false, false, false, false,
            ],
            'excuse_reviewed' => [
                NotificationType::ExcuseReviewed,
                true, false, false, true, true, true, true,
            ],
            'report_card_published' => [
                NotificationType::ReportCardPublished,
                true, true, false, true, true, true, true,
            ],
            'report_card_retracted' => [
                NotificationType::ReportCardRetracted,
                true, false, false, false, false, false, false,
            ],
            'payment_verified' => [
                NotificationType::PaymentVerified,
                true, false, false, true, true, true, true,
            ],
            'payment_rejected' => [
                NotificationType::PaymentRejected,
                true, true, false, true, false, false, false,
            ],
            'bill_due_reminder' => [
                NotificationType::BillDueReminder,
                true, false, false, true, true, true, true,
            ],
            'announcement_urgent' => [
                NotificationType::AnnouncementUrgent,
                true, false, false, true, true, true, true,
            ],
            'announcement_ack_required' => [
                NotificationType::AnnouncementAckRequired,
                true, false, false, false, false, false, false,
            ],
            'leave_request_submitted' => [
                NotificationType::LeaveRequestSubmitted,
                true, false, false, false, false, false, false,
            ],
            'leave_request_reviewed' => [
                NotificationType::LeaveRequestReviewed,
                true, false, false, false, false, false, false,
            ],
            'staff_absence_digest' => [
                NotificationType::StaffAbsenceDigest,
                true, false, false, false, false, false, false,
            ],
        ];
    }
}
