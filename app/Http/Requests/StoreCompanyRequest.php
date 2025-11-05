<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
        return [
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048', // Max 2MB
            'name' => 'required|string|max:255|unique:companies,name',
            'legal_form' => 'nullable|string|max:255|in:SARL,SAS,SA,Auto-entrepreneur,EURL,SNC,SCI',
            'siret' => 'nullable|string|size:14|regex:/^[0-9]{14}$/',
            'address' => 'nullable|string',
            'zip_code' => 'nullable|string|max:10',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|string|email|max:255',
            'creation_date' => 'nullable|date',
            'sector' => 'nullable|string|max:255',
        ];
    }
}


