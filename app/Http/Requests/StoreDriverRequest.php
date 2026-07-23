<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\DriverStatus;
use App\Enums\VehicleType;
use App\Models\Driver;
use App\Rules\SriLankanMobile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Driver::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:3', 'max:150'],
            'phone' => ['required', 'string', new SriLankanMobile],
            'email' => [
                'nullable', 'email:rfc', 'max:191',
                Rule::unique('drivers', 'email')->whereNull('deleted_at'),
            ],
            'vehicle_number' => [
                'required', 'string', 'max:20',
                Rule::unique('drivers', 'vehicle_number')->whereNull('deleted_at'),
            ],
            'license_number' => [
                'required', 'string', 'max:30',
                Rule::unique('drivers', 'license_number')->whereNull('deleted_at'),
            ],
            'vehicle_type' => ['required', Rule::enum(VehicleType::class)],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->whereNull('deleted_at')],
            'status' => ['required', Rule::enum(DriverStatus::class)],
            'license_expiry' => ['nullable', 'date', 'after:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'photo' => [
                'nullable', 'image',
                'mimes:'.implode(',', config('courier.uploads.image_mimes')),
                'max:'.config('courier.uploads.max_image_kb'),
            ],

            // Optionally create a login account for this driver at the same
            // time, so they can use the driver dashboard.
            'create_account' => ['nullable', 'boolean'],
            'account_email' => [
                'nullable',
                'required_if_accepted:create_account',
                'email:rfc',
                'max:191',
                Rule::unique('users', 'email')->withoutTrashed(),
            ],
            'account_password' => ['nullable', 'required_if_accepted:create_account', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vehicle_number.unique' => 'This vehicle number is already registered to another driver.',
            'license_number.unique' => 'This licence number is already registered to another driver.',
            'license_expiry.after' => 'The licence expiry date must be in the future.',
            'account_email.required_if_accepted' => 'An email address is required to create a login account.',
            'account_password.required_if_accepted' => 'A password is required to create a login account.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'branch_id' => 'branch',
            'account_email' => 'login email',
            'account_password' => 'login password',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => SriLankanMobile::normalize($this->input('phone')),
            'vehicle_number' => is_string($this->vehicle_number)
                ? strtoupper(preg_replace('/\s+/', '', $this->vehicle_number))
                : $this->vehicle_number,
            'license_number' => is_string($this->license_number)
                ? strtoupper(trim($this->license_number))
                : $this->license_number,
            'email' => is_string($this->email) && $this->email !== ''
                ? mb_strtolower(trim($this->email))
                : null,
            'status' => $this->input('status', DriverStatus::Available->value),
            // A Branch Manager may only add drivers to their own branch.
            'branch_id' => $this->user()->isSuperAdmin()
                ? $this->input('branch_id')
                : $this->user()->branch_id,
        ]);
    }

    /**
     * Only the driver columns, without the login-account extras.
     *
     * @return array<string, mixed>
     */
    public function driverData(): array
    {
        return collect($this->validated())
            ->except(['photo', 'create_account', 'account_email', 'account_password'])
            ->all();
    }
}
