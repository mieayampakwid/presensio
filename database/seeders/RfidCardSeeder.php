<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\RfidCard;
use App\Models\Student;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RfidCardSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ahmad = Student::where('full_name', 'Ahmad Fauzi')->first();
        $ayu = Student::where('full_name', 'Ayu Lestari')->first();
        $budiEmployee = Employee::where('employee_number', '198505052010011001')->first();
        $staffEmployee = Employee::where('employee_number', '199008152015021003')->first();

        $cards = [
            ['rfid_number' => 'CARD-001', 'student_id' => $ahmad?->id, 'employee_id' => null],
            ['rfid_number' => 'CARD-002', 'student_id' => $ayu?->id, 'employee_id' => null],
            ['rfid_number' => 'CARD-EMP-001', 'student_id' => null, 'employee_id' => $budiEmployee?->id],
            ['rfid_number' => 'CARD-EMP-002', 'student_id' => null, 'employee_id' => $staffEmployee?->id],
            ['rfid_number' => 'CARD-SPARE', 'student_id' => null, 'employee_id' => null],
        ];

        foreach ($cards as $data) {
            RfidCard::updateOrCreate(
                ['rfid_number' => $data['rfid_number']],
                [
                    'student_id' => $data['student_id'],
                    'employee_id' => $data['employee_id'],
                ]
            );
        }
    }
}
