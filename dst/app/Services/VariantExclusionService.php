<?php

namespace App\Services;

use App\Models\VariantExclusionRuleModel;
use App\Models\VariantExclusionConditionModel;

/**
 * VariantExclusionService
 * 
 * Core business logic for variant exclusion rules.
 * 
 * Determines whether a combination of attribute values should be excluded
 * from variant generation based on configured rules.
 * 
 * How it works:
 * 1. A rule with rule_type='exclude' blocks specific combinations
 * 2. A rule with rule_type='include' ONLY allows specific combinations
 * 3. Conditions within a rule are AND'd (all must match)
 * 4. Multiple rules are OR'd (if ANY rule matches, variant is excluded/rejected)
 * 5. Rules are only checked if is_active = 1
 * 
 * Example Usage:
 * ```php
 * $service = new VariantExclusionService();
 * 
 * $attributes = [
 *     'Shape'  => 'Flat',
 *     'Type'   => 'Billet',
 *     'Color'  => 'Blue'
 * ];
 * 
 * $isExcluded = $service->isVariantExcluded($productId, $attributes);
 * // Returns true if any active exclusion rule matches this combination
 * ```
 */
class VariantExclusionService
{
    protected $ruleModel;
    protected $conditionModel;

    public function __construct()
    {
        $this->ruleModel      = new VariantExclusionRuleModel();
        $this->conditionModel = new VariantExclusionConditionModel();
    }

    /**
     * Core check: Is this variant combination excluded?
     * 
     * @param int $productId
     * @param array $attributes  ['attributeName' => 'attributeValue', ...]
     * @return bool True if should be excluded from generation
     */
    /**
     * @param int $productId
     * @param array $attributes
     * @param array|null $productExcluded Optional: array of per-product excluded combos to use instead of DB
     * @return bool True if should be excluded from generation
     */
    public function isVariantExcluded(int $productId, array $attributes, ?array $productExcluded = null): bool
    {
        // Some installations may not have legacy rule tables yet.
        // If those tables are missing, continue with excluded_combos-only behavior.
        try {
            $rules = $this->ruleModel->getActiveRulesWithConditions($productId);
        } catch (\Throwable $e) {
            $rules = [];
        }

        // Also consider simple per-product excluded_combos JSON (single-attribute exclusions)
        try {
            $ex = null;
            if (is_array($productExcluded)) {
                $ex = $productExcluded;
            } else {
                $db = \Config\Database::connect();
                $prod = $db->table('products')->where('id', $productId)->get()->getRowArray();
                if ($prod && !empty($prod['excluded_combos'])) {
                    $ex = json_decode($prod['excluded_combos'], true) ?? [];
                }
            }

            if (is_array($ex) && count($ex)) {
                $onlyAllowCombos = [];
                $forceAllowCombos = [];
                foreach ($ex as $item) {
                    if (!is_array($item)) continue;
                    $type = (string)($item['type'] ?? '');
                    if (($type === 'only_allow_combo') && isset($item['attributes']) && is_array($item['attributes'])) {
                        $onlyAllowCombos[] = $item['attributes'];
                    }
                    if (($type === 'force_allow_combo') && isset($item['attributes']) && is_array($item['attributes'])) {
                        $forceAllowCombos[] = $item['attributes'];
                    }
                }

                // Manual override mode: explicitly allow a blocked combo.
                foreach ($forceAllowCombos as $allowAttrs) {
                    $allMatch = true;
                    foreach ($allowAttrs as $needAttr => $needVal) {
                        $needAttrNorm = strtolower(trim((string)$needAttr));
                        $needValNorm  = strtolower(trim((string)$needVal));
                        $found = false;
                        foreach ($attributes as $k => $v) {
                            if (strtolower(trim((string)$k)) === $needAttrNorm && strtolower(trim((string)$v)) === $needValNorm) {
                                $found = true;
                                break;
                            }
                        }
                        if (! $found) {
                            $allMatch = false;
                            break;
                        }
                    }
                    if ($allMatch) {
                        return false;
                    }
                }

                // Strict whitelist mode: if Only-Allow combos exist, only those are allowed.
                if (!empty($onlyAllowCombos)) {
                    $matchesIncluded = false;
                    foreach ($onlyAllowCombos as $includeAttrs) {
                        $allMatch = true;
                        foreach ($includeAttrs as $needAttr => $needVal) {
                            $needAttrNorm = strtolower(trim((string)$needAttr));
                            $needValNorm  = strtolower(trim((string)$needVal));
                            $found = false;
                            foreach ($attributes as $k => $v) {
                                if (strtolower(trim((string)$k)) === $needAttrNorm && strtolower(trim((string)$v)) === $needValNorm) {
                                    $found = true;
                                    break;
                                }
                            }
                            if (! $found) {
                                $allMatch = false;
                                break;
                            }
                        }
                        if ($allMatch) {
                            $matchesIncluded = true;
                            break;
                        }
                    }
                    if (! $matchesIncluded) {
                        return true;
                    }
                }

                foreach ($ex as $item) {
                    if (empty($item) || !is_array($item)) continue;

                    $itemType = (string)($item['type'] ?? '');
                    if ($itemType === 'only_allow_combo' || $itemType === 'force_allow_combo') {
                        continue;
                    }

                    // New format: exact combo exclusion
                    // { type: 'combo', attributes: { Color: 'Black', Size: '12x2' } }
                    if (isset($item['attributes']) && is_array($item['attributes'])) {
                        $allMatch = true;
                        foreach ($item['attributes'] as $needAttr => $needVal) {
                            $needAttrNorm = strtolower(trim((string)$needAttr));
                            $needValNorm  = strtolower(trim((string)$needVal));
                            $found = false;
                            foreach ($attributes as $k => $v) {
                                if (strtolower(trim((string)$k)) === $needAttrNorm && strtolower(trim((string)$v)) === $needValNorm) {
                                    $found = true;
                                    break;
                                }
                            }
                            if (! $found) {
                                $allMatch = false;
                                break;
                            }
                        }
                        if ($allMatch) {
                            return true;
                        }
                        continue;
                    }

                    // Legacy/simple format: single value exclusion
                    // { attribute: 'Size', value: '12x2' }
                    $attr = isset($item['attribute']) ? strtolower(trim((string)$item['attribute'])) : null;
                    $val = isset($item['value']) ? strtolower(trim((string)$item['value'])) : null;
                    if (!$attr || $val === null) continue;
                    foreach ($attributes as $k => $v) {
                        if (strtolower(trim((string)$k)) === $attr && strtolower(trim((string)$v)) === $val) {
                            return true;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore DB errors and continue with existing rule logic
        }

        if (empty($rules)) {
            return false;
        }

        $hasIncludeRules = false;
        $matchesAnyInclude = false;

        foreach ($rules as $rule) {
            $ruleMatches = $this->doesRuleMatch($rule, $attributes);

            if (($rule['rule_type'] ?? 'exclude') === 'exclude' && $ruleMatches) {
                return true;
            }

            if (($rule['rule_type'] ?? 'exclude') === 'include') {
                $hasIncludeRules = true;
                if ($ruleMatches) {
                    $matchesAnyInclude = true;
                }
            }
        }

        if ($hasIncludeRules && ! $matchesAnyInclude) {
            return true;
        }

        return false;
    }

    /**
     * Check if a variant combination matches a specific rule
     * 
     * All conditions in a rule must be satisfied (AND logic).
     * 
     * @param array $rule Rule with 'conditions' array
     * @param array $attributes Variant attributes
     * @return bool
     */
    protected function doesRuleMatch(array $rule, array $attributes): bool
    {
        $conditions = $rule['conditions'] ?? [];

        if (empty($conditions)) {
            // Rule with no conditions matches everything
            return true;
        }

        // All conditions must be satisfied (AND)
        foreach ($conditions as $condition) {
            if (!$this->doesConditionMatch($condition, $attributes)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a single condition is satisfied by the variant attributes
     * 
     * @param array $condition
     * @param array $attributes
     * @return bool
     */
    protected function doesConditionMatch(array $condition, array $attributes): bool
    {
        $attributeName = trim((string)($condition['attribute_name'] ?? ''));
        $attributeValueName = $condition['attribute_value_name'] ?? null;

        if (!$attributeName) {
            // Can't match without attribute name
            return false;
        }

        // Check if variant has this attribute
        $variantValue = null;
        foreach ($attributes as $attrName => $attrValue) {
            // Case-insensitive comparison
            if (strtolower(trim((string)$attrName)) === strtolower($attributeName)) {
                $variantValue = $attrValue;
                break;
            }
        }

        if ($variantValue === null) {
            // Variant doesn't have this attribute -> condition not met
            return false;
        }

        // If no specific value is specified in the condition, just having the attribute is enough
        if ($attributeValueName === null || $attributeValueName === '') {
            return true;
        }

        // Check if variant's value matches the condition value (case-insensitive)
        return strtolower(trim((string)$variantValue)) === strtolower(trim((string)$attributeValueName));
    }

    /**
     * Get all exclusion rule summaries for a product (for display in UI)
     * 
     * @param int $productId
     * @return array
     */
    public function getRuleSummaries(int $productId): array
    {
        $rules = $this->ruleModel->getActiveRulesWithConditions($productId);
        $summaries = [];

        foreach ($rules as $rule) {
            $typeLabel = $rule['rule_type'] === 'exclude' ? '❌ EXCLUDE' : '✓ ONLY INCLUDE';
            
            $conditionLabels = [];
            foreach ($rule['conditions'] as $cond) {
                $valueLabel = $cond['attribute_value_name'] ?? '(any value)';
                $conditionLabels[] = "{$cond['attribute_name']} = {$valueLabel}";
            }
            $conditionStr = !empty($conditionLabels) ? implode(' AND ', $conditionLabels) : '(no conditions)';

            $summaries[] = [
                'id'          => $rule['id'],
                'name'        => $rule['name'],
                'type'        => $rule['rule_type'],
                'type_label'  => $typeLabel,
                'conditions'  => $conditionStr,
                'is_active'   => $rule['is_active'],
                'description' => $rule['description'],
            ];
        }

        return $summaries;
    }

    /**
     * Validate that a rule's conditions are logically consistent
     * 
     * @param int $ruleId
     * @return array ['valid' => bool, 'errors' => string[]]
     */
    public function validateRule(int $ruleId): array
    {
        $rule = $this->ruleModel->find($ruleId);
        if (!$rule) {
            return ['valid' => false, 'errors' => ['Rule not found']];
        }

        $conditions = $this->conditionModel->where('rule_id', $ruleId)->findAll();
        $errors = [];

        if (empty($conditions)) {
            $errors[] = 'Rule has no conditions. It will match all variants.';
        }

        // Check for duplicate conditions (same attribute + value)
        $seen = [];
        foreach ($conditions as $cond) {
            $key = $cond['attribute_id'] . '|' . ($cond['attribute_value_id'] ?? 'all');
            if (isset($seen[$key])) {
                $errors[] = "Duplicate condition detected for attribute {$cond['attribute_id']}";
            }
            $seen[$key] = true;
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get detailed analysis of which variants would be affected by a rule
     * (useful for previewing before activation)
     * 
     * @param int $ruleId
     * @param array $allVariants List of existing variants with attributes
     * @return array ['affected_variants' => [...], 'count' => int]
     */
    public function getAffectedVariants(int $ruleId, array $allVariants): array
    {
        $rule = $this->ruleModel->find($ruleId);
        if (!$rule) {
            return ['affected_variants' => [], 'count' => 0];
        }

        $rule['conditions'] = $this->conditionModel->where('rule_id', $ruleId)->findAll();
        $affected = [];

        foreach ($allVariants as $variant) {
            $attrs = [];
            if (!empty($variant['attributes'])) {
                try {
                    $attrs = is_string($variant['attributes'])
                        ? json_decode($variant['attributes'], true)
                        : $variant['attributes'];
                } catch (\Throwable $e) {
                    $attrs = [];
                }
            }

            $ruleMatches = $this->doesRuleMatch($rule, is_array($attrs) ? $attrs : []);
            
            // Check if this rule type would exclude the variant
            if (($rule['rule_type'] === 'exclude' && $ruleMatches) ||
                ($rule['rule_type'] === 'include' && !$ruleMatches)) {
                $affected[] = [
                    'variant_id'    => $variant['id'],
                    'art_number'    => $variant['art_number'] ?? '-',
                    'name'          => $variant['name'] ?? '-',
                    'attributes'    => $attrs,
                ];
            }
        }

        return [
            'affected_variants' => $affected,
            'count'             => count($affected),
        ];
    }
}
