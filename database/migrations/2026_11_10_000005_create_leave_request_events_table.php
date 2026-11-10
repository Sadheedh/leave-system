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
        Schema::create('leave_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained()->cascadeOnDelete();
            // The user who caused this status change.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Null for the very first event, because a new request has no previous status.
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('note')->nullable();
            // Audit rows are never edited, so only created_at exists (no updated_at).
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_request_events');
    }
};
