<?php

use App\Enums\UserRole;

/**
 * Business rules for the courier operation.
 *
 * Everything an operator might reasonably want to change without touching code
 * lives here rather than being scattered through controllers and services.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Self-registration
    |--------------------------------------------------------------------------
    |
    | Public registration creates a staff account with the least privileged
    | role. Set `requires_approval` to true to have new accounts start
    | deactivated until a Super Admin switches them on.
    |
    */

    'registration' => [
        'enabled' => env('COURIER_REGISTRATION_ENABLED', true),
        'default_role' => UserRole::Dispatcher->value,
        'requires_approval' => env('COURIER_REGISTRATION_REQUIRES_APPROVAL', false),
        'requires_email_verification' => env('COURIER_REQUIRE_EMAIL_VERIFICATION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Delivery charge calculation
    |--------------------------------------------------------------------------
    |
    | Charge = base + (chargeable weight above the included allowance × per-kg
    | rate), then multiplied by the priority surcharge and increased by the
    | parcel type handling fee. All amounts are in LKR.
    |
    */

    'pricing' => [
        'currency' => 'LKR',
        'currency_symbol' => 'Rs.',
        'base_charge' => 350.00,
        'included_weight_kg' => 1.0,
        'per_kg_rate' => 120.00,
        'inter_city_surcharge' => 150.00,
        'minimum_charge' => 350.00,
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    */

    'uploads' => [
        'disk' => 'public',
        'max_image_kb' => 4096,
        'max_parcel_images' => 6,
        'image_mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        'paths' => [
            'driver_photos' => 'drivers/photos',
            'user_avatars' => 'users/avatars',
            'parcel_images' => 'parcels/images',
            'signatures' => 'deliveries/signatures',
            'proofs' => 'deliveries/proofs',
            'qr_codes' => 'parcels/qr',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Listing defaults
    |--------------------------------------------------------------------------
    */

    'pagination' => [
        'web' => 15,
        'api' => 25,
        'api_max' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Delivery policy
    |--------------------------------------------------------------------------
    |
    | After this many failed doorstep attempts a parcel is returned to the
    | sender rather than being reassigned again.
    |
    */

    'delivery' => [
        'max_attempts' => 3,
        'auto_return_after_max_attempts' => true,
    ],

];
