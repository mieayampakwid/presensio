<?php

namespace App\Models;

use App\Enums\Curriculum;
use Database\Factories\ReportCardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * An immutable per-student report card snapshot; the payload never changes after creation.
 *
 * @property int $id
 * @property int $report_card_publication_id
 * @property int $student_id
 * @property Curriculum $curriculum
 * @property array<string, mixed> $payload
 * @property Carbon $created_at
 * @property-read ReportCardPublication $publication
 * @property-read Student $student
 */
#[Fillable(['report_card_publication_id', 'student_id', 'curriculum', 'payload'])]
class ReportCard extends Model
{
    /** @use HasFactory<ReportCardFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Report card snapshots are immutable and cannot be updated.');
        });
    }

    /**
     * The publication this snapshot belongs to.
     *
     * @return BelongsTo<ReportCardPublication, $this>
     */
    public function publication(): BelongsTo
    {
        return $this->belongsTo(ReportCardPublication::class, 'report_card_publication_id');
    }

    /**
     * The student this snapshot describes.
     *
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'report_card_publication_id' => 'integer',
            'student_id' => 'integer',
            'curriculum' => Curriculum::class,
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
