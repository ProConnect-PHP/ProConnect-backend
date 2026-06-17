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
            $table->index(['professional_id', 'starts_at', 'ends_at'], 'bookings_professional_agenda_range_idx');
            $table->index(['professional_id', 'status', 'starts_at'], 'bookings_professional_status_starts_idx');

            if (DB::getDriverName() !== 'pgsql') {
                return;
            }
            DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

            DB::statement("
                ALTER TABLE bookings
                ADD CONSTRAINT bookings_no_professional_overlapping_active_periods
                EXCLUDE USING gist (
                    professional_id WITH =,
                    tsrange(starts_at, ends_at, '[)') WITH &&
                )
                WHERE (
                    status IN (
                        'pending',
                        'confirmed',
                        'paid',
                        'in_progress'
                        )
                    )
            ");

        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('
            ALTER TABLE bookings
            DROP CONSTRAINT IF EXISTS bookings_no_professional_overlapping_active_periods
        ');
        Schema::dropIfExists('bookings');
    }
};
