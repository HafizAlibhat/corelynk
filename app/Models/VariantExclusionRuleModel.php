<?php

namespace App\Models;

use CodeIgniter\Model;
use Config\Database;

/**
 * VariantExclusionRuleModel
 * 
 * Manages variant exclusion rules at the rule level (rule name, type, active status).
 * Associated conditions are managed via VariantExclusionConditionModel.
 * 
 * Business Rules:
 * - One product can have multiple exclusion rules
 * - Each rule is "exclude" (skip these combos) or "include" (only these combos)
 * - Conditions are AND'd together within a rule (all must match to trigger)
 * - Multiple rules are OR'd (if any rule matches, variant is excluded)
 * - is_active = 1 means the rule is enforced during variant generation
 */
class VariantExclusionRuleModel extends Model
{
    protected $table            = 'variant_exclusion_rules';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'product_id',
        'name',
        'description',
        'rule_type',
        'is_active',
    ];
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';
    protected $validationRules  = [
        'product_id'  => 'required|integer',
        'name'        => 'required|string|max_length[255]',
        'description' => 'string|max_length[2000]',
        'rule_type'   => 'required|in_list[include,exclude]',
        'is_active'   => 'integer|in_list[0,1]',
    ];
    protected $validationMessages = [
        'product_id' => [
            'required' => 'Product is required',
            'integer'  => 'Product ID must be numeric',
        ],
        'name' => [
            'required'   => 'Rule name is required',
            'max_length' => 'Rule name cannot exceed 255 characters',
        ],
        'rule_type' => [
            'required' => 'Rule type is required',
            'in_list'  => 'Rule type must be "include" or "exclude"',
        ],
    ];

    /**
     * Get all active exclusion rules for a product with their conditions
     * @param int $productId
     * @return array
     */
    public function getActiveRulesWithConditions(int $productId): array
    {
        $rules = $this->where('product_id', $productId)
                      ->where('is_active', 1)
                      ->findAll();

        if (empty($rules)) {
            return [];
        }

        $db = Database::connect();

        foreach ($rules as &$rule) {
            $rule['conditions'] = $db->table('variant_exclusion_conditions vec')
                ->select('vec.*, pa.name as attribute_name')
                ->join('product_attributes pa', 'pa.id = vec.attribute_id', 'left')
                ->where('vec.rule_id', (int)$rule['id'])
                ->orderBy('vec.id', 'ASC')
                ->get()
                ->getResultArray();
        }

        return $rules;
    }

    /**
     * Get a single rule with its conditions
     * @param int $ruleId
     * @return array|null
     */
    public function getRuleWithConditions(int $ruleId): ?array
    {
        $rule = $this->find($ruleId);
        if (!$rule) {
            return null;
        }

        $db = Database::connect();
        $rule['conditions'] = $db->table('variant_exclusion_conditions vec')
            ->select('vec.*, pa.name as attribute_name')
            ->join('product_attributes pa', 'pa.id = vec.attribute_id', 'left')
            ->where('vec.rule_id', $ruleId)
            ->orderBy('vec.id', 'ASC')
            ->get()
            ->getResultArray();

        return $rule;
    }

    /**
     * Get all rules for a product (active or inactive)
     * @param int $productId
     * @return array
     */
    public function getRulesByProduct(int $productId): array
    {
        return $this->where('product_id', $productId)
                    ->orderBy('is_active', 'DESC')
                    ->orderBy('created_at', 'DESC')
                    ->findAll();
    }

    /**
     * Clone a rule (with all conditions)
     * @param int $ruleId
     * @param int $newProductId
     * @return int|null New rule ID
     */
    public function cloneRule(int $ruleId, int $newProductId): ?int
    {
        $rule = $this->find($ruleId);
        if (!$rule) {
            return null;
        }

        $conditionModel = new VariantExclusionConditionModel();
        $conditions = $conditionModel->where('rule_id', $ruleId)->findAll();

        // Insert new rule
        unset($rule['id']);
        $rule['product_id'] = $newProductId;
        $this->insert($rule);
        $newRuleId = $this->getInsertID();

        // Clone conditions
        foreach ($conditions as $condition) {
            unset($condition['id']);
            $condition['rule_id'] = $newRuleId;
            $conditionModel->insert($condition);
        }

        return $newRuleId;
    }

    /**
     * Soft-disable a rule by setting is_active = 0
     * @param int $ruleId
     * @return bool
     */
    public function deactivateRule(int $ruleId): bool
    {
        return (bool)$this->update($ruleId, ['is_active' => 0]);
    }

    /**
     * Enable a previously disabled rule
     * @param int $ruleId
     * @return bool
     */
    public function activateRule(int $ruleId): bool
    {
        return (bool)$this->update($ruleId, ['is_active' => 1]);
    }

    /**
     * Delete a rule and all associated conditions
     * @param int $ruleId
     * @return bool
     */
    public function deleteRuleWithConditions(int $ruleId): bool
    {
        $conditionModel = new VariantExclusionConditionModel();
        $conditionModel->where('rule_id', $ruleId)->delete();
        return (bool)$this->delete($ruleId);
    }

    /**
     * Check if a product has any active exclusion rules
     * @param int $productId
     * @return bool
     */
    public function hasActiveRules(int $productId): bool
    {
        return $this->where('product_id', $productId)
                    ->where('is_active', 1)
                    ->countAllResults() > 0;
    }
}
