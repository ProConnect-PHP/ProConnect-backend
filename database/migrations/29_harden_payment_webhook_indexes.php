<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_webhook_events', function (Blueprint $table): void {
            $table->unique(
                ['provider', 'idempotency_key'],
                'payment_webhook_provider_idempotency_unique'
            );
            $table->index(
                ['status', 'created_at'],
                'payment_webhook_status_created_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('payment_webhook_events', function (Blueprint $table): void {
            $table->dropUnique('payment_webhook_provider_idempotency_unique');
            $table->dropIndex('payment_webhook_status_created_index');
        });
    }
};
