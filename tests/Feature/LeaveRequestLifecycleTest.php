<?php

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-10'));

    $this->leaveType = LeaveType::create(['name' => 'Annual', 'default_days' => 30]);

    // manager_id is not fillable, but factory states/attributes bypass mass assignment.
    $this->admin = User::factory()->admin()->create();
    $this->managerA = User::factory()->manager()->create(['manager_id' => $this->admin->id]);
    $this->managerB = User::factory()->manager()->create(['manager_id' => $this->admin->id]);
    $this->staffA = User::factory()->create(['name' => 'Alice Staff', 'manager_id' => $this->managerA->id]);
    $this->staffB = User::factory()->create(['name' => 'Bob Staff', 'manager_id' => $this->managerB->id]);
});

/**
 * Create a 2026 balance row for a user.
 */
function lifecycleBalanceFor(User $user, int $leaveTypeId, int $daysRemaining = 30): LeaveBalance
{
    return LeaveBalance::create([
        'user_id' => $user->id,
        'leave_type_id' => $leaveTypeId,
        'year' => 2026,
        'days_remaining' => $daysRemaining,
    ]);
}

/**
 * Create a request (starting 2026-11-15) with its first history event, like the store action does.
 * status is not fillable, so it is assigned directly and saved.
 */
function lifecycleRequestFor(User $user, int $leaveTypeId, int $days = 5, string $status = 'pending', string $startDate = '2026-11-15'): LeaveRequest
{
    $leaveRequest = $user->leaveRequests()->create([
        'leave_type_id' => $leaveTypeId,
        'start_date' => $startDate,
        'end_date' => '2026-11-19',
        'days' => $days,
        'reason' => 'Family trip',
    ]);

    $leaveRequest->events()->create([
        'user_id' => $user->id,
        'from_status' => null,
        'to_status' => 'pending',
    ]);

    if ($status !== 'pending') {
        $leaveRequest->status = $status;
        $leaveRequest->save();
    }

    return $leaveRequest;
}

it('deducts the days from the balance when a request is approved', function () {
    $balance = lifecycleBalanceFor($this->staffA, $this->leaveType->id);
    $leaveRequest = lifecycleRequestFor($this->staffA, $this->leaveType->id, 5);

    $this->actingAs($this->managerA)
        ->post(route('team-requests.approve', $leaveRequest))
        ->assertRedirect(route('team-requests.index'));

    expect($balance->fresh()->days_remaining)->toBe(25)
        ->and($leaveRequest->fresh()->status)->toBe('approved');
});

it('leaves the balance unchanged when a request is rejected', function () {
    $balance = lifecycleBalanceFor($this->staffA, $this->leaveType->id);
    $leaveRequest = lifecycleRequestFor($this->staffA, $this->leaveType->id, 5);

    $this->actingAs($this->managerA)
        ->post(route('team-requests.reject', $leaveRequest))
        ->assertRedirect(route('team-requests.index'));

    expect($balance->fresh()->days_remaining)->toBe(30)
        ->and($leaveRequest->fresh()->status)->toBe('rejected');
});

it('restores the days when an approved request is cancelled', function () {
    // 25 = the balance after a 5-day approval already happened.
    $balance = lifecycleBalanceFor($this->staffA, $this->leaveType->id, 25);
    $leaveRequest = lifecycleRequestFor($this->staffA, $this->leaveType->id, 5, 'approved');

    $this->actingAs($this->staffA)
        ->post(route('leave-requests.cancel', $leaveRequest))
        ->assertRedirect(route('leave-requests.index'));

    expect($balance->fresh()->days_remaining)->toBe(30)
        ->and($leaveRequest->fresh()->status)->toBe('cancelled');
});

it('leaves the balance unchanged when a pending request is cancelled', function () {
    $balance = lifecycleBalanceFor($this->staffA, $this->leaveType->id);
    $leaveRequest = lifecycleRequestFor($this->staffA, $this->leaveType->id, 5);

    $this->actingAs($this->staffA)
        ->post(route('leave-requests.cancel', $leaveRequest))
        ->assertRedirect(route('leave-requests.index'));

    expect($balance->fresh()->days_remaining)->toBe(30)
        ->and($leaveRequest->fresh()->status)->toBe('cancelled');

    $event = $leaveRequest->events()->orderByDesc('id')->first();
    expect($event->from_status)->toBe('pending')
        ->and($event->to_status)->toBe('cancelled')
        ->and($event->user_id)->toBe($this->staffA->id);
});

it('runs the full lifecycle: submit, approve, cancel', function () {
    $balance = lifecycleBalanceFor($this->staffA, $this->leaveType->id);

    // 2026-11-15 (Sun) to 2026-11-19 (Thu) is 5 working days: WorkingDayCalculator skips only Fri and Sat.
    $this->actingAs($this->staffA)
        ->post(route('leave-requests.store'), [
            'leave_type_id' => $this->leaveType->id,
            'start_date' => '2026-11-15',
            'end_date' => '2026-11-19',
            'reason' => 'Family trip',
        ])
        ->assertSessionHasNoErrors();

    $leaveRequest = LeaveRequest::firstOrFail();
    expect($leaveRequest->days)->toBe(5);

    $this->actingAs($this->managerA)->post(route('team-requests.approve', $leaveRequest));
    expect($balance->fresh()->days_remaining)->toBe(25);

    $this->actingAs($this->staffA)->post(route('leave-requests.cancel', $leaveRequest));
    expect($balance->fresh()->days_remaining)->toBe(30)
        ->and($leaveRequest->fresh()->status)->toBe('cancelled');

    $events = $leaveRequest->events()->orderBy('id')->get();
    expect($events)->toHaveCount(3)
        ->and($events[0]->from_status)->toBeNull()
        ->and($events[0]->to_status)->toBe('pending')
        ->and($events[1]->from_status)->toBe('pending')
        ->and($events[1]->to_status)->toBe('approved')
        ->and($events[2]->from_status)->toBe('approved')
        ->and($events[2]->to_status)->toBe('cancelled');
});

it('blocks approval when the balance is lower than the days requested', function () {
    $balance = lifecycleBalanceFor($this->staffA, $this->leaveType->id, 3);
    $leaveRequest = lifecycleRequestFor($this->staffA, $this->leaveType->id, 5);

    $this->actingAs($this->managerA)
        ->post(route('team-requests.approve', $leaveRequest))
        ->assertRedirect(route('team-requests.index'))
        ->assertSessionHas('error');

    expect($leaveRequest->fresh()->status)->toBe('pending')
        ->and($balance->fresh()->days_remaining)->toBe(3)
        ->and($leaveRequest->events()->count())->toBe(1); // only the original null -> pending event
});

it('blocks approval when no balance row exists', function () {
    $leaveRequest = lifecycleRequestFor($this->staffA, $this->leaveType->id, 5);

    $this->actingAs($this->managerA)
        ->post(route('team-requests.approve', $leaveRequest))
        ->assertRedirect(route('team-requests.index'))
        ->assertSessionHas('error');

    expect($leaveRequest->fresh()->status)->toBe('pending')
        ->and($leaveRequest->events()->count())->toBe(1);
});

it('forbids cancelling someone else request', function () {
    $balance = lifecycleBalanceFor($this->staffA, $this->leaveType->id, 25);
    $leaveRequest = lifecycleRequestFor($this->staffA, $this->leaveType->id, 5, 'approved');

    $this->actingAs($this->staffB)
        ->post(route('leave-requests.cancel', $leaveRequest))
        ->assertForbidden();

    expect($leaveRequest->fresh()->status)->toBe('approved')
        ->and($balance->fresh()->days_remaining)->toBe(25)
        ->and($leaveRequest->events()->count())->toBe(1);
});

it('forbids cancelling a request that is already rejected or cancelled', function (string $status) {
    $balance = lifecycleBalanceFor($this->staffA, $this->leaveType->id);
    $leaveRequest = lifecycleRequestFor($this->staffA, $this->leaveType->id, 5, $status);

    $this->actingAs($this->staffA)
        ->post(route('leave-requests.cancel', $leaveRequest))
        ->assertForbidden();

    expect($leaveRequest->fresh()->status)->toBe($status)
        ->and($balance->fresh()->days_remaining)->toBe(30);
})->with(['rejected', 'cancelled']);

it('shows the detail page with its history to the owner', function () {
    $leaveRequest = lifecycleRequestFor($this->staffA, $this->leaveType->id);

    $this->actingAs($this->staffA)
        ->get(route('leave-requests.show', $leaveRequest))
        ->assertOk()
        ->assertSee('Alice Staff')
        ->assertSee('History')
        ->assertSee('created -> pending', false); // false = do not escape, the text contains ">"
});

it('shows the detail page to the owner manager', function () {
    $leaveRequest = lifecycleRequestFor($this->staffA, $this->leaveType->id);

    $this->actingAs($this->managerA)
        ->get(route('leave-requests.show', $leaveRequest))
        ->assertOk()
        ->assertSee('Alice Staff');
});

it('forbids everyone else from the detail page', function () {
    $leaveRequest = lifecycleRequestFor($this->staffA, $this->leaveType->id);

    // Another staff member, another manager, and an admin who is not Alice's manager.
    foreach ([$this->staffB, $this->managerB, $this->admin] as $user) {
        $this->actingAs($user)
            ->get(route('leave-requests.show', $leaveRequest))
            ->assertForbidden();
    }
});

it('redirects guests from the detail page to login', function () {
    $leaveRequest = lifecycleRequestFor($this->staffA, $this->leaveType->id);

    $this->get(route('leave-requests.show', $leaveRequest))->assertRedirect(route('login'));
});

it('does not deduct twice when a request was already approved before the click arrives', function () {
    // Simulates the stale page: the manager loaded the request as pending, but it was approved meanwhile.
    $balance = lifecycleBalanceFor($this->staffA, $this->leaveType->id, 25);
    $leaveRequest = lifecycleRequestFor($this->staffA, $this->leaveType->id, 5);
    $leaveRequest->status = 'approved';
    $leaveRequest->save();

    $this->actingAs($this->managerA)
        ->post(route('team-requests.approve', $leaveRequest))
        ->assertForbidden();

    expect($balance->fresh()->days_remaining)->toBe(25)
        ->and($leaveRequest->events()->count())->toBe(1);
});

it('forbids cancelling an approved request once its start date is today or past', function (string $startDate) {
    // "Today" is 2026-11-10 (see travelTo in beforeEach).
    $balance = lifecycleBalanceFor($this->staffA, $this->leaveType->id, 25);
    $leaveRequest = lifecycleRequestFor($this->staffA, $this->leaveType->id, 5, 'approved', $startDate);

    $this->actingAs($this->staffA)
        ->post(route('leave-requests.cancel', $leaveRequest))
        ->assertForbidden();

    expect($leaveRequest->fresh()->status)->toBe('approved')
        ->and($balance->fresh()->days_remaining)->toBe(25)
        ->and($leaveRequest->events()->count())->toBe(1);
})->with(['starts today' => '2026-11-10', 'started yesterday' => '2026-11-09']);

it('allows cancelling an approved request that starts tomorrow', function () {
    $balance = lifecycleBalanceFor($this->staffA, $this->leaveType->id, 25);
    $leaveRequest = lifecycleRequestFor($this->staffA, $this->leaveType->id, 5, 'approved', '2026-11-11');

    $this->actingAs($this->staffA)
        ->post(route('leave-requests.cancel', $leaveRequest))
        ->assertRedirect(route('leave-requests.index'));

    expect($leaveRequest->fresh()->status)->toBe('cancelled')
        ->and($balance->fresh()->days_remaining)->toBe(30);
});

it('still allows cancelling a pending request whose start date has passed, without touching the balance', function () {
    $balance = lifecycleBalanceFor($this->staffA, $this->leaveType->id);
    $leaveRequest = lifecycleRequestFor($this->staffA, $this->leaveType->id, 5, 'pending', '2026-11-09');

    $this->actingAs($this->staffA)
        ->post(route('leave-requests.cancel', $leaveRequest))
        ->assertRedirect(route('leave-requests.index'));

    expect($leaveRequest->fresh()->status)->toBe('cancelled')
        ->and($balance->fresh()->days_remaining)->toBe(30);
});
