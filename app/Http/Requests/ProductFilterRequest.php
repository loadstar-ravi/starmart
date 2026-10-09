<?php

namespace App\Http\Requests;

use App\Enums\ProductSort;
use App\Models\Category;
use App\Services\CatalogService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductFilterRequest extends FormRequest
{
    public const int MAX_PER_PAGE = 48;

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
            'search' => ['nullable', 'string', 'max:100'],
            'category' => [
                'nullable',
                'string',
                'max:120',
                Rule::exists(Category::class, 'slug')->where('is_active', true),
            ],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'max_price' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999.99',
                Rule::when(is_numeric($this->input('min_price')), ['gte:min_price']),
            ],
            'sort' => ['nullable', Rule::enum(ProductSort::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'page' => ['nullable', 'integer', 'min:1'],
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
            'max_price.gte' => 'The maximum price must not be less than the minimum price.',
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
            'min_price' => 'minimum price',
            'max_price' => 'maximum price',
        ];
    }

    /**
     * Get the page size the client asked for, or the default.
     */
    public function perPage(): int
    {
        return $this->integer('per_page', CatalogService::DEFAULT_PER_PAGE);
    }
}
