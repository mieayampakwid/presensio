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
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(7);
            $table->foreignId('prepaid_revenue_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('bad_debt_expense_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('net_assets_closing_account_id')->nullable()->constrained('accounts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropForeign(['prepaid_revenue_account_id']);
            $table->dropForeign(['bad_debt_expense_account_id']);
            $table->dropForeign(['net_assets_closing_account_id']);
            $table->dropColumn([
                'fiscal_year_start_month',
                'prepaid_revenue_account_id',
                'bad_debt_expense_account_id',
                'net_assets_closing_account_id',
            ]);
        });
    }
};
