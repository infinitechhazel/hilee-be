<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StorePartnershipInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Public endpoint — no auth required
        return true;
    }

    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'max:255'],
            'email'   => ['required', 'email', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'type'    => ['required', 'in:retail,distributor,reseller,other'],
            'message' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'  => 'Full name is required.',
            'email.required' => 'Email address is required.',
            'email.email'    => 'Please provide a valid email address.',
            'type.required'  => 'Partnership type is required.',
            'type.in'        => 'Partnership type must be one of: retail, distributor, reseller, other.',
        ];
    }

    // Return JSON errors instead of redirecting
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}