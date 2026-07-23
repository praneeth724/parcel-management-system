<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Rules\SriLankanMobile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Customer::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:3', 'max:150'],

            // Unique among live rows only: a soft-deleted customer must not
            // permanently block their own NIC from being re-registered.
            'nic_passport' => [
                'required', 'string', 'max:30',
                Rule::unique('customers', 'nic_passport')->whereNull('deleted_at'),
            ],
            'mobile' => [
                'required', 'string', new SriLankanMobile,
                Rule::unique('customers', 'mobile')->whereNull('deleted_at'),
            ],
            'email' => [
                'nullable', 'email:rfc', 'max:191',
                Rule::unique('customers', 'email')->whereNull('deleted_at'),
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
            'nic_passport.unique' => 'A customer with this NIC or passport number already exists.',
            'mobile.unique' => 'A customer with this mobile number already exists.',
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
            'status' => $this->input('status', CustomerStatus::Active->value),

            // Dispatchers and managers can only file a customer under their own
            // branch; the form does not even show the field for them.
            'branch_id' => $this->user()->isSuperAdmin()
                ? $this->input('branch_id')
                : $this->user()->branch_id,
        ]);
    }
}
