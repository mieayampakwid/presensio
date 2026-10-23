<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->foreignId('guardian_deposit_account_id')->nullable()->constrained('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('guardian_deposit_account_id');
        });
    }
};
