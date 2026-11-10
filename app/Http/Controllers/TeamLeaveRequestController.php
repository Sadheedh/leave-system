<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewLeaveRequestRequest;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TeamLeaveRequestController extends Controller
{
    /**
     * Pending requests from the logged-in manager's own reports, oldest first so the longest wait is on top.
     * user and leaveType are eager loaded to avoid N+1 queries.
     */
    public function index(Request $request): View
    {
        return view('team-requests.index', [
            'leaveRequests' => LeaveRequest::query()
                ->with(['user', 'leaveType'])
                ->where('status', 'pending')
                ->whereRelation('user', 'manager_id', $request->user()->id)
                ->oldest()
                ->get(),
        ]);
    }

    /**
     * Approve a request; the policy runs before anything is changed.
     */
    public function approve(ReviewLeaveRequestRequest $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorize('approve', $leaveRequest);

        $this->review($leaveRequest, $request->user(), 'approved', $request->validated('review_note'));

        return redirect()->route('team-requests.index')->with('status', 'Leave request approved.');
    }

    /**
     * Reject a request; same policy rules as approving.
     */
    public function reject(ReviewLeaveRequestRequest $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorize('reject', $leaveRequest);

        $this->review($leaveRequest, $request->user(), 'rejected', $request->validated('review_note'));

        return redirect()->route('team-requests.index')->with('status', 'Leave request rejected.');
    }

    /**
     * Record the decision and its history event together, so one never exists without the other.
     */
    private function review(LeaveRequest $leaveRequest, User $reviewer, string $toStatus, ?string $note): void
    {
        DB::transaction(function () use ($leaveRequest, $reviewer, $toStatus, $note): void {
            // Day 5: balance deduction goes here, with a second balance check: re-fetch the
            // balance with lockForUpdate() and re-check the status is still pending.

            // Set one by one because these columns are deliberately not fillable.
            $leaveRequest->status = $toStatus;
            $leaveRequest->reviewed_by = $reviewer->id;
            $leaveRequest->reviewed_at = now();
            $leaveRequest->review_note = $note;
            $leaveRequest->save();

            $leaveRequest->events()->create([
                'user_id' => $reviewer->id,
                'from_status' => 'pending',
                'to_status' => $toStatus,
                'note' => $note,
            ]);
        });
    }
}
