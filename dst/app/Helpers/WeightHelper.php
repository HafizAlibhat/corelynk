<?php

namespace App\Helpers;

class WeightHelper
{
    /**
     * Convert weight to kilograms based on the source unit
     * 
     * @param float $weight The weight value
     * @param string $unit The unit of weight (kg, g, lbs, oz)
     * @return float The weight converted to kilograms
     */
    public static function toKilograms(float $weight, string $unit = 'kg'): float
    {
        if ($weight <= 0) {
            return 0.0;
        }
        
        $unit = strtolower(trim($unit));
        
        return match($unit) {
            'g', 'gram', 'grams' => $weight / 1000.0,
            'kg', 'kilogram', 'kilograms' => $weight,
            'lbs', 'lb', 'pound', 'pounds' => $weight * 0.453592,
            'oz', 'ounce', 'ounces' => $weight * 0.0283495,
            'mg', 'milligram', 'milligrams' => $weight / 1000000.0,
            'ton', 'tons', 'tonne', 'tonnes' => $weight * 1000.0,
            default => $weight, // assume kg if unknown
        };
    }
    
    /**
     * Convert weight from kilograms to another unit
     * 
     * @param float $weight The weight in kilograms
     * @param string $targetUnit The target unit (kg, g, lbs, oz)
     * @return float The weight converted to the target unit
     */
    public static function fromKilograms(float $weight, string $targetUnit = 'kg'): float
    {
        if ($weight <= 0) {
            return 0.0;
        }
        
        $targetUnit = strtolower(trim($targetUnit));
        
        return match($targetUnit) {
            'g', 'gram', 'grams' => $weight * 1000.0,
            'kg', 'kilogram', 'kilograms' => $weight,
            'lbs', 'lb', 'pound', 'pounds' => $weight / 0.453592,
            'oz', 'ounce', 'ounces' => $weight / 0.0283495,
            'mg', 'milligram', 'milligrams' => $weight * 1000000.0,
            'ton', 'tons', 'tonne', 'tonnes' => $weight / 1000.0,
            default => $weight, // assume kg if unknown
        };
    }

    /**
     * Display a shipment weight. When every line of the document shares one
     * weight unit we show that unit (the one the user picked on the product),
     * with the kg equivalent alongside so shipping still has a common base.
     * Mixed units (or none given) fall back to kg, or grams under 1 kg.
     */
    public static function formatShipment(float $kg, ?string $unit = null): string
    {
        $kg = max(0.0, $kg);
        $unit = strtolower(trim((string) $unit));
        $isKg = $unit === '' || in_array($unit, ['kg', 'kgs', 'kilogram', 'kilograms'], true);

        if (! $isKg) {
            $value = self::fromKilograms($kg, $unit);
            $decimals = $value >= 100 ? 0 : 2;

            return number_format($value, $decimals) . ' ' . $unit . ' (' . number_format($kg, 3) . ' kg)';
        }

        if ($kg >= 1) {
            return number_format($kg, 3) . ' kg';
        }

        return number_format($kg * 1000, 0) . ' g';
    }
}
