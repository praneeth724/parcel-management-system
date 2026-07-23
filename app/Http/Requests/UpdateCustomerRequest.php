<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CustomerStatus;
use App\Rules\SriLankanMobile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('customer'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $customerId = $this->route('customer')->id;

        return [
            'full_name' => ['required', 'string', 'min:3', 'max:150'],
            'nic_passport' => [
                'required', 'string', 'max:30',
                Rule::unique('customers', 'nic_passport')->ignore($customerId)->whereNull('deleted_at'),
            ],
            'mobile' => [
                'required', 'string', new SriLankanMobile,
                Rule::unique('customers', 'mobile')->ignore($customerId)->whereNull('deleted_at'),
            ],
            'email' => [
                'nullable', 'email:rfc', 'max:191',
                Rule::unique('customers', 'email')->ignore($customerId)->whereNull('deleted_at'),
            ],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10', 'regex:/^\d{5}$/'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'status' => ['required', Rule::enum(CustomerStatus::class)],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->whereNull('deleted_at')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nic_passport.unique' => 'Another customer already uses this NIC or passport number.',
            'mobile.unique' => 'Another customer already uses this mobile number.',
            'postal_code.regex' => 'The postal code must be 5 digits, for example 10100.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nic_passport' => 'NIC / passport number',
            'branch_id' => 'branch',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'mobile' => SriLankanMobile::normalize($this->input('mobile')),
            'nic_passport' => is_string($this->nic_passport)
                ? strtoupper(trim($this->nic_passport))
                : $this->nic_passport,
            'email' => is_string($this->email) && $this->email !== ''
                ? mb_strtolower(trim($this->email))
                : null,
            'branch_id' => $this->user()->isSuperAdmin()
                ? $this->input('branch_id')
                : $this->route('customer')->branch_id,
        ]);
    }
}
