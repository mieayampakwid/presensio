<?php

namespace App\Jobs;

use App\Models\ReportCardPublication;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Services\ReportCards\ReportCardPdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Build the combined "Cetak semua" PDF for a class from the current
 * publication's stored snapshots and store it on the private disk.
 */
class BuildClassReportCardPdf implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly SchoolClass $schoolClass,
        public readonly Semester $semester,
        public readonly ?int $publicationId = null,
    ) {}

    public function handle(ReportCardPdf $pdf): void
    {
        // Anchor on the requested publication when given, otherwise the
        // current one: a republish never exports a stale version's file.
        $publication = ReportCardPublication::query()
            ->where('class_id', $this->schoolClass->id)
            ->where('semester_id', $this->semester->id)
            ->when(
                $this->publicationId !== null,
                fn ($query) => $query->whereKey($this->publicationId),
                fn ($query) => $query->current(),
            )
            ->first();

        if ($publication === null) {
            return;
        }

        $cards = $publication->reportCards()->get();

        $payloads = $cards
            ->sortBy(fn ($card) => $card->payload['student']['name'])
            ->map(fn ($card) => $card->payload)
            ->values()
            ->all();

        Storage::disk('local')->put(
            self::path($publication),
            $pdf->bundle($payloads, $cards->first()?->curriculum?->value),
        );
    }

    /**
     * Private bundle path, keyed by publication id and version so a republish
     * writes a new file instead of reusing the previous version's export.
     */
    public static function path(ReportCardPublication $publication): string
    {
        return 'report-card-bundles/'.$publication->id.'-v'.$publication->version.'.pdf';
    }
}
