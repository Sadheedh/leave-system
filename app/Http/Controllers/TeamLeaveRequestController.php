<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewLeaveRequestRequest;
use App\Models\LeaveBalance;
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

        $error = $this->review($leaveRequest, $request->user(), 'approved', $request->validated('review_note'));

        if ($error !== null) {
            return redirect()->route('team-requests.index')->with('error', $error);
        }

        return redirect()->route('team-requests.index')->with('status', 'Leave request approved.');
    }

    /**
     * Reject a request; same policy rules as approving.
     */
    public function reject(ReviewLeaveRequestRequest $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorize('reject', $leaveRequest);

        $error = $this->review($leaveRequest, $request->user(), 'rejected', $request->validated('review_note'));

        if ($error !== null) {
            return redirect()->route('team-requests.index')->with('error', $error);
        }

        return redirect()->route('team-requests.index')->with('status', 'Leave request rejected.');
    }

    /**
     * Record the decision, the balance change and the history event together, so none exists without the others.
     * Returns null on success, or an error message when nothing was changed.
     */
    private function review(LeaveRequest $leaveRequest, User $reviewer, string $toStatus, ?string $note): ?string
    {
        return DB::transaction(function () use ($leaveRequest, $reviewer, $toStatus, $note): ?string {
            // WHY: re-read the row with a lock. A double click sends two requests; the second waits here,
            // then sees the status is no longer pending, so the balance cannot be deducted twice.
            $locked = LeaveRequest::whereKey($leaveRequest->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                return 'This request has already been reviewed.';
            }

            // Only approving spends days; rejecting never touches the balance.
            if ($toStatus === 'approved') {
                // The balance year is the year the leave starts (submission checks the same year).
                $balance = LeaveBalance::where('user_id', $locked->user_id)
                    ->where('leave_type_id', $locked->leave_type_id)
                    ->where('year', $locked->start_date->year)
                    ->lockForUpdate()
                    ->first();

                if ($balance === null) {
                    return 'No leave balance found for this leave type.';
                }

                // Checked again here because the balance may have changed since the request was submitted.
                if ($locked->days > $balance->days_remaining) {
                    return "Only {$balance->days_remaining} day(s) left, but this request needs {$locked->days}.";
                }

                $balance->days_remaining -= $locked->days;
                $balance->save();
            }

            // Set one by one because these columns are deliberately not fillable.
            $locked->status = $toStatus;
            $locked->reviewed_by = $reviewer->id;
            $locked->reviewed_at = now();
            $locked->review_note = $note;
            $locked->save();

            $locked->events()->create([
                'user_id' => $reviewer->id,
                'from_status' => 'pending',
                'to_status' => $toStatus,
                'note' => $note,
            ]);

            return null;
        });
    }
}
