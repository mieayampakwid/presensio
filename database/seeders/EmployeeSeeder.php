<?php

namespace Database\Seeders;

use App\Enums\EmploymentType;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $budiUser = User::where('username', 'teacher1')->first();
        $sitiUser = User::where('username', 'teacher2')->first();
        $staffUser = User::where('username', 'staff1')->first();

        $employees = [
            [
                'user_id' => $budiUser?->id,
                'name' => 'Budi Santoso',
                'employee_number' => '198505052010011001',
                'phone_number' => '+628111002001',
                'employment_type' => EmploymentType::Permanent,
                'position' => 'Guru Kelas',
                'working_days' => null,
                'is_active' => true,
            ],
            [
                'user_id' => $sitiUser?->id,
                'name' => 'Siti Aminah',
                'employee_number' => '198703102011012002',
                'phone_number' => '+628111002002',
                'employment_type' => EmploymentType::Permanent,
                'position' => 'Guru Kelas',
                'working_days' => null,
                'is_active' => true,
            ],
            [
                'user_id' => $staffUser?->id,
                'name' => 'Hendra Setiawan',
                'employee_number' => '199008152015021003',
                'phone_number' => '+628111003001',
                'employment_type' => EmploymentType::Contract,
                'position' => 'Tata Usaha',
                'working_days' => null,
                'is_active' => true,
            ],
            [
                'user_id' => null,
                'name' => 'Agus Priyono',
                'employee_number' => 'EMP-SEC-001',
                'phone_number' => '+628111003002',
                'employment_type' => EmploymentType::Honorary,
                'position' => 'Petugas Keamanan',
                'working_days' => [1, 2, 3, 4, 5],
                'is_active' => true,
            ],
            [
                'user_id' => null,
                'name' => 'Rina Wulandari',
                'employee_number' => 'EMP-CLN-001',
                'phone_number' => '+628111003003',
                'employment_type' => EmploymentType::Honorary,
                'position' => 'Kebersihan',
                'working_days' => [1, 2, 3, 4],
                'is_active' => true,
            ],
        ];

        foreach ($employees as $data) {
            Employee::firstOrCreate(
                ['employee_number' => $data['employee_number']],
                $data
            );
        }
    }
}
