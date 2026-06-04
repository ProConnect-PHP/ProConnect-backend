<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignUuid('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignUuid('booking_id')
                ->nullable()
                ->constrained('bookings')
                ->nullOnDelete();
            $table->foreignUuid('client_package_id')
                ->nullable()
                ->after('booking_id')
                ->constrained('client_packages')
                ->cascadeOnDelete();

            $table->foreignUuid('package_session_id')
                ->nullable()
                ->after('client_package_id')
                ->constrained('package_sessions')
                ->cascadeOnDelete();

            $table->string('channel');
            $table->string('type');
            $table->string('recipient');
            $table->string('status')->default('queued');
            $table->json('payload')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['client_package_id']);
            $table->index(['package_session_id']);
            $table->index(['user_id']);
            $table->index(['booking_id']);
            $table->index(['type']);
            $table->index(['status']);
            $table->unique(
                ['booking_id', 'user_id', 'channel', 'type'],
                'notification_logs_booking_user_channel_type_unique'
            );

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
