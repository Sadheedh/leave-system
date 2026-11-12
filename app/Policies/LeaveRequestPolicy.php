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
     * Only the owner may cancel.
     * A pending request can always be withdrawn: it never had days deducted, so the balance is untouched.
     * An approved request can only be cancelled while its start date is still in the future, because once
     * the leave has begun the days are used and must not be handed back.
     */
    public function cancel(User $user, LeaveRequest $leaveRequest): bool
    {
        if ($leaveRequest->user_id !== $user->id) {
            return false;
        }

        if ($leaveRequest->status === 'pending') {
            return true;
        }

        return $leaveRequest->status === 'approved'
            && $leaveRequest->start_date->greaterThan(today());
    }

    /**
     * Only the owner and the owner's manager may open the detail page.
     * There is deliberately no admin bypass: an admin who is not the owner's manager gets a 403.
     */
    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        return $leaveRequest->user_id === $user->id
            || $leaveRequest->user->manager_id === $user->id;
    }
}
