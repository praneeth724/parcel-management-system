<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\DriverStatus;
use App\Enums\VehicleType;
use App\Rules\SriLankanMobile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('driver'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $driverId = $this->route('driver')->id;

        return [
            'full_name' => ['required', 'string', 'min:3', 'max:150'],
            'phone' => ['required', 'string', new SriLankanMobile],
            'email' => [
                'nullable', 'email:rfc', 'max:191',
                Rule::unique('drivers', 'email')->ignore($driverId)->whereNull('deleted_at'),
            ],
            'vehicle_number' => [
                'required', 'string', 'max:20',
                Rule::unique('drivers', 'vehicle_number')->ignore($driverId)->whereNull('deleted_at'),
            ],
            'license_number' => [
                'required', 'string', 'max:30',
                Rule::unique('drivers', 'license_number')->ignore($driverId)->whereNull('deleted_at'),
            ],
            'vehicle_type' => ['required', Rule::enum(VehicleType::class)],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->whereNull('deleted_at')],
            'status' => ['required', Rule::enum(DriverStatus::class)],
            'license_expiry' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'photo' => [
                'nullable', 'image',
                'mimes:'.implode(',', config('courier.uploads.image_mimes')),
                'max:'.config('courier.uploads.max_image_kb'),
            ],
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
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['branch_id' => 'branch'];
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
            'branch_id' => $this->user()->isSuperAdmin()
                ? $this->input('branch_id')
                : $this->route('driver')->branch_id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function driverData(): array
    {
        return collect($this->validated())->except(['photo'])->all();
    }
}
