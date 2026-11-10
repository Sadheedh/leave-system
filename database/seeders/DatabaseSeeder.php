<?php

namespace Database\Seeders;

use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Factory create() is used because role and manager_id are not mass assignable;
        // factory states and explicit attributes are still saved.
        // Users are created in a fixed order so ids come out as 1..9.
        $admin = User::factory()->admin()->create([
            'name' => 'Ahmed Admin',
            'email' => 'admin@example.com',
        ]);

        // Managers report to the admin, so manager_id uses the admin's real id.
        $mariyam = User::factory()->manager()->create([
            'name' => 'Mariyam Manager',
            'email' => 'manager1@example.com',
            'manager_id' => $admin->id,
        ]);

        $ibrahim = User::factory()->manager()->create([
            'name' => 'Ibrahim Manager',
            'email' => 'manager2@example.com',
            'manager_id' => $admin->id,
        ]);

        // Staff One to Three report to Mariyam; Four to Six report to Ibrahim.
        $staffNames = ['One', 'Two', 'Three', 'Four', 'Five', 'Six'];

        foreach ($staffNames as $index => $word) {
            User::factory()->create([
                'name' => 'Staff '.$word,
                'email' => 'staff'.($index + 1).'@example.com',
                // The first three go to Mariyam, the rest to Ibrahim.
                'manager_id' => $index < 3 ? $mariyam->id : $ibrahim->id,
            ]);
        }

        $leaveTypes = [
            LeaveType::create(['name' => 'Annual', 'default_days' => 30]),
            LeaveType::create(['name' => 'Sick', 'default_days' => 30]),
            LeaveType::create(['name' => 'Family Responsibility', 'default_days' => 10]),
        ];

        // Every user gets one balance per leave type for this year: 9 users x 3 types = 27 rows.
        foreach (User::all() as $user) {
            foreach ($leaveTypes as $leaveType) {
                LeaveBalance::create([
                    'user_id' => $user->id,
                    'leave_type_id' => $leaveType->id,
                    'year' => now()->year,
                    // Start each balance at the full yearly allowance.
                    'days_remaining' => $leaveType->default_days,
                ]);
            }
        }
    }
}
