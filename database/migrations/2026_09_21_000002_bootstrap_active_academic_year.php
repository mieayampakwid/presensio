<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Every fresh install starts inside the bootstrap year so classes and
     * enrollments always have an active year to belong to (spec 07). The
     * seeder and factories resolve this row — they never invent one.
     */
    public function up(): void
    {
        DB::table('academic_years')->insert([
            'name' => '2026/2027',
            'starts_at' => '2026-07-01',
            'ends_at' => '2027-06-30',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('academic_years')->where('name', '2026/2027')->delete();
    }
};
