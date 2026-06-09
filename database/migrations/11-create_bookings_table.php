<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_id')
                ->constrained('services')
                ->cascadeOnDelete();

            $table->foreignUuid('professional_id')
                ->constrained('professional_profiles')
                ->cascadeOnDelete();

            $table->foreignUuid('client_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            $table->string('status')->default('pending');

            $table->string('modality');

            $table->decimal('price_snapshot', 10, 2);
            $table->unsignedInteger('duration_minutes_snapshot');

            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('no_show_at')->nullable();

            $table->text('cancellation_reason')->nullable();
            $table->text('reschedule_reason')->nullable();


            $table->timestamps();
            $table->softDeletes();
            $table->index(['service_id', 'starts_at', 'ends_at']);
            $table->index(['professional_id', 'starts_at']);
            $table->index(['client_id', 'starts_at']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
