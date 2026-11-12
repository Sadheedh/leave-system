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

    // Approving now deducts days, so every user whose request gets approved needs a balance (year = start_date year).
    foreach ([$this->staffA, $this->managerA] as $user) {
        LeaveBalance::create(['user_id' => $user->id, 'leave_type_id' => $this->leaveType->id, 'year' => 2026, 'days_remaining' => 30]);
    }
});

/**
 * Create a leave request for a user with the given status.
 * status is not fillable, so it is assigned directly and saved.
 */
function pendingLeaveRequestFor(User $user, int $leaveTypeId, string $status = 'pending'): LeaveRequest
{
    $leaveRequest = $user->leaveRequests()->create([
        'leave_type_id' => $leaveTypeId,
        'start_date' => '2026-11-15',
        'end_date' => '2026-11-16',
        'days' => 2,
        'reason' => 'Family trip',
    ]);

    if ($status !== 'pending') {
        $leaveRequest->status = $status;
        $leaveRequest->save();
    }

    return $leaveRequest;
}

it('redirects guests to the login page', function () {
    $this->get(route('team-requests.index'))->assertRedirect(route('login'));
});

it('forbids staff from the team page and hides the nav link', function () {
    $this->actingAs($this->staffA)->get(route('team-requests.index'))->assertForbidden();

    $this->actingAs($this->staffA)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Team Requests');
});

it('forbids staff from posting an approval directly', function () {
    $leaveRequest = pendingLeaveRequestFor($this->staffA, $this->leaveType->id);

    $this->actingAs($this->staffA)
        ->post(route('team-requests.approve', $leaveRequest))
        ->assertForbidden();

    expect($leaveRequest->fresh()->status)->toBe('pending');
});

it('shows a manager only their own staff pending requests', function () {
    $staffA2 = User::factory()->create(['name' => 'Carol Approved', 'manager_id' => $this->managerA->id]);

    pendingLeaveRequestFor($this->staffA, $this->leaveType->id);
    pendingLeaveRequestFor($this->staffB, $this->leaveType->id);
    pendingLeaveRequestFor($staffA2, $this->leaveType->id, 'approved');

    $this->actingAs($this->managerA)
        ->get(route('team-requests.index'))
        ->assertOk()
        ->assertSee('Alice Staff')
        ->assertDontSee('Bob Staff')
        ->assertDontSee('Carol Approved');
});

it('lets a manager approve a request and records the review', function () {
    $leaveRequest = pendingLeaveRequestFor($this->staffA, $this->leaveType->id);

    $this->actingAs($this->managerA)
        ->post(route('team-requests.approve', $leaveRequest), ['review_note' => 'Enjoy'])
        ->assertRedirect(route('team-requests.index'));

    $leaveRequest->refresh();
    expect($leaveRequest->status)->toBe('approved')
        ->and($leaveRequest->reviewed_by)->toBe($this->managerA->id)
        ->and($leaveRequest->review_note)->toBe('Enjoy')
        ->and($leaveRequest->reviewed_at)->not->toBeNull();

    $events = $leaveRequest->events()->where('from_status', 'pending')->get();
    expect($events)->toHaveCount(1)
        ->and($events->first()->to_status)->toBe('approved')
        ->and($events->first()->user_id)->toBe($this->managerA->id);
});

it('lets a manager reject a request with a note', function () {
    $leaveRequest = pendingLeaveRequestFor($this->staffA, $this->leaveType->id);

    $this->actingAs($this->managerA)
        ->post(route('team-requests.reject', $leaveRequest), ['review_note' => 'Team is short'])
        ->assertRedirect(route('team-requests.index'));

    $leaveRequest->refresh();
    expect($leaveRequest->status)->toBe('rejected')
        ->and($leaveRequest->reviewed_by)->toBe($this->managerA->id)
        ->and($leaveRequest->review_note)->toBe('Team is short')
        ->and($leaveRequest->reviewed_at)->not->toBeNull();

    $events = $leaveRequest->events()->where('from_status', 'pending')->get();
    expect($events)->toHaveCount(1)
        ->and($events->first()->to_status)->toBe('rejected')
        ->and($events->first()->user_id)->toBe($this->managerA->id);
});

it('forbids a manager who is not the requester manager', function (string $action) {
    $leaveRequest = pendingLeaveRequestFor($this->staffA, $this->leaveType->id);

    $this->actingAs($this->managerB)
        ->post(route("team-requests.{$action}", $leaveRequest))
        ->assertForbidden();

    expect($leaveRequest->fresh()->status)->toBe('pending')
        ->and($leaveRequest->events()->count())->toBe(0);
})->with(['approve', 'reject']);

it('forbids self-approval even when the manager is their own manager', function () {
    // Isolates Rule 2: Rule 1 would pass here, so only the self-approval check can block it.
    $this->managerA->forceFill(['manager_id' => $this->managerA->id])->save();
    $leaveRequest = pendingLeaveRequestFor($this->managerA, $this->leaveType->id);

    $this->actingAs($this->managerA)
        ->post(route('team-requests.approve', $leaveRequest))
        ->assertForbidden();

    expect($leaveRequest->fresh()->status)->toBe('pending');
});

it('forbids reviewing a request that is no longer pending', function (string $status, string $action) {
    $leaveRequest = pendingLeaveRequestFor($this->staffA, $this->leaveType->id, $status);

    $this->actingAs($this->managerA)
        ->post(route("team-requests.{$action}", $leaveRequest))
        ->assertForbidden();

    expect($leaveRequest->fresh()->status)->toBe($status);
})->with(['approved', 'rejected'])->with(['approve', 'reject']);

it('lets an admin approve a manager request', function () {
    $leaveRequest = pendingLeaveRequestFor($this->managerA, $this->leaveType->id);

    $this->actingAs($this->admin)
        ->post(route('team-requests.approve', $leaveRequest))
        ->assertRedirect(route('team-requests.index'));

    expect($leaveRequest->fresh()->status)->toBe('approved');
});

it('rejects a review note longer than 1000 characters', function () {
    $leaveRequest = pendingLeaveRequestFor($this->staffA, $this->leaveType->id);

    $this->actingAs($this->managerA)
        ->post(route('team-requests.approve', $leaveRequest), ['review_note' => str_repeat('a', 1001)])
        ->assertSessionHasErrors('review_note');

    expect($leaveRequest->fresh()->status)->toBe('pending');
});

it('lets only the owner cancel a pending or approved request', function () {
    $pending = pendingLeaveRequestFor($this->staffA, $this->leaveType->id);
    $approved = pendingLeaveRequestFor($this->staffA, $this->leaveType->id, 'approved');
    $rejected = pendingLeaveRequestFor($this->staffA, $this->leaveType->id, 'rejected');

    expect($this->staffA->can('cancel', $pending))->toBeTrue()
        ->and($this->staffA->can('cancel', $approved))->toBeTrue()
        ->and($this->staffA->can('cancel', $rejected))->toBeFalse()
        ->and($this->staffB->can('cancel', $pending))->toBeFalse();
});

it('shows the note validation error and kept note only under the submitted row', function () {
    $staffA2 = User::factory()->create(['name' => 'Dave Second', 'manager_id' => $this->managerA->id]);
    $first = pendingLeaveRequestFor($this->staffA, $this->leaveType->id);
    pendingLeaveRequestFor($staffA2, $this->leaveType->id);

    $longNote = str_repeat('a', 1001);

    $this->actingAs($this->managerA)
        ->from(route('team-requests.index'))
        ->post(route('team-requests.approve', $first), ['review_note' => $longNote, 'leave_request_id' => $first->id])
        ->assertRedirect(route('team-requests.index'));

    $html = $this->get(route('team-requests.index'))->getContent();
    $message = __('validation.max.string', ['attribute' => 'review note', 'max' => 1000]);

    expect(substr_count($html, $message))->toBe(1)
        ->and(substr_count($html, $longNote))->toBe(1);
});

it('does not let an admin approve their own request', function () {
    $adminRequest = pendingLeaveRequestFor($this->admin, $this->leaveType->id);

    $this->actingAs($this->admin)
        ->post(route('team-requests.approve', $adminRequest))
        ->assertForbidden();

    expect($adminRequest->fresh()->status)->toBe('pending');
});

it('does not let a manager approve their own request when an admin is their manager', function () {
    $own = pendingLeaveRequestFor($this->managerA, $this->leaveType->id);

    $this->actingAs($this->managerA)
        ->post(route('team-requests.approve', $own))
        ->assertForbidden();

    expect($own->fresh()->status)->toBe('pending');
});

it('shows an admin only the pending requests of their direct reports', function () {
    pendingLeaveRequestFor($this->managerA, $this->leaveType->id);
    pendingLeaveRequestFor($this->staffA, $this->leaveType->id);

    $this->actingAs($this->admin)
        ->get(route('team-requests.index'))
        ->assertOk()
        ->assertSee($this->managerA->name)
        ->assertDontSee('Alice Staff');
});

it('returns 404 when the leave request does not exist', function () {
    $this->actingAs($this->managerA)
        ->post(route('team-requests.approve', 999999))
        ->assertNotFound();
});
