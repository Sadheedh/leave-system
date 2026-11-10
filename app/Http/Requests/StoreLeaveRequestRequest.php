<?php

namespace App\Http\Requests;

use App\Services\WorkingDayCalculator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreLeaveRequestRequest extends FormRequest
{
    /**
     * Any logged-in user may file a request; the route's auth middleware already guards access.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
                'before_or_equal:'.now()->endOfYear()->toDateString(),
            ],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * Count the working days in the requested range, so the controller and the
     * balance check both use the same number.
     */
    public function workingDays(): int
    {
        return (new WorkingDayCalculator)->count(
            $this->date('start_date'),
            $this->date('end_date'),
        );
    }

    /**
     * Cross-field checks that need the database, run only after the basic rules pass.
     * They read from the request directly because validated() is not available yet.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $days = $this->workingDays();

                if ($days === 0) {
                    $validator->errors()->add(
                        'end_date',
                        'The selected dates contain no working days (Friday and Saturday are weekends).'
                    );

                    return;
                }

                $balance = $this->user()
                    ->balances()
                    ->where('leave_type_id', $this->integer('leave_type_id'))
                    ->where('year', now()->year)
                    ->first();

                if ($balance === null) {
                    $validator->errors()->add(
                        'leave_type_id',
                        'You have no balance for this leave type this year.'
                    );

                    return;
                }

                if ($days > $balance->days_remaining) {
                    $validator->errors()->add(
                        'leave_type_id',
                        "You requested {$days} working days but only have {$balance->days_remaining} left."
                    );
                }
            },
        ];
    }
}
