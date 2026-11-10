<?php

use App\Http\Controllers\LeaveBalanceController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TeamLeaveRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::resource('leave-requests', LeaveRequestController::class)->only(['index', 'create', 'store']);
    Route::get('/balances', [LeaveBalanceController::class, 'index'])->name('balances.index');

    // Only managers and admins get past this gate; the policy then checks each individual request.
    Route::middleware('can:review-leave-requests')->group(function () {
        Route::get('/team-requests', [TeamLeaveRequestController::class, 'index'])->name('team-requests.index');
        Route::post('/team-requests/{leaveRequest}/approve', [TeamLeaveRequestController::class, 'approve'])->name('team-requests.approve');
        Route::post('/team-requests/{leaveRequest}/reject', [TeamLeaveRequestController::class, 'reject'])->name('team-requests.reject');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
