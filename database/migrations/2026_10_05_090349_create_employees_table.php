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
        Schema::create('employees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('employee_number', 50)->nullable()->unique();
            $table->string('phone_number', 30)->nullable();
            $table->string('employment_type', 20);
            $table->string('position', 100)->nullable();
            $table->json('working_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('teachers', function (Blueprint $table): void {
            $table->foreignId('employee_id')->nullable()->constrained('employees')->cascadeOnDelete();
        });

        self::migrateData();

        Schema::disableForeignKeyConstraints();

        Schema::table('teachers', function (Blueprint $table): void {
            $table->unsignedBigInteger('employee_id')->nullable(false)->change();
            $table->unique('employee_id');
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id']);
            $table->dropColumn(['name', 'teacher_number', 'phone_number', 'user_id']);
        });

        Schema::enableForeignKeyConstraints();

        Schema::table('settings', function (Blueprint $table): void {
            $table->time('staff_start_time')->default('07:00:00');
            $table->time('staff_end_time')->default('14:00:00');
            $table->time('staff_absent_sweep_time')->default('09:00:00');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table): void {
            $table->dropColumn([
                'staff_start_time',
                'staff_end_time',
                'staff_absent_sweep_time',
            ]);
        });

        Schema::table('teachers', function (Blueprint $table): void {
            $table->string('name')->nullable();
            $table->string('teacher_number')->nullable();
            $table->string('phone_number')->nullable();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
        });

        self::rollbackData();

        Schema::disableForeignKeyConstraints();

        Schema::table('teachers', function (Blueprint $table): void {
            $table->string('name')->nullable(false)->change();
            $table->dropForeign(['employee_id']);
            $table->dropUnique(['employee_id']);
            $table->dropColumn('employee_id');
        });

        Schema::enableForeignKeyConstraints();

        Schema::dropIfExists('employees');
    }

    /**
     * Move teacher profiles to employee records.
     */
    public static function migrateData(): void
    {
        $teachers = DB::table('teachers')->get();

        foreach ($teachers as $teacher) {
            $employeeId = DB::table('employees')->insertGetId([
                'user_id' => $teacher->user_id,
                'name' => $teacher->name,
                'employee_number' => $teacher->teacher_number,
                'phone_number' => $teacher->phone_number,
                'employment_type' => 'permanent',
                'position' => 'Guru',
                'working_days' => null,
                'is_active' => true,
                'created_at' => $teacher->created_at ?? now(),
                'updated_at' => $teacher->updated_at ?? now(),
            ]);

            DB::table('teachers')->where('id', $teacher->id)->update([
                'employee_id' => $employeeId,
            ]);
        }
    }

    /**
     * Restore teacher profile fields from employee records.
     */
    public static function rollbackData(): void
    {
        $teachers = DB::table('teachers')->get();

        foreach ($teachers as $teacher) {
            if ($teacher->employee_id === null) {
                continue;
            }

            $employee = DB::table('employees')->where('id', $teacher->employee_id)->first();
            if ($employee !== null) {
                DB::table('teachers')->where('id', $teacher->id)->update([
                    'name' => $employee->name,
                    'teacher_number' => $employee->employee_number,
                    'phone_number' => $employee->phone_number,
                    'user_id' => $employee->user_id,
                ]);
            }
        }
    }
};
