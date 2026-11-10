<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveBalanceController extends Controller
{
    /**
     * Show the logged-in user's balances for the current year.
     */
    public function index(Request $request): View
    {
        return view('balances.index', [
            'balances' => $request->user()
                ->balances()
                ->with('leaveType')
                ->where('year', now()->year)
                ->orderBy('leave_type_id')
                ->get(),
        ]);
    }
}
