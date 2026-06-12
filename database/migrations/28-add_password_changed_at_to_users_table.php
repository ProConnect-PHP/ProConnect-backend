<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Agregamos la columna de tipo timestamp, que permita nulos, justo después de la contraseña
            $table->timestamp('password_changed_at')->nullable()->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Si tiramos atrás la migración, removemos la columna
            $table->dropColumn('password_changed_at');
        });
    }
};
