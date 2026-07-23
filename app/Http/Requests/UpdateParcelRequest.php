<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\DeliveryPriority;
use App\Enums\ParcelType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Rules\SriLankanMobile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateParcelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('parcel'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The sender is fixed once a parcel exists — rebooking under a
            // different customer would corrupt the shipment history.
            'receiver_name' => ['required', 'string', 'min:3', 'max:150'],
            'receiver_phone' => ['required', 'string', new SriLankanMobile],
            'receiver_address' => ['required', 'string', 'max:500'],
            'receiver_city' => ['required', 'string', 'max:100'],
            'receiver_postal_code' => ['nullable', 'string', 'max:10', 'regex:/^\d{5}$/'],
            'pickup_address' => ['required', 'string', 'max:500'],

            'parcel_type' => ['required', Rule::enum(ParcelType::class)],
            'weight' => ['required', 'numeric', 'gt:0', 'max:10000'],
            'length_cm' => ['nullable', 'numeric', 'gt:0', 'max:1000'],
            'width_cm' => ['nullable', 'numeric', 'gt:0', 'max:1000'],
            'height_cm' => ['nullable', 'numeric', 'gt:0', 'max:1000'],

            'delivery_charge' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'cod_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'payment_status' => ['required', Rule::enum(PaymentStatus::class)],
            'priority' => ['required', Rule::enum(DeliveryPriority::class)],

            'special_instructions' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
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
            'receiver_postal_code.regex' => 'The postal code must be 5 digits, for example 10100.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'weight' => 'weight (kg)',
            'cod_amount' => 'cash on delivery amount',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'receiver_phone' => SriLankanMobile::normalize($this->input('receiver_phone')),
            'cod_amount' => $this->input('cod_amount') ?: 0,
        ]);
    }
}
