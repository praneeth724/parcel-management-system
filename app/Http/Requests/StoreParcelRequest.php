<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\DeliveryPriority;
use App\Enums\ParcelType;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Parcel;
use App\Rules\SriLankanMobile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreParcelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Parcel::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxImages = (int) config('courier.uploads.max_parcel_images');

        return [
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->whereNull('deleted_at')],

            'receiver_name' => ['required', 'string', 'min:3', 'max:150'],
            'receiver_phone' => ['required', 'string', new SriLankanMobile],
            'receiver_address' => ['required', 'string', 'max:500'],
            'receiver_city' => ['required', 'string', 'max:100'],
            'receiver_postal_code' => ['nullable', 'string', 'max:10', 'regex:/^\d{5}$/'],
            'pickup_address' => ['required', 'string', 'max:500'],

            'parcel_type' => ['required', Rule::enum(ParcelType::class)],

            // The specification asks explicitly for numeric, greater than zero.
            'weight' => ['required', 'numeric', 'gt:0', 'max:10000'],
            'length_cm' => ['nullable', 'numeric', 'gt:0', 'max:1000'],
            'width_cm' => ['nullable', 'numeric', 'gt:0', 'max:1000'],
            'height_cm' => ['nullable', 'numeric', 'gt:0', 'max:1000'],

            'delivery_charge' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'cod_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'priority' => ['required', Rule::enum(DeliveryPriority::class)],

            'special_instructions' => ['nullable', 'string', 'max:1000'],

            'images' => ['nullable', 'array', "max:{$maxImages}"],
            'images.*' => [
                'image',
                'mimes:'.implode(',', config('courier.uploads.image_mimes')),
                'max:'.config('courier.uploads.max_image_kb'),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $customer = Customer::find($this->input('customer_id'));

            // A blacklisted or inactive customer must not be able to ship.
            if ($customer && ! $customer->status->canBookParcels()) {
                $validator->errors()->add(
                    'customer_id',
                    "{$customer->full_name} is {$customer->status->label()} and cannot book new shipments."
                );
            }

            // Dimensions are all-or-nothing; a partial set cannot be used to
            // compute volumetric weight.
            $dimensions = [
                $this->input('length_cm'),
                $this->input('width_cm'),
                $this->input('height_cm'),
            ];
            $provided = count(array_filter($dimensions, fn ($v) => filled($v)));

            if ($provided > 0 && $provided < 3) {
                $validator->errors()->add(
                    'length_cm',
                    'Enter all three dimensions (length, width and height) or leave them all blank.'
                );
            }

            // Only cash on delivery collects money from the receiver.
            $method = PaymentMethod::tryFrom((string) $this->input('payment_method'));

            if ($method && ! $method->isCollectedOnDelivery() && (float) $this->input('cod_amount', 0) > 0) {
                $validator->errors()->add(
                    'cod_amount',
                    'A cash-on-delivery amount only applies when the payment method is Cash on Delivery.'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'weight.gt' => 'The parcel weight must be greater than zero.',
            'customer_id.required' => 'Please choose the customer sending this parcel.',
            'receiver_postal_code.regex' => 'The postal code must be 5 digits, for example 10100.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_id' => 'customer',
            'branch_id' => 'branch',
            'weight' => 'weight (kg)',
            'cod_amount' => 'cash on delivery amount',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'receiver_phone' => SriLankanMobile::normalize($this->input('receiver_phone')),
            'cod_amount' => $this->input('cod_amount') ?: 0,
            // Dispatchers book against their own branch; the field is hidden
            // for them and forced here so it cannot be tampered with.
            'branch_id' => $this->user()->isSuperAdmin()
                ? $this->input('branch_id')
                : $this->user()->branch_id,
        ]);
    }

    /**
     * Parcel columns only, without the uploaded files.
     *
     * @return array<string, mixed>
     */
    public function parcelData(): array
    {
        return collect($this->validated())->except(['images'])->all();
    }
}
