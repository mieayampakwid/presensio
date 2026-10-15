<?php

namespace App\Services\ReportCards;

use App\Models\ReportCard;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Read model for the guardian/student report card archive. Every label and
 * date comes from the stored snapshot payload — live semester or school
 * settings changes never rewrite an issued archive entry.
 */
class ReportCardArchiveService
{
    /**
     * The current (non-retracted) snapshot per semester for the student,
     * newest publication first; when several classes share a semester the
     * latest current publication wins.
     *
     * @return Collection<int, ReportCard>
     */
    public function currentCards(Student $student): Collection
    {
        return ReportCard::query()
            ->where('student_id', $student->id)
            ->whereHas('publication', fn ($query) => $query->current())
            ->with('publication')
            ->get()
            ->sortByDesc(fn (ReportCard $card) => [$card->publication->published_at, $card->publication->id])
            ->unique('publication.semester_id')
            ->values();
    }

    /**
     * Archive entries for the page: frozen snapshot labels and issue dates.
     *
     * @param  Collection<int, ReportCard>  $cards
     * @return array<int, array{id: int, name: string, number: int, version: int, published_at: string}>
     */
    public function summaries(Collection $cards): array
    {
        return $cards
            ->map(fn (ReportCard $card) => [
                'id' => $card->publication->semester_id,
                'name' => $card->payload['semester']['name'],
                'number' => $card->payload['semester']['number'],
                'version' => $card->payload['version'],
                'published_at' => $card->payload['published_at'],
            ])
            ->all();
    }

    /**
     * The student's snapshot card for the given semester, or the newest one
     * when no semester is requested.
     *
     * @param  Collection<int, ReportCard>  $cards
     */
    public function selected(Collection $cards, ?int $semesterId): ?ReportCard
    {
        if ($semesterId === null) {
            return $cards->first();
        }

        return $cards->first(fn (ReportCard $card) => $card->publication->semester_id === $semesterId);
    }

    /**
     * The stored snapshot payload of the given card.
     *
     * @return array<string, mixed>|null
     */
    public function payload(?ReportCard $card): ?array
    {
        return $card?->payload;
    }
}
