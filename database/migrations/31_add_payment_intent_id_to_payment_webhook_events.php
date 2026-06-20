<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_webhook_events', function (Blueprint $table): void {
            $table->foreignUuid('payment_intent_id')
                ->nullable()
                ->after('resource_id')
                ->constrained('payment_intents')
                ->nullOnDelete();
            $table->index('payment_intent_id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_webhook_events', function (Blueprint $table): void {
            $table->dropForeign(['payment_intent_id']);
            $table->dropIndex(['payment_intent_id']);
            $table->dropColumn('payment_intent_id');
        });
    }
};
