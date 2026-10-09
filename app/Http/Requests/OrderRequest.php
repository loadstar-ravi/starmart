<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The checkout form. It carries no prices or amounts: those are always worked out on the server.
 */
class OrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Accept the payment method in any letter case, such as "COD" or "cod".
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('payment_method'))) {
            $this->merge(['payment_method' => Str::lower($this->input('payment_method'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'pincode' => ['required', 'regex:/^[1-9]\d{5}$/'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mobile.regex' => 'The mobile number must be 10 digits and start with 6, 7, 8 or 9.',
            'pincode.regex' => 'The pincode must be 6 digits.',
        ];
    }

    /**
     * Get the validated delivery details in the shape stored on the order.
     *
     * @return array{name: string, mobile: string, email: string, address: string, city: string, state: string, pincode: string}
     */
    public function shippingAddress(): array
    {
        return array_map(
            strval(...),
            $this->safe()->only(['name', 'mobile', 'email', 'address', 'city', 'state', 'pincode']),
        );
    }

    /**
     * Get the validated payment method.
     */
    public function paymentMethod(): PaymentMethod
    {
        return PaymentMethod::from($this->validated('payment_method'));
    }
}
