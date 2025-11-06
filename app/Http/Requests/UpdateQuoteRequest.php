<?php

namespace App\Http\Requests;

use App\Enums\QuoteStatus;
use App\Models\Quote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuoteRequest extends FormRequest
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
        $quote = $this->route('quote');
        $quoteId = $quote instanceof Quote ? $quote->id : $quote;
        $customerId = $this->input('customer_id', $quote instanceof Quote ? $quote->customer_id : null);
        
        // Get existing customer_id from quote if not provided in request
        $existingCustomerId = $quote instanceof Quote ? $quote->customer_id : null;
        $requestCustomerId = $this->input('customer_id');
        $finalCustomerId = $requestCustomerId ?? $existingCustomerId;

        return [
            // company_id ne peut jamais être modifié, il vient de la route
            'customer_id' => ['nullable', 'exists:companies,id'],
            
            // Informations client (si non enregistré)
            // Use custom rule to check against existing customer_id if not in request
            'customer_name' => [
                'nullable',
                function ($attribute, $value, $fail) use ($requestCustomerId, $existingCustomerId) {
                    $finalCustomerId = $requestCustomerId ?? $existingCustomerId;
                    if (!$finalCustomerId && !$value) {
                        $fail('customer_name_required');
                    }
                },
                'string',
                'max:255',
            ],
            'customer_address' => ['nullable', 'string'],
            'customer_zip' => ['nullable', 'string', 'max:255'],
            'customer_city' => ['nullable', 'string', 'max:255'],
            'customer_country' => ['nullable', 'string', 'max:255'],
            
            // Informations du devis
            'number' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('quotes', 'number')
                    ->where('customer_id', $customerId)
                    ->ignore($quoteId),
            ],
            'status' => ['sometimes', Rule::enum(QuoteStatus::class)],
            'issue_date' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:issue_date'],
            
            // Totaux
            'total_ht' => ['sometimes', 'numeric', 'min:0'],
            'total_tva' => ['sometimes', 'numeric', 'min:0'],
            'total_ttc' => ['sometimes', 'numeric', 'min:0'],
            
            // Métadonnées
            'metadata' => ['nullable', 'array'],
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
            // customer_id
            'customer_id.exists' => 'customer_not_found',
            
            // customer_name
            'customer_name.required_without' => 'customer_name_required',
            'customer_name.string' => 'customer_name_invalid',
            'customer_name.max' => 'customer_name_too_long',
            
            // customer_address
            'customer_address.string' => 'customer_address_invalid',
            
            // customer_zip
            'customer_zip.string' => 'customer_zip_invalid',
            'customer_zip.max' => 'customer_zip_too_long',
            
            // customer_city
            'customer_city.string' => 'customer_city_invalid',
            'customer_city.max' => 'customer_city_too_long',
            
            // customer_country
            'customer_country.string' => 'customer_country_invalid',
            'customer_country.max' => 'customer_country_too_long',
            
            // number
            'number.string' => 'number_invalid',
            'number.max' => 'number_too_long',
            'number.unique' => 'number_already_exists',
            
            // status
            'status.enum' => 'status_invalid',
            
            // issue_date
            'issue_date.date' => 'issue_date_invalid_date',
            
            // valid_until
            'valid_until.date' => 'valid_until_invalid_date',
            'valid_until.after_or_equal' => 'valid_until_must_be_after_issue_date',
            
            // total_ht
            'total_ht.numeric' => 'total_ht_invalid',
            'total_ht.min' => 'total_ht_must_be_positive',
            
            // total_tva
            'total_tva.numeric' => 'total_tva_invalid',
            'total_tva.min' => 'total_tva_must_be_positive',
            
            // total_ttc
            'total_ttc.numeric' => 'total_ttc_invalid',
            'total_ttc.min' => 'total_ttc_must_be_positive',
            
            // metadata
            'metadata.array' => 'metadata_invalid',
        ];
    }
}
