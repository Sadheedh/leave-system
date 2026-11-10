<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Safe to mass assign: the app (not the user) fills these when creating balances.
#[Fillable(['user_id', 'leave_type_id', 'year', 'days_remaining'])]
class LeaveBalance extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Keep numbers as ints so arithmetic and comparisons behave predictably.
            'user_id' => 'integer',
            'leave_type_id' => 'integer',
            'year' => 'integer',
            'days_remaining' => 'integer',
        ];
    }

    /**
     * A balance belongs to one user (the table holds user_id).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * A balance belongs to one leave type.
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
