<?php

namespace App\Http\Controllers;

use App\Models\LeaveType;
use App\Models\User;
use Illuminate\View\View;

class AdminController extends Controller
{
    /**
     * Show every user and every leave type (read-only; the route is gated by view-admin-page).
     */
    public function index(): View
    {
        return view('admin.index', [
            // with('manager') avoids an N+1 query, and lazy loading is prevented outside production.
            'users' => User::query()->with('manager')->orderBy('id')->get(),
            'leaveTypes' => LeaveType::query()->orderBy('name')->get(),
        ]);
    }
}
