<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Rules\SriLankanMobile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('branch'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $branchId = $this->route('branch')->id;

        return [
            'code' => [
                'required', 'string', 'max:20', 'regex:/^[A-Z0-9\-]+$/',
                Rule::unique('branches', 'code')->ignore($branchId)->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10', 'regex:/^\d{5}$/'],
            'contact_number' => ['required', 'string', 'max:20', 'regex:/^0\d{9}$/'],
            'email' => ['nullable', 'email:rfc', 'max:191'],
            'manager_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')
                    ->where('role', UserRole::BranchManager->value)
                    ->whereNull('deleted_at'),
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'The branch code may only contain capital letters, digits and hyphens.',
            'code.unique' => 'This branch code is already in use.',
            'contact_number.regex' => 'Enter a 10-digit Sri Lankan number starting with 0, for example 0112345678.',
            'manager_id.exists' => 'The selected user is not a Branch Manager.',
            'postal_code.regex' => 'The postal code must be 5 digits, for example 10100.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $branch = $this->route('branch');

        $this->merge([
            'code' => is_string($this->code) ? strtoupper(trim($this->code)) : $this->code,
            'contact_number' => SriLankanMobile::normalize($this->input('contact_number'))
                ?? $this->input('contact_number'),
            'email' => is_string($this->email) && $this->email !== ''
                ? mb_strtolower(trim($this->email))
                : null,
            'is_active' => $this->boolean('is_active'),

            // Only a Super Admin may reassign the manager of a branch.
            'manager_id' => $this->user()->isSuperAdmin()
                ? $this->input('manager_id')
                : $branch->manager_id,
        ]);
    }
}
