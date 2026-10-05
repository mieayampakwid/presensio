<?php

namespace Database\Seeders;

use App\Enums\SubjectGroup;
use App\Models\Subject;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $subjects = [
            [
                'code' => 'MAT',
                'name' => 'Matematika',
                'group' => SubjectGroup::General,
                'sort_order' => 1,
            ],
            [
                'code' => 'BIN',
                'name' => 'Bahasa Indonesia',
                'group' => SubjectGroup::General,
                'sort_order' => 2,
            ],
            [
                'code' => 'IPA',
                'name' => 'Ilmu Pengetahuan Alam',
                'group' => SubjectGroup::General,
                'sort_order' => 3,
            ],
            [
                'code' => 'IPS',
                'name' => 'Ilmu Pengetahuan Sosial',
                'group' => SubjectGroup::General,
                'sort_order' => 4,
            ],
            [
                'code' => 'BIG',
                'name' => 'Bahasa Inggris',
                'group' => SubjectGroup::General,
                'sort_order' => 5,
            ],
            [
                'code' => 'PAI',
                'name' => 'Pendidikan Agama Islam',
                'group' => SubjectGroup::General,
                'sort_order' => 6,
            ],
            [
                'code' => 'PJOK',
                'name' => 'Pendidikan Jasmani, Olahraga, dan Kesehatan',
                'group' => SubjectGroup::General,
                'sort_order' => 7,
            ],
            [
                'code' => 'SBK',
                'name' => 'Seni Budaya dan Prakarya',
                'group' => SubjectGroup::General,
                'sort_order' => 8,
            ],
            [
                'code' => 'BDJ',
                'name' => 'Bahasa Daerah',
                'group' => SubjectGroup::LocalContent,
                'sort_order' => 9,
            ],
        ];

        foreach ($subjects as $data) {
            Subject::firstOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'group' => $data['group'],
                    'sort_order' => $data['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }
}
