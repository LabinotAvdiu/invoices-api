<?php

namespace App\Http\Requests;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends FormRequest
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
        $invoice = $this->route('invoice');
        $invoiceId = $invoice instanceof Invoice ? $invoice->id : $invoice;
        $companyId = $invoice instanceof Invoice ? $invoice->company_id : null;

        return [
            'customer_id' => ['sometimes', 'nullable', 'exists:companies,id'],
            
            'customer_name' => [
                'sometimes',
                'nullable',
                function ($attribute, $value, $fail) use ($invoice) {
                    $existingCustomerId = $invoice instanceof Invoice ? $invoice->customer_id : null;
                    $requestCustomerId = $this->input('customer_id');
                    $finalCustomerId = $requestCustomerId ?? $existingCustomerId;
                    
                    if (empty($value) && !$finalCustomerId) {
                        $fail('customer_name_required');
                    }
                },
                'string',
                'max:255',
            ],
            'customer_address' => ['sometimes', 'nullable', 'string'],
            'customer_zip' => ['sometimes', 'nullable', 'string', 'max:255'],
            'customer_city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'customer_country' => ['sometimes', 'nullable', 'string', 'max:255'],
            
            'number' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('invoices', 'number')
                    ->where('company_id', $companyId)
                    ->ignore($invoiceId),
            ],
            'status' => ['sometimes', Rule::enum(InvoiceStatus::class)],
            'issue_date' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:issue_date'],
            'is_locked' => ['sometimes', 'boolean'],
            'total_ht' => ['sometimes', 'numeric', 'min:0'],
            'total_tva' => ['sometimes', 'numeric', 'min:0'],
            'total_ttc' => ['sometimes', 'numeric', 'min:0'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * Prepare the data for validation.
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
            'customer_id.exists' => 'customer_not_found',
            'customer_name.string' => 'customer_name_invalid',
            'customer_name.max' => 'customer_name_too_long',
            'customer_address.string' => 'customer_address_invalid',
            'customer_zip.string' => 'customer_zip_invalid',
            'customer_zip.max' => 'customer_zip_too_long',
            'customer_city.string' => 'customer_city_invalid',
            'customer_city.max' => 'customer_city_too_long',
            'customer_country.string' => 'customer_country_invalid',
            'customer_country.max' => 'customer_country_too_long',
            'number.string' => 'number_invalid',
            'number.max' => 'number_too_long',
            'number.unique' => 'number_already_exists',
            'status.enum' => 'status_invalid',
            'metadata.array' => 'metadata_invalid',
        ];
    }
}

