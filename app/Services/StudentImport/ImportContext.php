<?php

namespace App\Services\StudentImport;

use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\SchoolClass;

/**
 * Shared in-run state for validating rows: student numbers seen in this
 * file, guardians staged by phone, and the class-name => id map (existing
 * classes of the ACTIVE year preloaded; auto-created classes appended by
 * the run — imports never touch past years, spec 02 v2.0).
 */
class ImportContext
{
    /** @var array<string, true> */
    public array $seenStudentNumbers = [];

    /** @var array<string, int|null> phone => guardian id (null = created by this run) */
    public array $stagedGuardians = [];

    /** @var array<string, true> phones that a name+phone row in this file will create */
    public array $pendingGuardians = [];

    /** @var array<string, int> class name => id */
    public array $classes = [];

    public function __construct(public readonly AcademicYear $activeYear)
    {
        foreach (SchoolClass::query()->where('academic_year_id', $activeYear->id)->pluck('id', 'name') as $name => $id) {
            $this->classes[$name] = $id;
        }
    }

    public function isKnownClass(string $name): bool
    {
        return isset($this->classes[$name]);
    }

    public function stageClass(string $name, int $id): void
    {
        $this->classes[$name] = $id;
    }

    /**
     * Guardian id for a phone number: an existing DB guardian, one created
     * earlier in this run, or null when unknown.
     */
    public function guardianIdFor(string $phone): ?int
    {
        if (array_key_exists($phone, $this->stagedGuardians)) {
            return $this->stagedGuardians[$phone];
        }

        $id = Guardian::query()->where('phone_number', $phone)->value('id');

        if ($id !== null) {
            $this->stagedGuardians[$phone] = $id;
        }

        return $id;
    }

    public function stageGuardian(string $phone, int $id): void
    {
        $this->stagedGuardians[$phone] = $id;
    }

    /**
     * A name+phone row later in the file will create this guardian, so a
     * phone-only row referencing it is valid.
     */
    public function markPendingGuardian(string $phone): void
    {
        $this->pendingGuardians[$phone] = true;
    }

    public function isPendingGuardian(string $phone): bool
    {
        return isset($this->pendingGuardians[$phone]);
    }
}
