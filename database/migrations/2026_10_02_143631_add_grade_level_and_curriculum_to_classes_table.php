<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->unsignedTinyInteger('grade_level')->default(0)->after('name');
            $table->string('curriculum', 30)->default('merdeka')->after('grade_level');

            $table->index(['academic_year_id', 'grade_level']);
        });

        // Backfill grade_level via leading digits of name (preg_match('/^\d{1,2}/')) kept only when 1..12
        $classes = DB::table('classes')->get();
        foreach ($classes as $class) {
            $level = 0;
            if (preg_match('/^(\d{1,2})/', $class->name, $matches)) {
                $val = (int) $matches[1];
                if ($val >= 1 && $val <= 12) {
                    $level = $val;
                }
            }

            DB::table('classes')
                ->where('id', $class->id)
                ->update([
                    'grade_level' => $level,
                    'curriculum' => 'merdeka',
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropIndex(['academic_year_id', 'grade_level']);
            $table->dropColumn(['grade_level', 'curriculum']);
        });
    }
};
