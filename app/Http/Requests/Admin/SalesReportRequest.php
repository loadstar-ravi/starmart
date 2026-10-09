<?php

namespace App\Http\Requests\Admin;

use App\Services\SalesReportService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SalesReportRequest extends FormRequest
{
    private const string DATE_FORMAT = 'Y-m-d';

    /**
     * Determine if the user is authorized to make this request.
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
            'from' => ['nullable', 'date_format:'.self::DATE_FORMAT],
            'to' => ['nullable', 'date_format:'.self::DATE_FORMAT],
        ];
    }

    /**
     * Check the range as a whole, once both dates are well-formed.
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

                [$from, $to] = [$this->from(), $this->to()];

                if ($to->isAfter(now())) {
                    $validator->errors()->add('to', 'The end date must not be in the future.');
                } elseif ($from->isAfter($to)) {
                    $validator->errors()->add('from', 'The start date must not be after the end date.');
                } elseif ($from->diffInDays($to) + 1 > SalesReportService::MAX_DAYS) {
                    $validator->errors()->add('from', 'The report can cover at most '.SalesReportService::MAX_DAYS.' days.');
                }
            },
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'from' => 'start date',
            'to' => 'end date',
        ];
    }

    /**
     * Get the first day of the report: the one asked for, or far enough
     * before the last day to cover the default number of days.
     */
    public function from(): CarbonImmutable
    {
        return $this->day('from') ?? $this->to()->subDays(SalesReportService::DEFAULT_DAYS - 1);
    }

    /**
     * Get the last day of the report: the one asked for, or today.
     */
    public function to(): CarbonImmutable
    {
        return $this->day('to') ?? now()->toImmutable()->startOfDay();
    }

    private function day(string $key): ?CarbonImmutable
    {
        return $this->date($key, self::DATE_FORMAT)?->toImmutable()->startOfDay();
    }
}
