<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('
            ALTER TABLE bookings
            DROP CONSTRAINT IF EXISTS bookings_no_professional_overlapping_active_periods
        ');
    }
};
