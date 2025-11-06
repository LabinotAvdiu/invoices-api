<?php

namespace App\Http\Requests;

use App\Enums\CompanyType;
use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCompanyRequest extends FormRequest
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
        $type = $this->input('type', CompanyType::CUSTOMER->value);
        $userId = $this->user()?->id ?? 0;

        $rules = $this->getBaseRules();

        // Add type-specific validation rules
        if ($type === CompanyType::ISSUER->value) {
            $rules = array_merge($rules, $this->getIssuerRules());
        } else {
            $rules = array_merge($rules, $this->getCustomerRules($userId));
        }

        return $rules;
    }

    /**
     * Configure the validator instance.
     *
     * @param Validator $validator
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $type = $this->input('type', CompanyType::CUSTOMER->value);
        $userId = $this->user()?->id;

        if (!$userId) {
            return;
        }

        if ($type === CompanyType::CUSTOMER->value) {
            $validator->after(function ($validator) use ($userId) {
                $this->validateUniqueCustomerName($validator, $userId);
                $this->validateUniqueCustomerSiret($validator, $userId);
            });
        }
    }

    /**
     * Get base validation rules common to all company types.
     *
     * @return array<string, string>
     */
    private function getBaseRules(): array
    {
        return [
            'type' => ['required', Rule::enum(CompanyType::class)],
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'name' => 'required|string|max:255',
            'legal_form' => 'nullable|string|max:255|in:SARL,SAS,SA,Auto-entrepreneur,EURL,SNC,SCI',
            'siret' => 'nullable|string|size:14|regex:/^[0-9]{14}$/',
            'address' => 'required|string',
            'zip_code' => 'required|string|max:10',
            'city' => 'required|string|max:255',
            'country' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|string|email|max:255',
            'creation_date' => 'nullable|date',
            'sector' => 'nullable|string|max:255',
        ];
    }

    /**
     * Get validation rules specific to issuer companies.
     * Issuer companies must have unique name and SIRET globally.
     *
     * @return array<string, array>
     */
    private function getIssuerRules(): array
    {
        return [
            'name' => [
                'required',
                Rule::unique('companies', 'name')->where('type', CompanyType::ISSUER->value),
            ],
            'siret' => [
                'nullable',
                'string',
                'size:14',
                'regex:/^[0-9]{14}$/',
                Rule::unique('companies', 'siret')->where('type', CompanyType::ISSUER->value),
            ],
        ];
    }

    /**
     * Get validation rules specific to customer companies.
     * Customer companies must have unique name and SIRET per user.
     *
     * @param int $userId
     * @return array<string, array>
     */
    private function getCustomerRules(int $userId): array
    {
        return [
            'siret' => [
                'nullable',
                'string',
                'size:14',
                'regex:/^[0-9]{14}$/',
            ],
        ];
    }

    /**
     * Validate that customer name is unique for the user.
     *
     * @param Validator $validator
     * @param int $userId
     * @return void
     */
    private function validateUniqueCustomerName(Validator $validator, int $userId): void
    {
        $name = $this->input('name');

        if ($name) {
            $exists = Company::customer()
                ->where('name', $name)
                ->whereRelation('users', function ($query) use ($userId) {
                    $query->where('users.id', $userId);
                })
                ->exists();

            if ($exists) {
                $validator->errors()->add('name', 'validation.unique');
            }
        }
    }

    /**
     * Validate that customer SIRET is unique for the user.
     *
     * @param Validator $validator
     * @param int $userId
     * @return void
     */
    private function validateUniqueCustomerSiret(Validator $validator, int $userId): void
    {
        $siret = $this->input('siret');

        if ($siret) {
            $exists = Company::customer()
                ->where('siret', $siret)
                ->whereRelation('users', function ($query) use ($userId) {
                    $query->where('users.id', $userId);
                })
                ->exists();

            if ($exists) {
                $validator->errors()->add('siret', 'validation.unique');
            }
        }
    }
}
