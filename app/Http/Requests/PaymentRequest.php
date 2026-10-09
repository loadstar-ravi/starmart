<?php

namespace App\Http\Requests;

use App\Enums\PaymentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Accept the simulated outcome in any letter case, such as "FAILED" or "failed".
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('simulate'))) {
            $this->merge(['simulate' => Str::lower($this->input('simulate'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The amount is only checked for shape here; the payment service compares it with the order total.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric'],
            'simulate' => [
                'nullable',
                Rule::enum(PaymentStatus::class)->only([PaymentStatus::Success, PaymentStatus::Failed]),
            ],
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
            'order_id' => 'order',
            'simulate' => 'test outcome',
        ];
    }

    /**
     * Determine whether the payment should be declined instead of going through.
     */
    public function simulatesFailure(): bool
    {
        return $this->validated('simulate') === PaymentStatus::Failed->value;
    }
}
