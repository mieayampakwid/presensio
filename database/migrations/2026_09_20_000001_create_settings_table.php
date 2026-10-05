<?php

use App\Enums\NotificationType;
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
        $catalogDefaults = [];
        foreach (NotificationType::cases() as $type) {
            $catalogDefaults[$type->value] = [
                'whatsapp' => $type->defaultWhatsapp(),
                'email' => $type->defaultEmail(),
            ];
        }

        Schema::create('settings', function (Blueprint $table) use ($catalogDefaults) {
            $table->id();
            // Single-row table (id = 1). All times are evaluated in the
            // school timezone — the app itself runs UTC (spec 03 §Decisions).
            $table->string('school_timezone')->default('Asia/Jakarta');
            $table->time('school_start_time')->default('07:30:00');
            $table->boolean('require_checkout')->default(false);
            $table->time('auto_absent_cron_time')->default('15:30:00');
            $table->unsignedSmallInteger('scan_debounce_minutes')->default(1);
            $table->unsignedSmallInteger('scan_drift_tolerance_minutes')->default(2);
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
            $table->json('school_operational_days')->default(json_encode([1, 2, 3, 4, 5]));
            $table->decimal('default_passing_threshold', 5, 2)->default(75.00);
            $table->json('notification_channels')->default(json_encode($catalogDefaults));
            $table->unsignedInteger('whatsapp_daily_quota')->nullable()->default(500);
            $table->time('quiet_hours_start')->default('21:00:00');
            $table->time('quiet_hours_end')->default('06:00:00');
            $table->unsignedTinyInteger('bill_reminder_days_before')->default(3);
            $table->time('staff_start_time')->default('07:00:00');
            $table->time('staff_end_time')->default('14:00:00');
            $table->time('staff_absent_sweep_time')->default('09:00:00');
            $table->timestamps();
        });

        DB::table('settings')->insert([
            'id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
