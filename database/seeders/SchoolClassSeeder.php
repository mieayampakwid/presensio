<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Employee;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SchoolClassSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $academicYear = AcademicYear::active();
        if ($academicYear === null) {
            return;
        }

        $budiEmployee = Employee::where('employee_number', '198505052010011001')->first();
        $sitiEmployee = Employee::where('employee_number', '198703102011012002')->first();

        $budiTeacher = $budiEmployee ? Teacher::where('employee_id', $budiEmployee->id)->first() : null;
        $sitiTeacher = $sitiEmployee ? Teacher::where('employee_id', $sitiEmployee->id)->first() : null;

        $class5a = SchoolClass::firstOrCreate(
            [
                'academic_year_id' => $academicYear->id,
                'name' => 'Kelas 5A',
            ],
            [
                'teacher_id' => $budiTeacher?->id,
            ]
        );

        $class5b = SchoolClass::firstOrCreate(
            [
                'academic_year_id' => $academicYear->id,
                'name' => 'Kelas 5B',
            ],
            [
                'teacher_id' => $sitiTeacher?->id,
            ]
        );

        // Optional ClassSubject assignments
        $math = Subject::where('code', 'MAT')->first();
        if ($math !== null && $budiTeacher !== null) {
            ClassSubject::firstOrCreate(
                [
                    'class_id' => $class5a->id,
                    'subject_id' => $math->id,
                ],
                [
                    'teacher_id' => $budiTeacher->id,
                    'passing_threshold' => '75.00',
                ]
            );
        }

        if ($math !== null && $sitiTeacher !== null) {
            ClassSubject::firstOrCreate(
                [
                    'class_id' => $class5b->id,
                    'subject_id' => $math->id,
                ],
                [
                    'teacher_id' => $sitiTeacher->id,
                    'passing_threshold' => '75.00',
                ]
            );
        }
    }
}
