<?php

namespace App\Http\Resources;

use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LeaveBalance
 */
class LeaveBalanceResource extends JsonResource
{
    /**
     * Shape one leave balance for the API.
     * whenLoaded never lazy loads: if leaveType was not eager loaded the key is dropped, so the controller must eager load.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'leave_type' => $this->whenLoaded('leaveType', fn (LeaveType $leaveType): string => $leaveType->name),
            'year' => $this->year,
            'days_remaining' => $this->days_remaining,
        ];
    }
}
