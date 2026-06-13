<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['provider', 'provider_payment_id']);
            $table->unique(
                ['provider', 'provider_payment_id'],
                'payments_provider_payment_id_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique('payments_provider_payment_id_unique');
            $table->index(['provider', 'provider_payment_id']);
        });
    }
};
