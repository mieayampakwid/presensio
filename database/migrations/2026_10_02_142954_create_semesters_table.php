<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->unsignedTinyInteger('number'); // 1 = Ganjil, 2 = Genap
            $table->string('name', 50);
            $table->date('starts_at');
            $table->date('ends_at');
            $table->timestamps();

            $table->unique(['academic_year_id', 'number']);
            $table->index(['starts_at', 'ends_at']);
        });

        // Backfill both semesters for every existing academic year (spec 15 §Semesters: 1 Jan split)
        $years = DB::table('academic_years')->get();
        $now = Carbon::now();

        foreach ($years as $year) {
            $startYear = Carbon::parse($year->starts_at)->year;
            $splitYear = $startYear;
            $december31 = Carbon::create($splitYear, 12, 31)->toDateString();
            $january1 = Carbon::create($splitYear + 1, 1, 1)->toDateString();

            DB::table('semesters')->insert([
                [
                    'academic_year_id' => $year->id,
                    'number' => 1,
                    'name' => 'Semester Ganjil',
                    'starts_at' => $year->starts_at,
                    'ends_at' => $december31,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'academic_year_id' => $year->id,
                    'number' => 2,
                    'name' => 'Semester Genap',
                    'starts_at' => $january1,
                    'ends_at' => $year->ends_at,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('semesters');
    }
};
