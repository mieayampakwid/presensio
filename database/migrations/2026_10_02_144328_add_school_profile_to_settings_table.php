<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('school_name', 255)->default('');
            $table->string('npsn', 20)->nullable();
            $table->text('school_address')->nullable();
            $table->string('school_phone', 30)->nullable();
            $table->string('school_email', 255)->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->string('principal_name', 255)->nullable();
            $table->string('principal_nip', 30)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->string('bank_account_holder', 255)->nullable();
            $table->string('default_curriculum', 30)->default('merdeka');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'school_name',
                'npsn',
                'school_address',
                'school_phone',
                'school_email',
                'logo_path',
                'principal_name',
                'principal_nip',
                'bank_name',
                'bank_account_number',
                'bank_account_holder',
                'default_curriculum',
            ]);
        });
    }
};
