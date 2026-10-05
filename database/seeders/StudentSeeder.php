<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\EnrollmentService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    use WithoutModelEvents;

    public function __construct(private readonly EnrollmentService $enrollments) {}

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $class5a = SchoolClass::where('name', 'Kelas 5A')->first();
        $class5b = SchoolClass::where('name', 'Kelas 5B')->first();

        if ($class5a === null || $class5b === null) {
            return;
        }

        $activeYear = AcademicYear::active();
        $startDate = $activeYear?->starts_at?->toDateString();

        $roster = [
            ['Ahmad Fauzi', 'Ahmad', $class5a],
            ['Ayu Lestari', 'Ayu', $class5a],
            ['Bagas Pratama', 'Bagas', $class5a],
            ['Citra Kirana', 'Citra', $class5a],
            ['Dimas Saputra', 'Dimas', $class5b],
            ['Eka Putri', 'Eka', $class5b],
            ['Fajar Nugroho', 'Fajar', $class5b],
            ['Gita Hapsari', 'Gita', $class5b],
        ];

        foreach ($roster as $index => [$fullName, $nickname, $class]) {
            $nis = (string) (2410001 + $index);

            $student = Student::firstOrCreate(
                ['student_number' => $nis],
                [
                    'full_name' => $fullName,
                    'nickname' => $nickname,
                    'dob' => sprintf('201%d-0%d-1%d', $index % 3, ($index % 9) + 1, ($index % 8) + 1),
                ]
            );

            if ($index < 2) {
                $userHandle = 'student'.($index + 1);
                $studentUser = User::where('username', $userHandle)->first();
                if ($studentUser !== null && $student->user_id !== $studentUser->id) {
                    $student->update(['user_id' => $studentUser->id]);
                }
            }

            // Assign enrollment
            $this->enrollments->assign($student, $class, $startDate);
        }
    }
}
