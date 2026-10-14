<?php

namespace App\Services\Notifications;

use App\Enums\NotificationType;
use App\Models\Guardian;
use App\Models\ReportCardPublication;
use App\Models\Student;

/**
 * Guardian and student notifications for report card publications
 * (spec 17): payloads carry no scores, and the publication id anchors the
 * dedupe base so re-dispatching never duplicates (AC-17-02).
 */
class ReportCardNotifier
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    /**
     * Notify guardians and students that a report card version was published.
     */
    public function published(ReportCardPublication $publication): void
    {
        $this->dispatch(
            type: NotificationType::ReportCardPublished,
            publication: $publication,
            dedupeBase: "publication:{$publication->id}",
            title: 'Rapor Diterbitkan',
            body: fn (Student $student): string => "Rapor {$student->full_name} untuk {$publication->semester->name} telah diterbitkan.",
        );
    }

    /**
     * Notify guardians and students that a report card version was retracted.
     */
    public function retracted(ReportCardPublication $publication): void
    {
        $this->dispatch(
            type: NotificationType::ReportCardRetracted,
            publication: $publication,
            dedupeBase: "publication:{$publication->id}:retracted",
            title: 'Rapor Ditarik',
            body: fn (Student $student): string => "Rapor {$student->full_name} untuk {$publication->semester->name} ditarik sementara.",
        );
    }

    /**
     * @param  callable(Student): string  $body
     */
    private function dispatch(
        NotificationType $type,
        ReportCardPublication $publication,
        string $dedupeBase,
        string $title,
        callable $body,
    ): void {
        $publication->loadMissing(['semester', 'reportCards.student.user', 'reportCards.student.guardians.user']);

        foreach ($publication->reportCards as $card) {
            $student = $card->student;

            $guardians = $student->guardians->filter(
                fn (Guardian $guardian) => $guardian->user_id !== null || $guardian->phone_number !== '',
            );

            if ($guardians->isNotEmpty()) {
                $this->dispatcher->dispatch(
                    type: $type,
                    recipients: $guardians->map(fn (Guardian $guardian) => Recipient::fromGuardian($guardian))->values(),
                    message: new Message(
                        title: $title,
                        body: $body($student),
                        route: 'children.report-cards.index',
                        routeParameters: ['student' => $student->id],
                    ),
                    dedupeBase: $dedupeBase,
                );
            }

            if ($student->user_id !== null) {
                $this->dispatcher->dispatch(
                    type: $type,
                    recipients: collect([Recipient::fromUser($student->user)]),
                    message: new Message(
                        title: $title,
                        body: $body($student),
                        route: 'my-report-cards',
                    ),
                    dedupeBase: $dedupeBase,
                );
            }
        }
    }
}
