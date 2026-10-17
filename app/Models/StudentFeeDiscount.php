<?php

namespace App\Models;

use Database\Factories\StudentFeeDiscountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Per-student percentage or fixed reduction on a fee type for one academic year.
 *
 * @property int $id
 * @property int $student_id
 * @property int $fee_type_id
 * @property int $academic_year_id
 * @property string|null $percent
 * @property string|null $fixed_amount
 * @property string $reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Student $student
 * @property-read FeeType $feeType
 * @property-read AcademicYear $academicYear
 */
#[Fillable([
    'student_id',
    'fee_type_id',
    'academic_year_id',
    'percent',
    'fixed_amount',
    'reason',
])]
class StudentFeeDiscount extends Model
{
    /** @use HasFactory<StudentFeeDiscountFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<FeeType, $this>
     */
    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'percent' => 'decimal:2',
            'fixed_amount' => 'decimal:2',
        ];
    }
}
