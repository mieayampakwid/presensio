<?php

use App\Enums\NotificationType;
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
        $catalogDefaults = [];
        foreach (NotificationType::cases() as $type) {
            $catalogDefaults[$type->value] = [
                'whatsapp' => $type->defaultWhatsapp(),
                'email' => $type->defaultEmail(),
            ];
        }

        Schema::table('settings', function (Blueprint $table) use ($catalogDefaults) {
            $table->json('notification_channels')->default(json_encode($catalogDefaults));
            $table->unsignedInteger('whatsapp_daily_quota')->nullable()->default(500);
            $table->time('quiet_hours_start')->default('21:00:00');
            $table->time('quiet_hours_end')->default('06:00:00');
            $table->unsignedTinyInteger('bill_reminder_days_before')->default(3);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'notification_channels',
                'whatsapp_daily_quota',
                'quiet_hours_start',
                'quiet_hours_end',
                'bill_reminder_days_before',
            ]);
        });
    }
};
