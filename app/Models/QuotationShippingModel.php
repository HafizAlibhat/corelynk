<?php
namespace App\Models;

use CodeIgniter\Model;

class QuotationShippingModel extends Model
{
    protected $table = 'quotation_shipping';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'quotation_id','carrier','service','product_weight','packing_weight','box_weight','shipment_weight',
        'shipping_method','shipping_cost','shipping_cost_currency','shipping_taxable','shipping_tax_rate','shipping_tax_amount','shipping_total',
        'show_to_customer','show_weight_on_pdf','actual_shipping_cost','notes','metadata','created_by','created_at','updated_by','updated_at'
    ];

    public function calculateShipment(array $shippingPayload, array $lines): array
    {
        // product weight
        $productWeight = 0.0;
        foreach ($lines as $ln) {
            $unitWeight = isset($ln['unit_weight']) ? (float)$ln['unit_weight'] : 0.0;
            $qty = isset($ln['quantity']) ? (float)$ln['quantity'] : 0.0;
            $productWeight += $unitWeight * $qty;
        }
        // Accept either 'packing_weight' or the new 'packaging_weight' (single control)
        $packing = 0.0;
        if (isset($shippingPayload['packaging_weight'])) $packing = (float)$shippingPayload['packaging_weight'];
        elseif (isset($shippingPayload['packing_weight'])) $packing = (float)$shippingPayload['packing_weight'];
        $box = isset($shippingPayload['box_weight']) ? (float)$shippingPayload['box_weight'] : 0.0;
        $shipmentWeight = round(max(0, $productWeight + $packing + $box), 3);

        // Default manual values
        $shippingCost = isset($shippingPayload['shipping_cost']) ? (float)$shippingPayload['shipping_cost'] : 0.00;
        $shippingTaxable = !empty($shippingPayload['shipping_taxable']);
        $shippingTaxRate = isset($shippingPayload['shipping_tax_rate']) ? (float)$shippingPayload['shipping_tax_rate'] : 0.0;

        // If a service_id was provided and exists, use its rates to compute shipping cost server-side
        if (!empty($shippingPayload['service_id'])) {
            $serviceId = $shippingPayload['service_id'];
            // support numeric id or legacy "Carrier::Service" string
            if (is_numeric($serviceId)) {
                $svcModel = new \App\Models\ShippingServiceModel();
                $svc = $svcModel->findById((int)$serviceId);
                if ($svc) {
                    $minWeight = isset($svc['min_weight']) ? (float)$svc['min_weight'] : 0.0;
                    $baseRate = isset($svc['base_rate']) ? (float)$svc['base_rate'] : 0.0;
                    $ratePerKg = isset($svc['rate_per_kg']) ? (float)$svc['rate_per_kg'] : 0.0;
                    $chargeWeight = max($minWeight, $shipmentWeight);
                    // compute cost: base + rate_per_kg * chargeWeight
                    $shippingCost = round($baseRate + ($ratePerKg * $chargeWeight), 2);
                    // prefer service-controlled taxable flag if present in metadata (not yet implemented), else keep payload value
                }
            } else {
                // legacy format Carrier::Service - do nothing here, keep manual cost if supplied
            }
        }

        $shippingTaxAmount = 0.0;
        if ($shippingTaxable && $shippingCost > 0) {
            $shippingTaxAmount = round($shippingCost * ($shippingTaxRate / 100.0), 2);
        }
        $shippingTotal = round($shippingCost + $shippingTaxAmount, 2);

        // Expose carrier/service for storage where possible
        $carrier = null;
        $service = null;
        if (!empty($shippingPayload['service_id']) && is_numeric($shippingPayload['service_id'])) {
            $svcModel = new \App\Models\ShippingServiceModel();
            $svc = $svcModel->findById((int)$shippingPayload['service_id']);
            if ($svc) {
                $carrier = $svc['carrier'];
                $service = $svc['service_name'];
            }
        } elseif (!empty($shippingPayload['service_id']) && is_string($shippingPayload['service_id']) && strpos($shippingPayload['service_id'],'::')!==false) {
            list($carrier,$service) = explode('::', $shippingPayload['service_id'], 2) + [null,null];
        }

        return [
            'product_weight' => round($productWeight, 3),
            'packing_weight' => round($packing, 3),
            'box_weight' => round($box, 3),
            'shipment_weight' => $shipmentWeight,
            'shipping_cost' => round($shippingCost, 2),
            'shipping_taxable' => (bool)$shippingTaxable,
            'shipping_tax_rate' => $shippingTaxRate,
            'shipping_tax_amount' => $shippingTaxAmount,
            'shipping_total' => $shippingTotal,
            'carrier' => $carrier,
            'service' => $service,
        ];
    }
}
