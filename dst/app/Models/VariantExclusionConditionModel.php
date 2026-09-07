<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * VariantExclusionConditionModel
 * 
 * Manages individual attribute conditions within an exclusion rule.
 * 
 * Example:
 * - rule_id: 1, attribute_id: 5 (Shape), attribute_value_id: 12 (Flat)
 * - rule_id: 1, attribute_id: 6 (Type), attribute_value_id: 18 (Billet)
 * 
 * This means: "Exclude variants where BOTH Shape=Flat AND Type=Billet"
 * (within the same exclude/include rule)
 */
class VariantExclusionConditionModel extends Model
{
    protected $table            = 'variant_exclusion_conditions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'rule_id',
        'attribute_id',
        'attribute_value_id',
        'attribute_value_name',
    ];
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = null;
    protected $validationRules  = [
        'rule_id'              => 'required|integer',
        'attribute_id'         => 'required|integer',
        'attribute_value_id'   => 'integer',
        'attribute_value_name' => 'string|max_length[255]',
    ];

    /**
     * Get all conditions for a rule with attribute details
     * @param int $ruleId
     * @return array
     */
    public function getConditionsWithDetails(int $ruleId): array
    {
        $conditions = $this->where('rule_id', $ruleId)->findAll();
        
        $attributeModel = new ProductAttributeModel();
        
        foreach ($conditions as &$cond) {
            $attr = $attributeModel->find($cond['attribute_id']);
            $cond['attribute_name'] = $attr ? ($attr['name'] ?? 'Unknown') : 'Unknown';
            
            // If attribute_value_name is not stored, show the ID
            if (!$cond['attribute_value_name'] && $cond['attribute_value_id']) {
                $cond['attribute_value_name'] = '#' . $cond['attribute_value_id'];
            }
        }
        
        return $conditions;
    }

    /**
     * Add a condition to a rule
     * @param int $ruleId
     * @param int $attributeId
     * @param int|null $attributeValueId
     * @param string|null $attributeValueName
     * @return int|null Condition ID
     */
    public function addCondition(
        int $ruleId,
        int $attributeId,
        ?int $attributeValueId = null,
        ?string $attributeValueName = null
    ): ?int {
        $data = [
            'rule_id'         => $ruleId,
            'attribute_id'    => $attributeId,
            'attribute_value_id'   => $attributeValueId,
            'attribute_value_name' => $attributeValueName ? trim((string)$attributeValueName) : null,
        ];

        if ($this->insert($data)) {
            return $this->getInsertID();
        }

        return null;
    }

    /**
     * Remove a condition from a rule
     * @param int $conditionId
     * @return bool
     */
    public function removeCondition(int $conditionId): bool
    {
        return (bool)$this->delete($conditionId);
    }

    /**
     * Check if a specific condition already exists (avoid duplicates)
     * @param int $ruleId
     * @param int $attributeId
     * @param int|null $attributeValueId
     * @return bool
     */
    public function conditionExists(int $ruleId, int $attributeId, ?int $attributeValueId = null): bool
    {
        $query = $this->where('rule_id', $ruleId)
                      ->where('attribute_id', $attributeId);
        
        if ($attributeValueId !== null) {
            $query->where('attribute_value_id', $attributeValueId);
        } else {
            $query->where('attribute_value_id', null);
        }

        return $query->countAllResults() > 0;
    }

    /**
     * Get all conditions for a rule grouped by attribute for easier display
     * @param int $ruleId
     * @return array [attribute_id => [conditions...]]
     */
    public function getConditionsGroupedByAttribute(int $ruleId): array
    {
        $conditions = $this->where('rule_id', $ruleId)->findAll();
        
        $grouped = [];
        foreach ($conditions as $cond) {
            $attrId = $cond['attribute_id'];
            if (!isset($grouped[$attrId])) {
                $grouped[$attrId] = [];
            }
            $grouped[$attrId][] = $cond;
        }

        return $grouped;
    }

    /**
     * Delete all conditions for a rule
     * @param int $ruleId
     * @return bool
     */
    public function deleteRuleConditions(int $ruleId): bool
    {
        return (bool)$this->where('rule_id', $ruleId)->delete();
    }
}
