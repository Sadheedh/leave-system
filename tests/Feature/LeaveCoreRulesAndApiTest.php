<?php

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-10'));

    $this->leaveType = LeaveType::create(['name' => 'Annual', 'default_days' => 30]);

    $this->admin = User::factory()->admin()->create();
    $this->managerA = User::factory()->manager()->create(['manager_id' => $this->admin->id]);
    $this->managerB = User::factory()->manager()->create(['manager_id' => $this->admin->id]);
    $this->staffA = User::factory()->create(['name' => 'Alice Staff', 'manager_id' => $this->managerA->id]);
    $this->staffB = User::factory()->create(['name' => 'Bob Staff', 'manager_id' => $this->managerB->id]);
});

/**
 * Create a balance row for a user, defaulting to 30 days in 2026.
 */
function coreBalanceFor(User $user, int $leaveTypeId, int $daysRemaining = 30, int $year = 2026): LeaveBalance
{
    return LeaveBalance::create([
        'user_id' => $user->id,
        'leave_type_id' => $leaveTypeId,
        'year' => $year,
        'days_remaining' => $daysRemaining,
    ]);
}

/**
 * Create a request (2026-11-15 to 2026-11-19) with its first history event, like the store action does.
 * status is not fillable, so it is assigned directly and saved.
 */
function coreRequestFor(User $user, int $leaveTypeId, string $status = 'pending', int $days = 5): LeaveRequest
{
    $leaveRequest = $user->leaveRequests()->create([
        'leave_type_id' => $leaveTypeId,
        'start_date' => '2026-11-15',
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

it('lets staff submit a valid leave request', function () {
    coreBalanceFor($this->staffA, $this->leaveType->id);

    $this->actingAs($this->staffA)
        ->post(route('leave-requests.store'), [
            'leave_type_id' => $this->leaveType->id,
            'start_date' => '2026-11-15',
            'end_date' => '2026-11-19',
            'reason' => 'Family trip',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('leave-requests.index'))
        ->assertSessionHas('status', 'Leave request submitted.');

    $this->assertDatabaseHas('leave_requests', [
        'user_id' => $this->staffA->id,
        'leave_type_id' => $this->leaveType->id,
        'days' => 5,
        'status' => 'pending',
    ]);

    $this->assertDatabaseHas('leave_request_events', [
        'user_id' => $this->staffA->id,
        'from_status' => null,
        'to_status' => 'pending',
    ]);
});

it('lets a manager approve a request from their own team', function () {
    coreBalanceFor($this->staffA, $this->leaveType->id);
    $leaveRequest = coreRequestFor($this->staffA, $this->leaveType->id);

    $this->actingAs($this->managerA)
        ->post(route('team-requests.approve', $leaveRequest), ['review_note' => 'Enjoy'])
        ->assertRedirect(route('team-requests.index'))
        ->assertSessionHas('status', 'Leave request approved.');

    $leaveRequest->refresh();
    expect($leaveRequest->status)->toBe('approved')
        ->and($leaveRequest->reviewed_by)->toBe($this->managerA->id)
        ->and($leaveRequest->review_note)->toBe('Enjoy')
        ->and($leaveRequest->reviewed_at)->not->toBeNull();
});

it('forbids a manager from approving another team request', function () {
    $balance = coreBalanceFor($this->staffA, $this->leaveType->id);
    $leaveRequest = coreRequestFor($this->staffA, $this->leaveType->id);

    $this->actingAs($this->managerB)
        ->post(route('team-requests.approve', $leaveRequest))
        ->assertForbidden();

    expect($leaveRequest->fresh()->status)->toBe('pending')
        ->and($balance->fresh()->days_remaining)->toBe(30)
        ->and($leaveRequest->events()->count())->toBe(1);
});

it('forbids a user from approving their own request', function () {
    $this->managerA->forceFill(['manager_id' => $this->managerA->id])->save();
    $balance = coreBalanceFor($this->managerA, $this->leaveType->id);
    $leaveRequest = coreRequestFor($this->managerA, $this->leaveType->id);

    $this->actingAs($this->managerA)
        ->post(route('team-requests.approve', $leaveRequest))
        ->assertForbidden();

    expect($leaveRequest->fresh()->status)->toBe('pending')
        ->and($balance->fresh()->days_remaining)->toBe(30);
});

it('deducts the balance on approval and restores it on cancellation', function () {
    $balance = coreBalanceFor($this->staffA, $this->leaveType->id);
    $leaveRequest = coreRequestFor($this->staffA, $this->leaveType->id);

    $this->actingAs($this->managerA)
        ->post(route('team-requests.approve', $leaveRequest))
        ->assertRedirect(route('team-requests.index'));

    expect($balance->fresh()->days_remaining)->toBe(25)
        ->and($leaveRequest->fresh()->status)->toBe('approved');

    $this->actingAs($this->staffA)
        ->post(route('leave-requests.cancel', $leaveRequest))
        ->assertRedirect(route('leave-requests.index'));

    expect($balance->fresh()->days_remaining)->toBe(30)
        ->and($leaveRequest->fresh()->status)->toBe('cancelled')
        ->and($leaveRequest->events()->count())->toBe(3);
});

it('returns 401 JSON to guests on the API endpoints', function (string $url) {
    $this->getJson($url)
        ->assertUnauthorized()
        ->assertJson(['message' => 'Unauthenticated.']);
})->with(['/api/leave-requests', '/api/leave-balances']);

it('returns only the authenticated user own requests', function () {
    $first = coreRequestFor($this->staffA, $this->leaveType->id);
    $second = coreRequestFor($this->staffA, $this->leaveType->id, 'approved');
    coreRequestFor($this->staffB, $this->leaveType->id);

    Sanctum::actingAs($this->staffA);

    $response = $this->getJson('/api/leave-requests')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect($response->json('data.*.id'))->toEqualCanonicalizing([$first->id, $second->id]);
});

it('returns only the authenticated user own balances', function () {
    coreBalanceFor($this->staffA, $this->leaveType->id, 30, 2026);
    coreBalanceFor($this->staffA, $this->leaveType->id, 12, 2025);
    coreBalanceFor($this->staffB, $this->leaveType->id, 7, 2026);

    Sanctum::actingAs($this->staffA);

    $this->getJson('/api/leave-balances')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.year', 2026)
        ->assertJsonPath('data.0.days_remaining', 30)
        ->assertJsonPath('data.1.year', 2025)
        ->assertJsonPath('data.1.days_remaining', 12);
});

it('returns the expected JSON shape for a request', function () {
    $leaveRequest = coreRequestFor($this->staffA, $this->leaveType->id, 'approved');
    $leaveRequest->reviewed_by = $this->managerA->id;
    $leaveRequest->reviewed_at = now();
    $leaveRequest->review_note = 'Enjoy';
    $leaveRequest->save();

    Sanctum::actingAs($this->staffA);

    $this->getJson('/api/leave-requests')
        ->assertOk()
        ->assertExactJsonStructure([
            'data' => ['*' => ['id', 'leave_type', 'start_date', 'end_date', 'days', 'reason', 'status', 'reviewed_at', 'review_note', 'reviewer']],
        ])
        ->assertJsonPath('data.0.id', $leaveRequest->id)
        ->assertJsonPath('data.0.leave_type', 'Annual')
        ->assertJsonPath('data.0.start_date', '2026-11-15')
        ->assertJsonPath('data.0.end_date', '2026-11-19')
        ->assertJsonPath('data.0.days', 5)
        ->assertJsonPath('data.0.reason', 'Family trip')
        ->assertJsonPath('data.0.status', 'approved')
        ->assertJsonPath('data.0.reviewed_at', '2026-11-10T00:00:00+00:00')
        ->assertJsonPath('data.0.review_note', 'Enjoy')
        ->assertJsonPath('data.0.reviewer', $this->managerA->name);
});

it('returns the expected JSON shape for a balance', function () {
    coreBalanceFor($this->staffA, $this->leaveType->id, 25);

    Sanctum::actingAs($this->staffA);

    $this->getJson('/api/leave-balances')
        ->assertOk()
        ->assertExactJsonStructure([
            'data' => ['*' => ['leave_type', 'year', 'days_remaining']],
        ])
        ->assertJsonPath('data.0.leave_type', 'Annual')
        ->assertJsonPath('data.0.year', 2026)
        ->assertJsonPath('data.0.days_remaining', 25);
});

/**
 * Lazy-loading checks only fire when two or more models load together, and a missing with() makes
 * whenLoaded drop the key instead of throwing, so this test asserts the keys are present for every row.
 */
it('eager loads relationships for every row without lazy loading', function () {
    $sick = LeaveType::create(['name' => 'Sick', 'default_days' => 10]);

    $approved = coreRequestFor($this->staffA, $this->leaveType->id, 'approved');
    $approved->reviewed_by = $this->managerA->id;
    $approved->reviewed_at = now();
    $approved->save();
    coreRequestFor($this->staffA, $this->leaveType->id);
    coreRequestFor($this->staffA, $sick->id);

    coreBalanceFor($this->staffA, $this->leaveType->id);
    coreBalanceFor($this->staffA, $sick->id, 10);

    Sanctum::actingAs($this->staffA);

    $requests = $this->getJson('/api/leave-requests')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertExactJsonStructure([
            'data' => ['*' => ['id', 'leave_type', 'start_date', 'end_date', 'days', 'reason', 'status', 'reviewed_at', 'review_note', 'reviewer']],
        ]);

    expect($requests->json('data.*.leave_type'))->toEqualCanonicalizing(['Annual', 'Annual', 'Sick'])
        ->and($requests->json('data.*.reviewer'))->toEqualCanonicalizing([$this->managerA->name, null, null]);

    $balances = $this->getJson('/api/leave-balances')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertExactJsonStructure([
            'data' => ['*' => ['leave_type', 'year', 'days_remaining']],
        ]);

    expect($balances->json('data.*.leave_type'))->toEqualCanonicalizing(['Annual', 'Sick']);
});
