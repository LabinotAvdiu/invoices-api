<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuoteLineRequest extends FormRequest
{
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'quantity' => ['sometimes', 'required', 'numeric', 'min:0', 'max:999999999.999'],
            'unit_price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999999.99'],
            'tva_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'title_required',
            'title.string' => 'title_invalid',
            'title.max' => 'title_too_long',
            
            'description.string' => 'description_invalid',
            
            'quantity.required' => 'quantity_required',
            'quantity.numeric' => 'quantity_invalid',
            'quantity.min' => 'quantity_must_be_positive',
            'quantity.max' => 'quantity_too_large',
            
            'unit_price.required' => 'unit_price_required',
            'unit_price.numeric' => 'unit_price_invalid',
            'unit_price.min' => 'unit_price_must_be_positive',
            'unit_price.max' => 'unit_price_too_large',
            
            'tva_rate.numeric' => 'tva_rate_invalid',
            'tva_rate.min' => 'tva_rate_must_be_positive',
            'tva_rate.max' => 'tva_rate_too_large',
        ];
    }
}
