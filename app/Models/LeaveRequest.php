<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// user_id, status and reviewed_* are deliberately NOT fillable: a requester must not be able
// to approve their own request or file one as someone else. The controller sets them explicitly.
#[Fillable(['leave_type_id', 'start_date', 'end_date', 'days', 'reason'])]
class LeaveRequest extends Model
{
    /**
     * Default attribute values, so new requests always start as pending.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Cast to Carbon date objects so we can compare and format dates easily.
            'start_date' => 'date',
            'end_date' => 'date',
            'reviewed_at' => 'datetime',
            // Integer casts so strict === comparisons in the policy are int vs int, not string vs int.
            'user_id' => 'integer',
            'leave_type_id' => 'integer',
            'reviewed_by' => 'integer',
            'days' => 'integer',
        ];
    }

    /**
     * The staff member who made the request.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The type of leave requested.
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    /**
     * The manager who approved or rejected it.
     * The column is reviewed_by, not reviewer_id, so the key must be named explicitly.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * The history of status changes for this request.
     */
    public function events(): HasMany
    {
        return $this->hasMany(LeaveRequestEvent::class);
    }
}
