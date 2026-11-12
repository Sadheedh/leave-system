<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LeaveBalanceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LeaveBalanceController extends Controller
{
    /**
     * List only the authenticated user's own balances for every year, newest year first.
     * leaveType is eager loaded because preventLazyLoading is on.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return LeaveBalanceResource::collection(
            $request->user()->balances()->with('leaveType')->orderByDesc('year')->orderBy('leave_type_id')->get()
        );
    }
}
