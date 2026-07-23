<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DeliveryPriority;
use App\Enums\ParcelType;

/**
 * Works out what to charge for a shipment.
 *
 * Charge = (base + weight surcharge + inter-city surcharge)
 *          × priority multiplier
 *          + parcel-type handling fee,
 * floored at the configured minimum.
 */
class PricingService
{
    /**
     * @return array{
     *     base: float,
     *     weight_surcharge: float,
     *     city_surcharge: float,
     *     priority_multiplier: float,
     *     handling_fee: float,
     *     chargeable_weight: float,
     *     total: float
     * }
     */
    public function breakdown(
        float $weightKg,
        DeliveryPriority $priority,
        ParcelType $type,
        ?string $originCity = null,
        ?string $destinationCity = null,
        ?float $lengthCm = null,
        ?float $widthCm = null,
        ?float $heightCm = null,
    ): array {
        $config = config('courier.pricing');

        $chargeableWeight = $this->chargeableWeight($weightKg, $lengthCm, $widthCm, $heightCm);

        $base = (float) $config['base_charge'];

        // Only the weight above the included allowance is billed.
        $billableWeight = max(0.0, $chargeableWeight - (float) $config['included_weight_kg']);
        $weightSurcharge = ceil($billableWeight) * (float) $config['per_kg_rate'];

        $citySurcharge = $this->isInterCity($originCity, $destinationCity)
            ? (float) $config['inter_city_surcharge']
            : 0.0;

        $multiplier = $priority->chargeMultiplier();
        $handlingFee = $type->handlingFee();

        $total = (($base + $weightSurcharge + $citySurcharge) * $multiplier) + $handlingFee;
        $total = max($total, (float) $config['minimum_charge']);

        return [
            'base' => round($base, 2),
            'weight_surcharge' => round($weightSurcharge, 2),
            'city_surcharge' => round($citySurcharge, 2),
            'priority_multiplier' => $multiplier,
            'handling_fee' => round($handlingFee, 2),
            'chargeable_weight' => $chargeableWeight,
            'total' => round($total, 2),
        ];
    }

    public function calculate(
        float $weightKg,
        DeliveryPriority $priority,
        ParcelType $type,
        ?string $originCity = null,
        ?string $destinationCity = null,
        ?float $lengthCm = null,
        ?float $widthCm = null,
        ?float $heightCm = null,
    ): float {
        return $this->breakdown(
            $weightKg, $priority, $type, $originCity, $destinationCity,
            $lengthCm, $widthCm, $heightCm
        )['total'];
    }

    /**
     * Carriers bill on actual or volumetric weight, whichever is greater.
     * The 5000 divisor is the standard road/air freight convention.
     */
    public function chargeableWeight(
        float $weightKg,
        ?float $lengthCm,
        ?float $widthCm,
        ?float $heightCm,
    ): float {
        if (! $lengthCm || ! $widthCm || ! $heightCm) {
            return round($weightKg, 3);
        }

        $volumetric = ($lengthCm * $widthCm * $heightCm) / 5000;

        return round(max($weightKg, $volumetric), 3);
    }

    private function isInterCity(?string $origin, ?string $destination): bool
    {
        if (blank($origin) || blank($destination)) {
            return false;
        }

        return mb_strtolower(trim($origin)) !== mb_strtolower(trim($destination));
    }

    public function format(float $amount): string
    {
        return config('courier.pricing.currency_symbol').' '.number_format($amount, 2);
    }
}
