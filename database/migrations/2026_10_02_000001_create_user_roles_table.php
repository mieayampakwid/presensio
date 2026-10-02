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
        Schema::create('user_roles', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 20)->index();
            $table->timestamp('created_at')->nullable();
            $table->primary(['user_id', 'role']);
        });

        self::backfill();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }

    /**
     * Backfill user roles from the legacy users.role column.
     */
    public static function backfill(): void
    {
        DB::table('user_roles')->insertUsing(
            ['user_id', 'role', 'created_at'],
            DB::table('users')->select('id', 'role', DB::raw('CURRENT_TIMESTAMP'))
        );
    }
};
