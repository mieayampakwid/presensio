<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Development seeder: a deterministic school with every persona wired
 * up — admin, principal, teachers with classes, non-teaching staff,
 * students, guardians, RFID cards, and demo attendance.
 * Run with `php artisan migrate:fresh --seed`.
 *
 * Every dev account uses the password "password". Never runs in
 * production — production admins come from `php artisan app:create-admin`.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('Refusing to seed demo data in production — use app:create-admin instead.');

            return;
        }

        $this->call([
            AcademicYearSeeder::class,
            UserSeeder::class,
            EmployeeSeeder::class,
            TeacherSeeder::class,
            SubjectSeeder::class,
            SchoolClassSeeder::class,
            StudentSeeder::class,
            GuardianSeeder::class,
            RfidCardSeeder::class,
            AttendanceSeeder::class,
            EmployeeAttendanceSeeder::class,
        ]);

        $this->printSummary();
    }

    /**
     * Print summary credentials and relation cheat-sheet to the console.
     */
    private function printSummary(): void
    {
        $this->command->table(
            ['Role', 'Username', 'Password', 'Profile'],
            [
                ['Admin', 'admin', 'password', '—'],
                ['Principal', 'principal', 'password', 'Dr. H. Mulyadi, M.Pd.'],
                ['Teacher', 'teacher1', 'password', 'Budi Santoso'],
                ['Teacher', 'teacher2', 'password', 'Siti Aminah'],
                ['Staff', 'staff1', 'password', 'Hendra Setiawan (Tata Usaha)'],
                ['Student', 'student1', 'password', 'Ahmad Fauzi (Kelas 5A)'],
                ['Student', 'student2', 'password', 'Ayu Lestari (Kelas 5A)'],
                ['Parent', 'parent1', 'password', 'Slamet Riyadi'],
                ['Parent', 'parent2', 'password', 'Dewi Lestari'],
            ],
        );

        $this->command->table(
            ['Relation', 'Who'],
            [
                ['Homeroom', 'teacher1 → Kelas 5A (2410001–2410004), teacher2 → Kelas 5B (2410005–2410008)'],
                ['Staff (Tendik)', 'staff1 → Hendra Setiawan (Tata Usaha); Agus Priyono (Satpam); Rina Wulandari (Kebersihan)'],
                ['RFID Cards', 'CARD-001 (student1), CARD-002 (student2), CARD-EMP-001 (teacher1), CARD-EMP-002 (staff1)'],
                ['parent1 (Slamet)', 'Ahmad Fauzi (5A) + Dimas Saputra (5B) — children across both classes'],
                ['parent2 (Dewi)', 'Ahmad Fauzi (5A) + Ayu Lestari (5A) — shares Ahmad with parent1 (dual-guardian)'],
                ['Siti Rahayu (no login)', 'Eka Putri (5B) — phone only, WhatsApp path'],
                ['Bambang Sutrisno (no login)', 'Fajar Nugroho (5B) — no phone, no user: unreachable, silent skip'],
            ],
        );
    }
}
