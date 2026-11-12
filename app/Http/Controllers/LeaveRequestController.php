<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeaveRequestRequest;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LeaveRequestController extends Controller
{
    /**
     * List the logged-in user's own requests; leaveType is eager loaded to avoid N+1 queries.
     */
    public function index(Request $request): View
    {
        return view('leave-requests.index', [
            'leaveRequests' => $request->user()->leaveRequests()->with('leaveType')->latest()->get(),
        ]);
    }

    /**
     * Show the form for filing a new request.
     */
    public function create(): View
    {
        return view('leave-requests.create', [
            'leaveTypes' => LeaveType::orderBy('name')->get(),
        ]);
    }

    /**
     * Store the request and its first history event together, so one never exists without the other.
     */
    public function store(StoreLeaveRequestRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $leaveRequest = $request->user()->leaveRequests()->create([
                ...$request->validated(),
                'days' => $request->workingDays(),
            ]);

            $leaveRequest->events()->create([
                'user_id' => $request->user()->id,
                'from_status' => null,
                'to_status' => 'pending',
            ]);
        });

        return redirect()->route('leave-requests.index')->with('status', 'Leave request submitted.');
    }

    /**
     * Detail page; the policy allows only the owner and the owner's manager.
     * Events are ordered by id, not created_at, because several events can share the same second.
     */
    public function show(Request $request, LeaveRequest $leaveRequest): View
    {
        $this->authorize('view', $leaveRequest);

        $leaveRequest->load([
            'leaveType',
            'user',
            'reviewer',
            'events' => fn ($query) => $query->with('user')->orderBy('id'),
        ]);

        return view('leave-requests.show', ['leaveRequest' => $leaveRequest]);
    }

    /**
     * Cancel your own pending or approved request; an approved one gets its days back.
     * The policy is the real protection, the buttons are only a convenience.
     */
    public function cancel(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->authorize('cancel', $leaveRequest);

        $error = DB::transaction(function () use ($request, $leaveRequest): ?string {
            // WHY: re-read the row with a lock so a double click cannot restore the days twice.
            $locked = LeaveRequest::whereKey($leaveRequest->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, ['pending', 'approved'], true)) {
                return 'This request can no longer be cancelled.';
            }

            $previous = $locked->status;

            // Only an approved request had its days deducted, so only that one gets them back.
            if ($previous === 'approved') {
                $balance = LeaveBalance::where('user_id', $locked->user_id)
                    ->where('leave_type_id', $locked->leave_type_id)
                    ->where('year', $locked->start_date->year)
                    ->lockForUpdate()
                    ->first();

                // Return an error instead of creating a row, so the transaction never half-applies.
                if ($balance === null) {
                    return 'No leave balance found to restore.';
                }

                $balance->days_remaining += $locked->days;
                $balance->save();
            }

            // status is not fillable, so it is set directly.
            $locked->status = 'cancelled';
            $locked->save();

            $locked->events()->create([
                'user_id' => $request->user()->id,
                'from_status' => $previous,
                'to_status' => 'cancelled',
                'note' => null,
            ]);

            return null;
        });

        if ($error !== null) {
            return redirect()->route('leave-requests.show', $leaveRequest)->with('error', $error);
        }

        return redirect()->route('leave-requests.index')->with('status', 'Leave request cancelled.');
    }
}
