<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\VariantExclusionRuleModel;
use App\Models\VariantExclusionConditionModel;
use App\Models\ProductVariantModel;
use App\Services\VariantExclusionService;

/**
 * VariantExclusionRules Controller
 * 
 * REST API for managing variant exclusion rules.
 * 
 * Endpoints:
 * - POST   /variant-exclusions                    Create new rule
 * - GET    /variant-exclusions?product_id=X      List rules for product
 * - GET    /variant-exclusions/:id                Get one rule with conditions
 * - POST   /variant-exclusions/:id                Update rule metadata
 * - DELETE /variant-exclusions/:id                Delete rule
 * - POST   /variant-exclusions/:id/conditions      Add condition to rule
 * - DELETE /variant-exclusions/conditions/:id     Remove condition
 * - POST   /variant-exclusions/:id/preview       Preview affected variants
 * - POST   /variant-exclusions/:id/toggle        Enable/disable rule
 */
class VariantExclusionRules extends BaseController
{
    protected $ruleModel;
    protected $conditionModel;
    protected $exclusionService;
    protected $variantModel;

    public function __construct()
    {
        $this->ruleModel         = new VariantExclusionRuleModel();
        $this->conditionModel    = new VariantExclusionConditionModel();
        $this->exclusionService  = new VariantExclusionService();
        $this->variantModel      = new ProductVariantModel();
    }

    /**
     * GET /variant-exclusions?product_id=X
     * List all exclusion rules for a product
     */
    public function index()
    {
        if (!$this->requireAuth(false)) {
            return $this->response->setStatusCode(401)->setJSON(['success' => false, 'message' => 'Unauthorized']);
        }

        $productId = (int)($this->request->getGet('product_id') ?? 0);
        if (!$productId) {
            return $this->response->setJSON(['success' => false, 'message' => 'product_id required']);
        }

        try {
            $rules = $this->ruleModel->getRulesByProduct($productId);
            
            // Add condition count and summaries
            foreach ($rules as &$rule) {
                $conds = $this->conditionModel->getConditionsWithDetails((int)$rule['id']);
                $rule['condition_count'] = count($conds);
                $rule['condition_preview'] = implode(' AND ', array_map(static function ($cond) {
                    $attr = trim((string)($cond['attribute_name'] ?? 'Attribute'));
                    $val = trim((string)($cond['attribute_value_name'] ?? ''));
                    return $attr . ' = ' . ($val !== '' ? $val : '(any)');
                }, $conds));
            }

            return $this->response->setJSON([
                'success' => true,
                'data'    => $rules,
                'count'   => count($rules),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Failed to list exclusion rules: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Failed to load rules']);
        }
    }

    /**
     * GET /variant-exclusions/:id
     * Get a single rule with all its conditions
     */
    public function show($id = null)
    {
        if (!$this->requireAuth(false)) {
            return $this->response->setStatusCode(401)->setJSON(['success' => false, 'message' => 'Unauthorized']);
        }

        $ruleId = (int)$id;
        try {
            $rule = $this->ruleModel->getRuleWithConditions($ruleId);
            
            if (!$rule) {
                return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Rule not found']);
            }

            return $this->response->setJSON(['success' => true, 'data' => $rule]);
        } catch (\Throwable $e) {
            log_message('error', 'Failed to get exclusion rule: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Failed to load rule']);
        }
    }

    /**
     * POST /variant-exclusions
     * Create a new exclusion rule
     * 
     * Body: {product_id, name, description, rule_type, is_active}
     */
    public function store()
    {
        if (!$this->requireAuth(false)) {
            return $this->response->setStatusCode(401)->setJSON(['success' => false, 'message' => 'Unauthorized']);
        }

        $post = $this->request->getJSON(true) ?? $this->request->getPost();
        
        $data = [
            'product_id'   => (int)($post['product_id'] ?? 0),
            'name'         => trim((string)($post['name'] ?? '')),
            'description'  => trim((string)($post['description'] ?? '')),
            'rule_type'    => trim((string)($post['rule_type'] ?? 'exclude')),
            'is_active'    => isset($post['is_active']) ? (int)$post['is_active'] : 1,
        ];

        if (!$this->ruleModel->validate($data)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'errors'  => $this->ruleModel->errors(),
            ]);
        }

        try {
            if (!$this->ruleModel->insert($data)) {
                return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Failed to create rule']);
            }

            $ruleId = $this->ruleModel->getInsertID();
            $rule = $this->ruleModel->find($ruleId);

            return $this->response->setStatusCode(201)->setJSON([
                'success' => true,
                'message' => 'Rule created successfully',
                'data'    => $rule,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Failed to create exclusion rule: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Database error']);
        }
    }

    /**
     * PATCH /variant-exclusions/:id
     * Update rule metadata (name, description, rule_type, is_active)
     */
    public function update($id = null)
    {
        if (!$this->requireAuth(false)) {
            return $this->response->setStatusCode(401)->setJSON(['success' => false, 'message' => 'Unauthorized']);
        }

        $ruleId = (int)$id;
        $rule = $this->ruleModel->find($ruleId);
        
        if (!$rule) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Rule not found']);
        }

        $post = $this->request->getJSON(true) ?? $this->request->getPost();
        
        $data = [];
        if (isset($post['name'])) {
            $data['name'] = trim((string)$post['name']);
        }
        if (isset($post['description'])) {
            $data['description'] = trim((string)$post['description']);
        }
        if (isset($post['rule_type'])) {
            $data['rule_type'] = trim((string)$post['rule_type']);
        }
        if (isset($post['is_active'])) {
            $data['is_active'] = (int)$post['is_active'];
        }

        if (empty($data)) {
            return $this->response->setJSON(['success' => true, 'message' => 'No changes', 'data' => $rule]);
        }

        try {
            if (!$this->ruleModel->update($ruleId, $data)) {
                return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Failed to update rule']);
            }

            $updated = $this->ruleModel->find($ruleId);
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Rule updated successfully',
                'data'    => $updated,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Failed to update exclusion rule: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Database error']);
        }
    }

    /**
     * DELETE /variant-exclusions/:id
     * Delete an entire rule with all its conditions
     */
    public function delete($id = null)
    {
        if (!$this->requireAuth(false)) {
            return $this->response->setStatusCode(401)->setJSON(['success' => false, 'message' => 'Unauthorized']);
        }

        $ruleId = (int)$id;
        $rule = $this->ruleModel->find($ruleId);
        
        if (!$rule) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Rule not found']);
        }

        try {
            $this->ruleModel->deleteRuleWithConditions($ruleId);
            return $this->response->setJSON(['success' => true, 'message' => 'Rule deleted successfully']);
        } catch (\Throwable $e) {
            log_message('error', 'Failed to delete exclusion rule: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Database error']);
        }
    }

    /**
     * POST /variant-exclusions/:id/conditions
     * Add a condition to an existing rule
     * 
     * Body: {attribute_id, attribute_value_id, attribute_value_name}
     */
    public function addCondition($id = null)
    {
        if (!$this->requireAuth(false)) {
            return $this->response->setStatusCode(401)->setJSON(['success' => false, 'message' => 'Unauthorized']);
        }

        $ruleId = (int)$id;
        $rule = $this->ruleModel->find($ruleId);
        
        if (!$rule) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Rule not found']);
        }

        $post = $this->request->getJSON(true) ?? $this->request->getPost();
        
        $attributeId = (int)($post['attribute_id'] ?? 0);
        $attributeValueId = isset($post['attribute_value_id']) ? (int)$post['attribute_value_id'] : null;
        $attributeValueName = isset($post['attribute_value_name']) ? trim((string)$post['attribute_value_name']) : null;

        if (!$attributeId) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'attribute_id required']);
        }

        // Check for duplicate condition
        if ($this->conditionModel->conditionExists($ruleId, $attributeId, $attributeValueId)) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'This condition already exists']);
        }

        try {
            $conditionId = $this->conditionModel->addCondition($ruleId, $attributeId, $attributeValueId, $attributeValueName);
            
            if (!$conditionId) {
                return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Failed to add condition']);
            }

            $condition = $this->conditionModel->find($conditionId);
            return $this->response->setStatusCode(201)->setJSON([
                'success' => true,
                'message' => 'Condition added successfully',
                'data'    => $condition,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Failed to add condition: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Database error']);
        }
    }

    /**
     * DELETE /variant-exclusions/conditions/:id
     * Remove a condition from its rule
     */
    public function deleteCondition($id = null)
    {
        if (!$this->requireAuth(false)) {
            return $this->response->setStatusCode(401)->setJSON(['success' => false, 'message' => 'Unauthorized']);
        }

        $conditionId = (int)$id;
        $condition = $this->conditionModel->find($conditionId);
        
        if (!$condition) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Condition not found']);
        }

        try {
            $this->conditionModel->delete($conditionId);
            return $this->response->setJSON(['success' => true, 'message' => 'Condition removed successfully']);
        } catch (\Throwable $e) {
            log_message('error', 'Failed to delete condition: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Database error']);
        }
    }

    /**
     * POST /variant-exclusions/:id/preview
     * Preview which existing variants would be affected by this rule
     */
    public function preview($id = null)
    {
        if (!$this->requireAuth(false)) {
            return $this->response->setStatusCode(401)->setJSON(['success' => false, 'message' => 'Unauthorized']);
        }

        $ruleId = (int)$id;
        $rule = $this->ruleModel->getRuleWithConditions($ruleId);
        
        if (!$rule) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Rule not found']);
        }

        try {
            // Get all variants for this product
            $variants = $this->variantModel->where('product_id', $rule['product_id'])->findAll();
            
            $analysis = $this->exclusionService->getAffectedVariants($ruleId, $variants);

            return $this->response->setJSON([
                'success' => true,
                'affected_count' => $analysis['count'],
                'affected_variants' => $analysis['affected_variants'],
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Failed to preview exclusion rule: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Preview failed']);
        }
    }

    /**
     * POST /variant-exclusions/:id/toggle
     * Toggle rule active/inactive status
     */
    public function toggle($id = null)
    {
        if (!$this->requireAuth(false)) {
            return $this->response->setStatusCode(401)->setJSON(['success' => false, 'message' => 'Unauthorized']);
        }

        $ruleId = (int)$id;
        $rule = $this->ruleModel->find($ruleId);
        
        if (!$rule) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Rule not found']);
        }

        try {
            $newState = $rule['is_active'] ? 0 : 1;
            $this->ruleModel->update($ruleId, ['is_active' => $newState]);
            
            $updated = $this->ruleModel->find($ruleId);
            return $this->response->setJSON([
                'success' => true,
                'message' => ($newState ? 'Rule activated' : 'Rule deactivated'),
                'data'    => $updated,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Failed to toggle exclusion rule: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Database error']);
        }
    }

    /**
     * GET /variant-exclusions/attribute-values/:attributeId
     * Returns possible values for selected attribute
     */
    public function attributeValues($attributeId = null)
    {
        if (!$this->requireAuth(false)) {
            return $this->response->setStatusCode(401)->setJSON(['success' => false, 'message' => 'Unauthorized']);
        }

        $attrId = (int)$attributeId;
        if (!$attrId) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Invalid attribute id']);
        }

        try {
            $db = \Config\Database::connect();
            $values = [];

            if ($db->tableExists('product_attribute_values')) {
                $rows = $db->table('product_attribute_values')
                    ->select('id, value')
                    ->where('attribute_id', $attrId)
                    ->orderBy('value', 'ASC')
                    ->get()
                    ->getResultArray();
                foreach ($rows as $row) {
                    $values[] = [
                        'id' => (int)($row['id'] ?? 0),
                        'name' => trim((string)($row['value'] ?? '')),
                    ];
                }
            } elseif ($db->tableExists('attribute_values')) {
                $rows = $db->table('attribute_values')
                    ->select('id, value')
                    ->where('attribute_id', $attrId)
                    ->orderBy('value', 'ASC')
                    ->get()
                    ->getResultArray();
                foreach ($rows as $row) {
                    $values[] = [
                        'id' => (int)($row['id'] ?? 0),
                        'name' => trim((string)($row['value'] ?? '')),
                    ];
                }
            }

            return $this->response->setJSON(['success' => true, 'data' => $values]);
        } catch (\Throwable $e) {
            log_message('error', 'Failed to load attribute values for exclusions: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Failed to load values']);
        }
    }

    /**
     * Helper: Verify user is authenticated.
     * Respects BaseController::$authDisabled — when auth is globally disabled,
     * all requests are treated as authenticated (dev / demo mode).
     */
    protected function requireAuth($throw = true): bool
    {
        // When auth is globally disabled, skip session check entirely
        if (property_exists($this, 'authDisabled') && $this->authDisabled) {
            return true;
        }
        $userId = session('user_id');
        if (!$userId && $throw) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Unauthorized');
        }
        return (bool)$userId;
    }
}
