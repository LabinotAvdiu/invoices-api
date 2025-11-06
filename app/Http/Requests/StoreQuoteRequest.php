<?php

namespace App\Http\Requests;

use App\Enums\QuoteStatus;
use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuoteRequest extends FormRequest
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
        $customerId = $this->input('customer_id');

        return [
            // company_id vient de la route, pas besoin de validation
            'customer_id' => ['nullable', 'exists:companies,id'],
            
            // Informations client (si non enregistré)
            'customer_name' => ['nullable', 'required_without:customer_id', 'string', 'max:255'],
            'customer_address' => ['nullable',  'required_without:customer_id', 'string'],
            'customer_zip' => ['nullable',  'required_without:customer_id', 'string', 'max:255'],
            'customer_city' => ['nullable',  'required_without:customer_id', 'string', 'max:255'],
            'customer_country' => ['nullable',  'required_without:customer_id', 'string', 'max:255'],
            
            // Informations du devis
            'number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('quotes', 'number')->where('customer_id', $customerId),
            ],
            'status' => ['nullable', Rule::enum(QuoteStatus::class)],
            'issue_date' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:issue_date'],
            
            // Totaux
            'total_ht' => ['nullable', 'numeric', 'min:0'],
            'total_tva' => ['nullable', 'numeric', 'min:0'],
            'total_ttc' => ['nullable', 'numeric', 'min:0'],
            
            // Métadonnées
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * Prepare the data for validation.
     * If customer_id is provided, automatically fill customer information from the company.
     * The customer information always takes priority over manual input.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('customer_id') && $this->customer_id) {
            $customer = Company::find($this->customer_id);
            
            if ($customer) {
                $this->merge([
                    'customer_name' => $customer->name,
                    'customer_address' => $customer->address,
                    'customer_zip' => $customer->zip_code,
                    'customer_city' => $customer->city,
                    'customer_country' => $customer->country,
                ]);
            }
        }
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
            'customer_address.required_without' => 'customer_address_required',
            'customer_address.string' => 'customer_address_invalid',
            
            // customer_zip
            'customer_zip.required_without' => 'customer_zip_required',
            'customer_zip.string' => 'customer_zip_invalid',
            'customer_zip.max' => 'customer_zip_too_long',
            
            // customer_city
            'customer_city.required_without' => 'customer_city_required',
            'customer_city.string' => 'customer_city_invalid',
            'customer_city.max' => 'customer_city_too_long',
            
            // customer_country
            'customer_country.required_without' => 'customer_country_required',
            'customer_country.string' => 'customer_country_invalid',
            'customer_country.max' => 'customer_country_too_long',
            
            // number
            'number.required' => 'number_required',
            'number.string' => 'number_invalid',
            'number.max' => 'number_too_long',
            'number.unique' => 'number_already_exists',
            
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
