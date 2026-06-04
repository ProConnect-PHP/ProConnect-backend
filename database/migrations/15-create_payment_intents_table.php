<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_intents', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('booking_id')
                ->constrained('bookings')
                ->cascadeOnDelete();

            $table->foreignUuid('client_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignUuid('professional_id')
                ->constrained('professional_profiles')
                ->cascadeOnDelete();

            $table->string('provider')->default('simulator');
            $table->string('status')->default('pending');

            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('UYU');

            $table->string('provider_reference')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('succeeded_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->text('failure_reason')->nullable();

            $table->timestamps();

            $table->index(['booking_id']);
            $table->index(['client_id']);
            $table->index(['professional_id']);
            $table->index(['status']);
            $table->index(['provider', 'provider_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_intents');
    }
};
