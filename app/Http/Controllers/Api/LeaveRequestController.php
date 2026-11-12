<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LeaveRequestResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LeaveRequestController extends Controller
{
    /**
     * List only the authenticated user's own requests, scoped through the leaveRequests() relation.
     * leaveType and reviewer are eager loaded because preventLazyLoading is on.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return LeaveRequestResource::collection(
            $request->user()->leaveRequests()->with(['leaveType', 'reviewer'])->latest()->get()
        );
    }
}
