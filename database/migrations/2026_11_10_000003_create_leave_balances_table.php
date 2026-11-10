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
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            // If a user is deleted, their balances are meaningless, so delete them too.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Refuse to delete a leave type that balances still use (protects history).
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('days_remaining');
            $table->timestamps();

            // One balance row per user, leave type and year; the database enforces this.
            $table->unique(['user_id', 'leave_type_id', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
    }
};
