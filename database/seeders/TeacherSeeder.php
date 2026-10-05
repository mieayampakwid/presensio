<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Teacher;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TeacherSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $teacherEmployeeNumbers = [
            '198505052010011001', // Budi Santoso
            '198703102011012002', // Siti Aminah
        ];

        foreach ($teacherEmployeeNumbers as $number) {
            $employee = Employee::where('employee_number', $number)->first();

            if ($employee !== null) {
                Teacher::firstOrCreate(['employee_id' => $employee->id]);
            }
        }
    }
}
