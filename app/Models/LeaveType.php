<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Only these columns may be set through create()/fill() (mass assignment protection).
#[Fillable(['name', 'default_days'])]
class LeaveType extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Ensures default_days always comes back as a PHP int, not a string.
            'default_days' => 'integer',
        ];
    }

    /**
     * One leave type has many balances (one per user per year).
     */
    public function balances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    /**
     * One leave type has many leave requests.
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
