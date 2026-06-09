<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_reminder_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('booking_id')
                ->constrained('bookings')
                ->cascadeOnDelete();
            $table->foreignUuid('reminder_rule_id')
                ->constrained('professional_booking_reminder_rules')
                ->cascadeOnDelete();
            $table->timestamp('scheduled_for');
            $table->timestamp('sent_at')->nullable();
            $table->string('status')->default('pending');
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->unique(
                ['booking_id', 'reminder_rule_id'],
                'booking_reminder_deliveries_booking_rule_unique'
            );
            $table->index(
                ['status', 'scheduled_for'],
                'booking_reminder_deliveries_status_schedule_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_reminder_deliveries');
    }
};
