<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeaveRequestRequest;
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
}
