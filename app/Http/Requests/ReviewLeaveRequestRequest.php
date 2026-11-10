<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReviewLeaveRequestRequest extends FormRequest
{
    /**
     * Authorization is done by LeaveRequestPolicy in the controller, because it needs the
     * specific leave request being reviewed.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The note is optional for both approve and reject.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'review_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
