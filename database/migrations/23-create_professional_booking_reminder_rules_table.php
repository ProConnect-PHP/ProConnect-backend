<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professional_booking_reminder_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('professional_id')
                ->constrained('professional_profiles')
                ->cascadeOnDelete();
            $table->unsignedInteger('minutes_before_start');
            $table->boolean('send_email')->default(true);
            $table->boolean('send_database_notification')->default(true);
            $table->boolean('send_push')->default(false);
            $table->boolean('send_whatsapp')->default(false);
            $table->boolean('notify_client')->default(true);
            $table->boolean('notify_professional')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['professional_id', 'minutes_before_start'],
                'professional_reminder_rules_minutes_unique'
            );
            $table->index(
                ['professional_id', 'is_active'],
                'professional_reminder_rules_active_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_booking_reminder_rules');
    }
};
