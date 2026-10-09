<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Services\SlugGenerator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    /**
     * The stock rules, shared with the quick stock update on the product list.
     *
     * @var list<string>
     */
    public const array STOCK_RULES = ['required', 'integer', 'min:0', 'max:1000000'];

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
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'nullable',
                'string',
                'max:170',
                'regex:'.SlugGenerator::PATTERN,
                Rule::unique(Product::class)->ignore($this->route('product')),
            ],
            'category_id' => ['required', 'integer', Rule::exists(Category::class, 'id')],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['bail', 'required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'stock' => self::STOCK_RULES,
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['bail', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
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
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and hyphens.',
            'images.max' => 'You may upload at most 5 images at a time.',
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
            'category_id' => 'category',
            'images.*' => 'image',
        ];
    }
}
