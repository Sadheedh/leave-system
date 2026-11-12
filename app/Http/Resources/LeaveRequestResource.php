<?php

namespace App\Http\Resources;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LeaveRequest
 */
class LeaveRequestResource extends JsonResource
{
    /**
     * Shape one leave request for the API.
     * whenLoaded never lazy loads: if a relation was not eager loaded the key is dropped, so the controller must eager load.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'leave_type' => $this->whenLoaded('leaveType', fn (LeaveType $leaveType): string => $leaveType->name),
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'days' => $this->days,
            'reason' => $this->reason,
            'status' => $this->status,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'review_note' => $this->review_note,
            'reviewer' => $this->whenLoaded('reviewer', fn (User $reviewer): string => $reviewer->name),
        ];
    }
}
