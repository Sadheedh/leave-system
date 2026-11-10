<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// leave_request_id is left out because events are created through $leaveRequest->events()->create(),
// which fills it automatically.
#[Fillable(['user_id', 'from_status', 'to_status', 'note'])]
class LeaveRequestEvent extends Model
{
    // The table has no updated_at column, so tell Eloquent not to try to write one.
    const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * The leave request this event belongs to.
     */
    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    /**
     * The user who triggered the status change.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
