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
            // The role decides what a user may do; new users default to the least powerful one.
            $table->enum('role', ['staff', 'manager', 'admin'])->default('staff')->after('email');

            // Points at another row in the same users table (the person's manager).
            // nullOnDelete: if a manager is deleted, their staff are kept and just lose a manager.
            $table->foreignId('manager_id')->nullable()->after('role')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Must drop the foreign key and the column together, before dropping role.
            $table->dropConstrainedForeignId('manager_id');
            $table->dropColumn('role');
        });
    }
};
