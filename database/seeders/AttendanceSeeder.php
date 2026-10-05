<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\ScanMethod;
use App\Models\Attendance;
use App\Models\NonSchoolDay;
use App\Models\Student;
use App\Services\SchoolSettings;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;

class AttendanceSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $students = Student::orderBy('id')->get();
        if ($students->isEmpty()) {
            return;
        }

        $settings = app(SchoolSettings::class);
        $tz = $settings->timezone();
        $today = $settings->now()->startOfDay();

        $isSchoolDay = fn (string $date) => ! Date::parse($date, $tz)->isWeekend()
            && ! NonSchoolDay::query()->whereDate('date', $date)->exists();

        $record = function (Student $student, string $date, AttendanceStatus $status, ?string $in = null, ?string $out = null) use ($tz): void {
            $scanned = in_array($status, [AttendanceStatus::Present, AttendanceStatus::Late], true);

            Attendance::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'date' => $date,
                ],
                [
                    'status' => $status,
                    'checked_in_at' => $in === null ? null : Date::parse("{$date} {$in}", $tz)->tz('UTC'),
                    'checked_out_at' => $out === null ? null : Date::parse("{$date} {$out}", $tz)->tz('UTC'),
                    'scan_method' => $scanned ? ScanMethod::Rfid : null,
                ]
            );
        };

        $dayNumber = 0;

        for ($day = $today->copy()->startOfMonth(); $day->lessThan($today); $day = $day->addDay()) {
            if ($day->isWeekend() || ! $isSchoolDay($day->toDateString())) {
                continue;
            }

            $dayNumber++;
            $date = $day->toDateString();

            foreach ($students as $index => $student) {
                // Short sick spells for two students mid-month
                if (in_array($index, [1, 5], true) && in_array($dayNumber, [8, 9, 17, 18], true)) {
                    $record($student, $date, AttendanceStatus::Sick);

                    continue;
                }

                $roll = ($index * 7 + $dayNumber * 3) % 11;

                match (true) {
                    $roll === 0 => $record($student, $date, AttendanceStatus::Absent),
                    $roll === 1 => $record($student, $date, AttendanceStatus::Late, sprintf('07:%02d', 33 + ($index * 4) % 20)),
                    in_array($roll, [2, 3], true) => $record($student, $date, AttendanceStatus::Present, sprintf('06:%02d', 50 + $index % 9), '13:30'),
                    default => $record($student, $date, AttendanceStatus::Present, sprintf('07:%02d', 2 + ($index * 3) % 25)),
                };
            }
        }

        if ($isSchoolDay($today->toDateString())) {
            $morning = [
                ['Ahmad Fauzi', AttendanceStatus::Present, '07:05', null],
                ['Ayu Lestari', AttendanceStatus::Late, '07:48', null],
                ['Bagas Pratama', AttendanceStatus::Present, '07:15', '13:00'],
                ['Citra Kirana', AttendanceStatus::Sick, null, null],
                ['Dimas Saputra', AttendanceStatus::Present, '07:10', null],
                ['Eka Putri', AttendanceStatus::Present, '07:12', null],
                // Fajar Nugroho deliberately left with no record yet.
                ['Gita Hapsari', AttendanceStatus::Present, '06:58', '12:45'],
            ];

            foreach ($morning as [$fullName, $status, $in, $out]) {
                $student = $students->firstWhere('full_name', $fullName);
                if ($student !== null) {
                    $record($student, $today->toDateString(), $status, $in, $out);
                }
            }
        }
    }
}
