<?php

use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-10'));

    $this->leaveType = LeaveType::create(['name' => 'Annual', 'default_days' => 30]);
    $this->user = User::factory()->create();
    $this->balance = LeaveBalance::create([
        'user_id' => $this->user->id,
        'leave_type_id' => $this->leaveType->id,
        'year' => 2026,
        'days_remaining' => 30,
    ]);
});

/**
 * Build a valid form payload, optionally overriding individual fields.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validLeavePayload(int $leaveTypeId, array $overrides = []): array
{
    return array_merge([
        'leave_type_id' => $leaveTypeId,
        'start_date' => '2026-11-15',
        'end_date' => '2026-11-19',
        'reason' => 'Family trip',
    ], $overrides);
}

it('redirects guests to the login page', function () {
    $this->get(route('leave-requests.create'))->assertRedirect(route('login'));
});

it('stores a valid request with working days and a pending event', function () {
    $response = $this->actingAs($this->user)
        ->post(route('leave-requests.store'), validLeavePayload($this->leaveType->id));

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('leave-requests.index'));

    $this->assertDatabaseHas('leave_requests', [
        'user_id' => $this->user->id,
        'leave_type_id' => $this->leaveType->id,
        'days' => 5,
        'status' => 'pending',
    ]);
    $this->assertDatabaseHas('leave_request_events', [
        'user_id' => $this->user->id,
        'from_status' => null,
        'to_status' => 'pending',
    ]);
});

it('rejects a request that exceeds the remaining balance', function () {
    $this->balance->update(['days_remaining' => 3]);

    $this->actingAs($this->user)
        ->post(route('leave-requests.store'), validLeavePayload($this->leaveType->id))
        ->assertSessionHasErrors('leave_type_id');

    $this->assertDatabaseCount('leave_requests', 0);
});

it('rejects a range that contains only weekend days', function () {
    $this->actingAs($this->user)
        ->post(route('leave-requests.store'), validLeavePayload($this->leaveType->id, [
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-21',
        ]))
        ->assertSessionHasErrors('end_date');

    $this->assertDatabaseCount('leave_requests', 0);
});

it('rejects a leave type the user has no balance for', function () {
    $sick = LeaveType::create(['name' => 'Sick', 'default_days' => 10]);

    $this->actingAs($this->user)
        ->post(route('leave-requests.store'), validLeavePayload($sick->id))
        ->assertSessionHasErrors('leave_type_id');

    $this->assertDatabaseCount('leave_requests', 0);
});

it('validates the basic rules', function (array $overrides, string $field) {
    $this->actingAs($this->user)
        ->post(route('leave-requests.store'), validLeavePayload($this->leaveType->id, $overrides))
        ->assertSessionHasErrors($field);

    $this->assertDatabaseCount('leave_requests', 0);
})->with([
    'end before start' => [['start_date' => '2026-11-19', 'end_date' => '2026-11-15'], 'end_date'],
    'start in the past' => [['start_date' => '2026-11-01'], 'start_date'],
    'missing reason' => [['reason' => ''], 'reason'],
    'unknown leave type' => [['leave_type_id' => 999], 'leave_type_id'],
]);

it('shows only the logged-in user their own requests', function () {
    $other = User::factory()->create();

    $this->user->leaveRequests()->create([
        'leave_type_id' => $this->leaveType->id,
        'start_date' => '2026-11-15',
        'end_date' => '2026-11-16',
        'days' => 2,
        'reason' => 'My own reason',
    ]);
    $other->leaveRequests()->create([
        'leave_type_id' => $this->leaveType->id,
        'start_date' => '2026-11-15',
        'end_date' => '2026-11-16',
        'days' => 2,
        'reason' => 'Someone else reason',
    ]);

    $this->actingAs($this->user)
        ->get(route('leave-requests.index'))
        ->assertOk()
        ->assertSee('My own reason')
        ->assertDontSee('Someone else reason');
});
