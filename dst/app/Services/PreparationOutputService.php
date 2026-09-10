<?php

namespace App\Services;

use App\Models\PreparationComponentModel;
use App\Models\PreparationProfileModel;
use App\Models\PreparationStepModel;

/**
 * Turns prepared material into the finished product.
 *
 * Vendor work does not change a SKU in stock — the blanks we send out come back
 * as the same item, just coated/polished. The identity change happens here: once
 * the LAST step of the preparation profile is done for some quantity, the
 * profile's components are consumed and the finished product is produced into
 * the same location. Without this the sales order line stays short forever even
 * though the goods are physically ready.
 */
class PreparationOutputService
{
    public function __construct(
        private ?PreparationProfileModel $profileModel = null,
        private ?PreparationStepModel $stepModel = null,
        private ?PreparationComponentModel $componentModel = null,
        private ?InventoryService $inventory = null
    ) {
        $this->profileModel ??= new PreparationProfileModel();
        $this->stepModel ??= new PreparationStepModel();
        $this->componentModel ??= new PreparationComponentModel();
        $this->inventory ??= new InventoryService();
    }

    public function isFinalStep(int $productId, int $stepId): bool
    {
        $profiles = $this->profileModel->getByProduct($productId);
        if ($profiles === []) {
            return false;
        }

        $steps = $this->stepModel->getByProfile((int) $profiles[0]['id']);
        if ($steps === []) {
            return false;
        }

        return (int) end($steps)['id'] === $stepId;
    }

    /**
     * @return float Finished quantity produced (0 when this was not the last step).
     */
    public function produceIfFinalStep(int $productId, int $stepId, float $qty, int $locationId, int $userId, int $referenceId = 0): float
    {
        if ($qty <= 0 || $locationId <= 0 || ! $this->isFinalStep($productId, $stepId)) {
            return 0.0;
        }

        $profiles = $this->profileModel->getByProduct($productId);
        $profileId = (int) $profiles[0]['id'];

        $db = \Config\Database::connect();
        $location = $db->table('warehouse_locations')->select('id, warehouse_id')->where('id', $locationId)->get()->getRowArray();
        if (! $location) {
            throw new \RuntimeException('Output location not found.');
        }
        $warehouseId = (int) $location['warehouse_id'];

        foreach ($this->componentModel->getByProfile($profileId) as $component) {
            $componentProductId = (int) ($component['product_id'] ?? 0);
            if ($componentProductId <= 0 || ! empty($component['is_optional'])) {
                continue;
            }

            $this->inventory->issueFromStock(
                $componentProductId,
                $warehouseId,
                $locationId,
                (float) $component['qty_per_unit'] * $qty,
                $referenceId,
                $userId,
                'preparation_consume',
                'preparation',
                (int) ($component['variant_id'] ?? 0) ?: null
            );
        }

        $this->inventory->receiveStock(
            $productId,
            $warehouseId,
            $locationId,
            $qty,
            $referenceId,
            $userId,
            null,
            'preparation_output',
            'preparation'
        );

        return $qty;
    }
}
