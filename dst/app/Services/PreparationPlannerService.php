<?php

namespace App\Services;

use App\Models\PreparationComponentModel;
use App\Models\PreparationProfileModel;
use App\Models\PreparationStepModel;
use App\Models\ProductModel;

class PreparationPlannerService
{
    private InventoryAvailabilityService $inventoryService;
    private PreparationProfileModel $profileModel;
    private PreparationComponentModel $componentModel;
    private PreparationStepModel $stepModel;
    private ProductModel $productModel;

    public function __construct()
    {
        $this->inventoryService = new InventoryAvailabilityService();
        $this->profileModel = new PreparationProfileModel();
        $this->componentModel = new PreparationComponentModel();
        $this->stepModel = new PreparationStepModel();
        $this->productModel = new ProductModel();
    }

    public function generatePlan(int $productId, float $qty): array
    {
        $qty = max(0.0, (float) $qty);
        $product = $this->productModel->find($productId);
        $productName = (string) ($product['name'] ?? ('Product #' . $productId));

        $availability = $this->inventoryService->getAvailability($productId, null) ?? [
            'on_hand' => 0.0,
            'reserved' => 0.0,
            'available' => 0.0,
        ];

        $availableQty = (float) ($availability['available'] ?? 0);
        if ($availableQty >= $qty) {
            return [
                'product_id' => $productId,
                'product_name' => $productName,
                'requested_qty' => $qty,
                'available_qty' => $availableQty,
                'is_ready' => true,
                'show_panel' => false,
                'status' => 'ready',
                'reason' => 'Enough stock is available.',
                'profile' => null,
                'materials' => [],
                'steps' => [],
                'has_missing_materials' => false,
            ];
        }

        $profile = $this->profileModel
            ->where('product_id', $productId)
            ->where('is_active', 1)
            ->orderBy('id', 'DESC')
            ->first();

        if (! $profile) {
            return [
                'product_id' => $productId,
                'product_name' => $productName,
                'requested_qty' => $qty,
                'available_qty' => $availableQty,
                'is_ready' => false,
                'show_panel' => true,
                'status' => 'no_profile',
                'reason' => 'No preparation profile found for this product.',
                'profile' => null,
                'materials' => [],
                'steps' => [],
                'has_missing_materials' => false,
            ];
        }

        $components = $this->componentModel->getByProfileWithProduct((int) $profile['id']);
        $steps = $this->stepModel->getByProfile((int) $profile['id']);

        $materials = [];
        $materialsOk = true;
        foreach ($components as $component) {
            $componentProductId = (int) ($component['product_id'] ?? 0);
            $componentVariantId = (int) ($component['variant_id'] ?? 0);
            $qtyPerUnit = (float) ($component['qty_per_unit'] ?? 0);
            $requiredQty = round($qty * $qtyPerUnit, 4);

            $componentAvailability = $this->inventoryService->getAvailability($componentProductId, null) ?? [
                'on_hand' => 0.0,
                'reserved' => 0.0,
                'available' => 0.0,
            ];

            $componentAvailable = (float) ($componentAvailability['available'] ?? 0);
            $isOk = $componentAvailable >= $requiredQty;
            if (! $isOk) {
                $materialsOk = false;
            }

            $materialName = (string) ($component['product_name'] ?? ('Product #' . $componentProductId));
            if ($componentVariantId > 0) {
                $variantName = trim((string) ($component['variant_name'] ?? ''));
                $variantArtNumber = trim((string) ($component['variant_art_number'] ?? ''));
                if ($variantName !== '') {
                    $materialName .= ' / ' . $variantName;
                }
                if ($variantArtNumber !== '') {
                    $materialName .= ' [' . $variantArtNumber . ']';
                }
            }

            $materials[] = [
                'product_id' => $componentProductId,
                'variant_id' => $componentVariantId > 0 ? $componentVariantId : null,
                'name' => $materialName,
                'required_qty' => $requiredQty,
                'available_qty' => round($componentAvailable, 4),
                'status' => $isOk ? 'ok' : 'missing',
                'is_optional' => (int) ($component['is_optional'] ?? 0) === 1,
            ];
        }

        $planSteps = [];
        $previousStatus = null;
        foreach ($steps as $index => $step) {
            $status = 'blocked';
            $reason = 'Waiting for previous step.';

            if ($index === 0) {
                if ($materialsOk) {
                    $status = 'ready';
                    $reason = 'Materials are available.';
                } else {
                    $status = 'blocked';
                    $reason = 'Materials are missing.';
                }
            } else {
                if ($previousStatus === 'ready') {
                    $status = 'waiting';
                    $reason = 'Waiting for previous step to start.';
                } else {
                    $status = 'blocked';
                    $reason = 'Previous step is not ready.';
                }
            }

            $planSteps[] = [
                'id' => (int) ($step['id'] ?? 0),
                'step_order' => (int) ($step['step_order'] ?? ($index + 1)),
                'name' => (string) ($step['name'] ?? ('Step ' . ($index + 1))),
                'status' => $status,
                'reason' => $reason,
                'is_optional' => (int) ($step['is_optional'] ?? 0) === 1,
            ];

            $previousStatus = $status;
        }

        return [
            'product_id' => $productId,
            'product_name' => $productName,
            'requested_qty' => $qty,
            'available_qty' => $availableQty,
            'is_ready' => false,
            'show_panel' => true,
            'status' => 'planned',
            'reason' => 'Not enough stock. Preparation is required.',
            'profile' => [
                'id' => (int) ($profile['id'] ?? 0),
                'name' => (string) ($profile['name'] ?? ''),
            ],
            'materials' => $materials,
            'steps' => $planSteps,
            'has_missing_materials' => ! $materialsOk,
        ];
    }
}
