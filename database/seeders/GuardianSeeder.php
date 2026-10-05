<?php

namespace Database\Seeders;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GuardianSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $parent1 = User::where('username', 'parent1')->first();
        $parent2 = User::where('username', 'parent2')->first();

        $slamet = Guardian::firstOrCreate(
            ['name' => 'Slamet Riyadi'],
            [
                'user_id' => $parent1?->id,
                'phone_number' => '+628111000001',
                'work' => 'Merchant',
                'address' => 'Jl. Kenanga 9, Jakarta',
            ]
        );

        $dewi = Guardian::firstOrCreate(
            ['name' => 'Dewi Lestari'],
            [
                'user_id' => $parent2?->id,
                'phone_number' => '+628111000003',
                'work' => 'Teacher',
                'address' => 'Jl. Kenanga 9, Jakarta',
            ]
        );

        $sitiRahayu = Guardian::firstOrCreate(
            ['name' => 'Siti Rahayu'],
            [
                'user_id' => null,
                'phone_number' => '+628111000002',
            ]
        );

        $bambang = Guardian::firstOrCreate(
            ['name' => 'Bambang Sutrisno'],
            [
                'user_id' => null,
                'phone_number' => '',
            ]
        );

        $attach = function (Guardian $guardian, array $studentNames): void {
            $studentIds = Student::whereIn('full_name', $studentNames)->pluck('id')->toArray();
            $guardian->students()->syncWithoutDetaching($studentIds);
        };

        $attach($slamet, ['Ahmad Fauzi', 'Dimas Saputra']);
        $attach($dewi, ['Ahmad Fauzi', 'Ayu Lestari']);
        $attach($sitiRahayu, ['Eka Putri']);
        $attach($bambang, ['Fajar Nugroho']);
    }
}
