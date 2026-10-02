<?php

use App\Enums\UserRole;
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
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 30)->nullable()->after('password');
        });

        $enumOrder = array_flip(array_map(fn (UserRole $case) => $case->value, UserRole::cases()));

        DB::table('users')->orderBy('id')->each(function (object $user) use ($enumOrder): void {
            $grants = DB::table('user_roles')
                ->where('user_id', $user->id)
                ->pluck('role')
                ->all();

            usort($grants, fn (string $a, string $b) => ($enumOrder[$a] ?? 99) <=> ($enumOrder[$b] ?? 99));

            if (! empty($grants)) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['role' => $grants[0]]);
            }
        });
    }
};
