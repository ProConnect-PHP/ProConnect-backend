<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('client_package_id')
                ->constrained('client_packages')
                ->cascadeOnDelete();

            $table->foreignUuid('booking_id')
                ->unique()
                ->constrained('bookings')
                ->cascadeOnDelete();

            $table->foreignUuid('client_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignUuid('professional_id')
                ->constrained('professional_profiles')
                ->cascadeOnDelete();

            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('released_at')->nullable();

            $table->string('status')->default('reserved');
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['client_package_id']);
            $table->index(['client_id']);
            $table->index(['professional_id']);
            $table->index(['status']);
        });

        Schema::table('notification_logs', function (Blueprint $table) {
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

            $table->index(['client_package_id']);
            $table->index(['package_session_id']);
        });
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('package_session_id');
            $table->dropConstrainedForeignId('client_package_id');
        });

        Schema::dropIfExists('package_sessions');
    }
};
