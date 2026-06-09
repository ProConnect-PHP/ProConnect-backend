<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professional_booking_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('professional_id')
                ->unique()
                ->constrained('professional_profiles')
                ->cascadeOnDelete();
            $table->boolean('allow_client_cancellation')->default(true);
            $table->unsignedInteger('cancellation_cutoff_minutes')->default(120);
            $table->boolean('allow_client_rescheduling')->default(true);
            $table->unsignedInteger('rescheduling_cutoff_minutes')->default(120);
            $table->unsignedInteger('late_tolerance_minutes')->default(10);
            $table->boolean('reminders_enabled')->default(true);
            $table->text('cancellation_policy_text')->nullable();
            $table->text('rescheduling_policy_text')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_booking_policies');
    }
};
