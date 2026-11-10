<?php

use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Support\Carbon;

it('shows the current year balance and hides other years', function () {
    $this->travelTo(Carbon::parse('2026-11-10'));

    $type = LeaveType::create(['name' => 'Annual', 'default_days' => 30]);
    $user = User::factory()->create();

    LeaveBalance::create(['user_id' => $user->id, 'leave_type_id' => $type->id, 'year' => 2026, 'days_remaining' => 17]);
    LeaveBalance::create(['user_id' => $user->id, 'leave_type_id' => $type->id, 'year' => 2025, 'days_remaining' => 29]);

    $this->actingAs($user)
        ->get(route('balances.index'))
        ->assertOk()
        ->assertSee('Annual')
        ->assertSee('<td class="px-6 py-4">17</td>', false)
        ->assertDontSee('<td class="px-6 py-4">29</td>', false);
});
