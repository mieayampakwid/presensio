<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Guardian;
use App\Models\RfidCard;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Development seeder: a deterministic school with every persona wired
 * up — admin, two homeroom teachers with classes, eight students, four
 * guardians (including a phone-less one and a dual-guardian family) and
 * a couple of RFID cards. Run with `php artisan migrate:fresh --seed`.
 *
 * Every account uses the password "password". Never runs in production —
 * production admins come from `php artisan app:create-admin`.
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
        $budiUser = User::create(['username' => $budi->teacher_number, 'email' => 'budi@presensio.test', 'password' => $password, 'role' => UserRole::Teacher, 'is_active' => true]);
        $sitiUser = User::create(['username' => $siti->teacher_number, 'email' => 'siti@presensio.test', 'password' => $password, 'role' => UserRole::Teacher, 'is_active' => true]);
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
                    'username' => $nis,
                    'email' => strtolower(str_replace(' ', '.', $nickname)).'.siswa@presensio.test',
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
        $slametUser = User::create(['username' => '3204015501800001', 'email' => 'slamet@presensio.test', 'password' => $password, 'role' => UserRole::Parent, 'is_active' => true]);
        $slamet->update(['user_id' => $slametUser->id]);

        $dewi = Guardian::create([
            'user_id' => null,
            'name' => 'Dewi Lestari',
            'phone_number' => '+628111000003',
            'work' => 'Teacher',
            'address' => 'Jl. Kenanga 9, Jakarta',
        ]);
        $dewiUser = User::create(['username' => '3174020503820002', 'email' => 'dewi@presensio.test', 'password' => $password, 'role' => UserRole::Parent, 'is_active' => true]);
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

        $slamet->students()->attach([$students['Ahmad Fauzi']->id, $students['Dimas Saputra']->id]);
        $dewi->students()->attach([$students['Ahmad Fauzi']->id, $students['Ayu Lestari']->id]);
        $sitiRahayu->students()->attach($students['Eka Putri']->id);
        $bambang->students()->attach($students['Fajar Nugroho']->id);

        // --- RFID cards -----------------------------------------------------
        RfidCard::create(['rfid_number' => 'CARD-001', 'student_id' => $students['Ahmad Fauzi']->id]);
        RfidCard::create(['rfid_number' => 'CARD-002', 'student_id' => $students['Ayu Lestari']->id]);
        RfidCard::create(['rfid_number' => 'CARD-SPARE', 'student_id' => null]);

        $this->command->table(
            ['Role', 'Username', 'Password'],
            [
                ['Admin', $admin->username, 'password'],
                ['Teacher (Kelas 5A)', $budiUser->username, 'password'],
                ['Teacher (Kelas 5B)', $sitiUser->username, 'password'],
                ['Student (Ahmad)', '2410001', 'password'],
                ['Student (Ayu)', '2410002', 'password'],
                ['Parent (Slamet)', $slametUser->username, 'password'],
                ['Parent (Dewi)', $dewiUser->username, 'password'],
            ],
        );

        $this->command->info('Students 2410003–2410008 and guardians Siti Rahayu / Bambang Sutrisno have no user account — exercise the users-page profile linker on them.');
    }
}
