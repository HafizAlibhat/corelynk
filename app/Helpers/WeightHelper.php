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
     * Display a weight in kg-or-grams form: 1 kg and above reads in kg,
     * anything lighter reads in grams. The unit the user picked on the
     * product only decides how the value is stored/converted, never how it
     * is displayed, so documents never show huge gram figures.
     *
     * @param float       $kg   Weight already converted to kilograms
     * @param string|null $unit Ignored, kept for existing callers
     */
    public static function formatShipment(float $kg, ?string $unit = null): string
    {
        $kg = max(0.0, $kg);

        if ($kg >= 1) {
            return rtrim(rtrim(number_format($kg, 3, '.', ','), '0'), '.') . ' kg';
        }

        return number_format($kg * 1000, 0) . ' g';
    }
}
