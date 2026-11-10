<?php

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\User;

// Auto-discovered: Laravel maps App\Models\LeaveRequest to App\Policies\LeaveRequestPolicy by name.
// There is deliberately no admin bypass: an admin may only review requests from their own reports.
class LeaveRequestPolicy
{
    /**
     * All three rules must hold; failing any one gives a 403.
     * $leaveRequest->user is safe to lazy load here: Laravel only flags lazy loading on models
     * hydrated in a batch of 2+ (Builder::hydrate), and route binding fetches a single model.
     */
    public function approve(User $user, LeaveRequest $leaveRequest): bool
    {
        return $leaveRequest->status === 'pending'              // Rule 3: only pending can be reviewed
            && $leaveRequest->user_id !== $user->id            // Rule 2: no self-approval
            && $leaveRequest->user->manager_id === $user->id;  // Rule 1: reviewer must be the requester's manager
    }

    /**
     * Rejecting follows exactly the same rules as approving.
     */
    public function reject(User $user, LeaveRequest $leaveRequest): bool
    {
        return $this->approve($user, $leaveRequest);
    }

    /**
     * Only the owner may cancel, and only while the request is still pending or approved.
     * Strict in_array so a stray type never matches by loose comparison.
     */
    public function cancel(User $user, LeaveRequest $leaveRequest): bool
    {
        return $leaveRequest->user_id === $user->id
            && in_array($leaveRequest->status, ['pending', 'approved'], true);
    }
}
