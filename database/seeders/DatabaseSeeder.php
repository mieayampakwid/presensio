<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\ScanMethod;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\Guardian;
use App\Models\NonSchoolDay;
use App\Models\RfidCard;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\SchoolSettings;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

/**
 * Development seeder: a deterministic school with every persona wired
 * up — admin, two homeroom teachers with classes, eight students, four
 * guardians (including a phone-less one and a dual-guardian family) and
 * a couple of RFID cards. Plus a month of demo attendance ending in a
 * partial "live morning" so the reports and presence board have data.
 * Run with `php artisan migrate:fresh --seed`.
 *
 * Login usernames are numbered handles (teacher1, student1, parent1, …)
 * for dev convenience — the numbers are persona indexes, NOT relation
 * keys (the relation graph is intentionally asymmetric and is printed
 * after seeding); NIP/NIS identity numbers live on the
 * teacher_number/student_number profile columns where spec 01 puts
 * them. Every account uses the password "password". Never runs in
 * production — production admins come from `php artisan app:create-admin`.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('Refusing to seed demo data in production — use app:create-admin instead.');

            return;
        }

        $password = Hash::make('password');

        // --- Admin -------------------------------------------------------
        $admin = User::create([
            'username' => 'admin',
            'email' => 'admin@presensio.test',
            'password' => $password,
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        // --- Teachers + homeroom classes ----------------------------------
        $budi = Teacher::create(['name' => 'Budi Santoso', 'teacher_number' => '198505052010011001', 'phone_number' => '+628111002001']);
        $siti = Teacher::create(['name' => 'Siti Aminah', 'teacher_number' => '198703102011012002', 'phone_number' => '+628111002002']);
        $budiUser = User::create(['username' => 'teacher1', 'email' => 'teacher1@presensio.test', 'password' => $password, 'role' => UserRole::Teacher, 'is_active' => true]);
        $sitiUser = User::create(['username' => 'teacher2', 'email' => 'teacher2@presensio.test', 'password' => $password, 'role' => UserRole::Teacher, 'is_active' => true]);
        $budi->update(['user_id' => $budiUser->id]);
        $siti->update(['user_id' => $sitiUser->id]);

        $class5a = SchoolClass::create(['name' => 'Kelas 5A', 'teacher_id' => $budi->id]);
        $class5b = SchoolClass::create(['name' => 'Kelas 5B', 'teacher_id' => $siti->id]);

        // --- Students (first two get portal logins) ------------------------
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

        $students = [];

        foreach ($roster as $index => [$fullName, $nickname, $class]) {
            $nis = (string) (2410001 + $index);

            $student = Student::create([
                'full_name' => $fullName,
                'nickname' => $nickname,
                'dob' => sprintf('201%d-0%d-1%d', $index % 3, ($index % 9) + 1, ($index % 8) + 1),
                'student_number' => $nis,
                'class_id' => $class->id,
            ]);

            if ($index < 2) {
                $studentUser = User::create([
                    'username' => 'student'.($index + 1),
                    'email' => 'student'.($index + 1).'@presensio.test',
                    'password' => $password,
                    'role' => UserRole::Student,
                    'is_active' => true,
                ]);
                $student->update(['user_id' => $studentUser->id]);
            }

            $students[$fullName] = $student;
        }

        // --- Guardians -----------------------------------------------------
        // Four coverage cases for the notification pipeline: user + phone,
        // phone only, phone-less (email-only impossible → silent skip), and
        // a dual-guardian family (both get one message per absence).
        $slamet = Guardian::create([
            'user_id' => null,
            'name' => 'Slamet Riyadi',
            'phone_number' => '+628111000001',
            'work' => 'Merchant',
            'address' => 'Jl. Kenanga 9, Jakarta',
        ]);
        $slametUser = User::create(['username' => 'parent1', 'email' => 'parent1@presensio.test', 'password' => $password, 'role' => UserRole::Parent, 'is_active' => true]);
        $slamet->update(['user_id' => $slametUser->id]);

        $dewi = Guardian::create([
            'user_id' => null,
            'name' => 'Dewi Lestari',
            'phone_number' => '+628111000003',
            'work' => 'Teacher',
            'address' => 'Jl. Kenanga 9, Jakarta',
        ]);
        $dewiUser = User::create(['username' => 'parent2', 'email' => 'parent2@presensio.test', 'password' => $password, 'role' => UserRole::Parent, 'is_active' => true]);
        $dewi->update(['user_id' => $dewiUser->id]);

        $sitiRahayu = Guardian::create([
            'user_id' => null,
            'name' => 'Siti Rahayu',
            'phone_number' => '+628111000002',
        ]);

        // No phone, no user account — no reachable channel.
        $bambang = Guardian::create([
            'user_id' => null,
            'name' => 'Bambang Sutrisno',
            'phone_number' => '',
        ]);

        // Loud on typos — a demo-data name miss must fail the seed, not
        // silently attach nothing.
        $studentByName = function (string $fullName) use ($students): Student {
            return $students[$fullName] ?? throw new InvalidArgumentException("Unknown demo student: {$fullName}");
        };

        $slamet->students()->attach([$studentByName('Ahmad Fauzi')->id, $studentByName('Dimas Saputra')->id]);
        $dewi->students()->attach([$studentByName('Ahmad Fauzi')->id, $studentByName('Ayu Lestari')->id]);
        $sitiRahayu->students()->attach($studentByName('Eka Putri')->id);
        $bambang->students()->attach($studentByName('Fajar Nugroho')->id);

        // --- RFID cards -----------------------------------------------------
        RfidCard::create(['rfid_number' => 'CARD-001', 'student_id' => $studentByName('Ahmad Fauzi')->id]);
        RfidCard::create(['rfid_number' => 'CARD-002', 'student_id' => $studentByName('Ayu Lestari')->id]);
        RfidCard::create(['rfid_number' => 'CARD-SPARE', 'student_id' => null]);

        // --- Demo attendance (current month → a partial live morning) -------
        // Deterministic mix of present/late/absent plus short sick spells;
        // today stays a partial morning so the presence board has a live
        // state. Runs under WithoutModelEvents — no notifications can fire.
        $settings = app(SchoolSettings::class);
        $tz = $settings->timezone();
        $today = $settings->now()->startOfDay();
        $isSchoolDay = fn (string $date) => ! Date::parse($date, $tz)->isWeekend()
            && ! NonSchoolDay::query()->whereDate('date', $date)->exists();

        $record = function (Student $student, string $date, AttendanceStatus $status, ?string $in = null, ?string $out = null) use ($tz): void {
            $scanned = in_array($status, [AttendanceStatus::Present, AttendanceStatus::Late], true);

            Attendance::create([
                'student_id' => $student->id,
                'date' => $date,
                'status' => $status,
                'checked_in_at' => $in === null ? null : Date::parse("{$date} {$in}", $tz)->tz('UTC'),
                'checked_out_at' => $out === null ? null : Date::parse("{$date} {$out}", $tz)->tz('UTC'),
                'scan_method' => $scanned ? ScanMethod::Rfid : null,
            ]);
        };

        $dayNumber = 0;

        for ($day = $today->copy()->startOfMonth(); $day->lessThan($today); $day = $day->addDay()) {
            if ($day->isWeekend() || ! $isSchoolDay($day->toDateString())) {
                continue;
            }

            $dayNumber++;
            $date = $day->toDateString();

            foreach (array_values($students) as $index => $student) {
                // Short sick spells for two students mid-month (excuse-style
                // records: no times, no scan method).
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
                $record($studentByName($fullName), $today->toDateString(), $status, $in, $out);
            }
        }

        $this->command->table(
            ['Role', 'Username', 'Password', 'Profile'],
            [
                ['Admin', $admin->username, 'password', '—'],
                ['Teacher', $budiUser->username, 'password', 'Budi Santoso'],
                ['Teacher', $sitiUser->username, 'password', 'Siti Aminah'],
                ['Student', 'student1', 'password', 'Ahmad Fauzi (Kelas 5A)'],
                ['Student', 'student2', 'password', 'Ayu Lestari (Kelas 5A)'],
                ['Parent', $slametUser->username, 'password', 'Slamet Riyadi'],
                ['Parent', $dewiUser->username, 'password', 'Dewi Lestari'],
            ],
        );

        $this->command->table(
            ['Relation', 'Who'],
            [
                ['Homeroom', 'teacher1 → Kelas 5A (2410001–2410004), teacher2 → Kelas 5B (2410005–2410008)'],
                ['parent1 (Slamet)', 'Ahmad Fauzi (5A) + Dimas Saputra (5B) — children across both classes'],
                ['parent2 (Dewi)', 'Ahmad Fauzi (5A) + Ayu Lestari (5A) — shares Ahmad with parent1 (dual-guardian)'],
                ['Siti Rahayu (no login)', 'Eka Putri (5B) — phone only, WhatsApp path'],
                ['Bambang Sutrisno (no login)', 'Fajar Nugroho (5B) — no phone, no user: unreachable, silent skip'],
            ],
        );
    }
}
