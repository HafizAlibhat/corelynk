<?php

namespace App\Controllers;

use App\Models\PreparationComponentModel;
use App\Models\PreparationProfileModel;
use App\Models\PreparationStepModel;
use App\Models\ProductModel;
use App\Models\ProductVariantModel;
use App\Models\StepExecutionOptionModel;
use App\Models\VendorModel;

class PreparationProfiles extends BaseController
{
    private PreparationProfileModel $profileModel;
    private PreparationComponentModel $componentModel;
    private PreparationStepModel $stepModel;
    private StepExecutionOptionModel $optionModel;
    private ProductModel $productModel;
    private ProductVariantModel $variantModel;
    private VendorModel $vendorModel;

    public function __construct()
    {
        $this->profileModel = new PreparationProfileModel();
        $this->componentModel = new PreparationComponentModel();
        $this->stepModel = new PreparationStepModel();
        $this->optionModel = new StepExecutionOptionModel();
        $this->productModel = new ProductModel();
        $this->variantModel = new ProductVariantModel();
        $this->vendorModel = new VendorModel();
    }

    public function index($productId)
    {
        $this->requireAuth();

        $productId = (int) $productId;
        $product = $this->productModel->find($productId);
        if (! $product) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Product not found');
        }

        $data = $this->setPageData([
            'page_title' => 'Preparation - ' . ($product['name'] ?? 'Product'),
            'product' => $product,
            'variant' => null,
            'preparation_profiles' => $this->profileModel->getWithCountsByProduct($productId, true),
        ]);

        return view('preparation_profiles/index', $data);
    }

    public function create($productId)
    {
        $this->requireAuth();

        $productId = (int) $productId;
        $product = $this->productModel->find($productId);
        if (! $product) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Product not found');
        }

        $data = $this->setPageData([
            'page_title' => 'Create Preparation Profile',
            'product' => $product,
            'variant' => null,
            'profile' => null,
            'materials' => [],
            'steps' => [],
            'step_options' => [],
            'material_items' => $this->getMaterialSelectableItems(),
            'vendors' => $this->vendorModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll(),
            'validation' => \Config\Services::validation(),
        ]);

        return view('preparation_profiles/form', $data);
    }

    public function indexVariant($variantId)
    {
        $this->requireAuth();

        $variantId = (int) $variantId;
        $variant = $this->variantModel->find($variantId);
        if (! $variant) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Variant not found');
        }

        $product = $this->productModel->find((int) ($variant['product_id'] ?? 0));
        if (! $product) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Product not found');
        }

        $data = $this->setPageData([
            'page_title' => 'Preparation - Variant ' . ($variant['name'] ?? ('#' . $variantId)),
            'product' => $product,
            'variant' => $variant,
            'preparation_profiles' => $this->profileModel->getWithCountsByVariant($variantId, true),
        ]);

        return view('preparation_profiles/index', $data);
    }

    public function createVariant($variantId)
    {
        $this->requireAuth();

        $variantId = (int) $variantId;
        $variant = $this->variantModel->find($variantId);
        if (! $variant) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Variant not found');
        }

        $product = $this->productModel->find((int) ($variant['product_id'] ?? 0));
        if (! $product) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Product not found');
        }

        $data = $this->setPageData([
            'page_title' => 'Create Variant Preparation Profile',
            'product' => $product,
            'variant' => $variant,
            'profile' => null,
            'materials' => [],
            'steps' => [],
            'step_options' => [],
            'material_items' => $this->getMaterialSelectableItems(),
            'vendors' => $this->vendorModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll(),
            'validation' => \Config\Services::validation(),
        ]);

        return view('preparation_profiles/form', $data);
    }

    public function store()
    {
        $this->requireAuth();

        $payload = $this->buildPayloadFromPost();
        if (! $payload['ok']) {
            return redirect()->back()->withInput()->with('error', $payload['message']);
        }

        $profileData = $payload['profile'];
        $materials = $payload['materials'];
        $steps = $payload['steps'];

        $db = \Config\Database::connect();
        $db->transStart();

        $profileId = $this->profileModel->createProfile($profileData);

        foreach ($materials as $material) {
            $material['profile_id'] = $profileId;
            $this->componentModel->addComponent($material);
        }

        foreach ($steps as $stepData) {
            $stepInsert = [
                'profile_id' => $profileId,
                'step_order' => $stepData['step_order'],
                'name' => $stepData['name'],
                'description' => $stepData['description'],
                'is_optional' => $stepData['is_optional'],
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $stepId = $this->stepModel->addStep($stepInsert);
            foreach ($stepData['options'] as $option) {
                $option['step_id'] = $stepId;
                $this->optionModel->addOption($option);
            }
        }

        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->back()->withInput()->with('error', 'Failed to save preparation profile.');
        }

        $redirectUrl = ! empty($profileData['variant_id'])
            ? base_url('product-variants/' . (int) $profileData['variant_id'] . '/edit')
            : base_url('products/' . (int) $profileData['product_id'] . '?tab=preparation');

        return redirect()->to($redirectUrl)
            ->with('success', 'Preparation profile created successfully.');
    }

    public function edit($id)
    {
        $this->requireAuth();

        $id = (int) $id;
        $profile = $this->profileModel->find($id);
        if (! $profile) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Preparation profile not found');
        }

        $product = $this->productModel->find((int) $profile['product_id']);
        if (! $product) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Product not found');
        }

        $materials = $this->componentModel->getByProfile($id);
        $steps = $this->stepModel->getByProfile($id);
        $stepOptions = $this->optionModel->getByProfileGrouped($id);

        $data = $this->setPageData([
            'page_title' => 'Edit Preparation Profile',
            'product' => $product,
            'variant' => !empty($profile['variant_id']) ? $this->variantModel->find((int) $profile['variant_id']) : null,
            'profile' => $profile,
            'materials' => $materials,
            'steps' => $steps,
            'step_options' => $stepOptions,
            'material_items' => $this->getMaterialSelectableItems(),
            'vendors' => $this->vendorModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll(),
            'validation' => \Config\Services::validation(),
        ]);

        return view('preparation_profiles/form', $data);
    }

    public function update($id)
    {
        $this->requireAuth();

        $id = (int) $id;
        $existing = $this->profileModel->find($id);
        if (! $existing) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Preparation profile not found');
        }

        $payload = $this->buildPayloadFromPost((int) $existing['product_id'], isset($existing['variant_id']) ? (int) $existing['variant_id'] : null);
        if (! $payload['ok']) {
            return redirect()->back()->withInput()->with('error', $payload['message']);
        }

        $profileData = $payload['profile'];
        $materials = $payload['materials'];
        $steps = $payload['steps'];

        $db = \Config\Database::connect();
        $db->transStart();

        $this->profileModel->updateProfile($id, [
            'name' => $this->generateProfileName((int) $existing['product_id'], isset($existing['variant_id']) ? (int) $existing['variant_id'] : null),
            'description' => $profileData['description'],
        ]);

        $existingSteps = $this->stepModel->select('id')->where('profile_id', $id)->findAll();
        $existingStepIds = array_map(static fn ($row) => (int) $row['id'], $existingSteps);

        $this->optionModel->deleteByStepIds($existingStepIds);
        $this->componentModel->deleteByProfile($id);
        $this->stepModel->deleteByProfile($id);

        foreach ($materials as $material) {
            $material['profile_id'] = $id;
            $this->componentModel->addComponent($material);
        }

        foreach ($steps as $stepData) {
            $stepInsert = [
                'profile_id' => $id,
                'step_order' => $stepData['step_order'],
                'name' => $stepData['name'],
                'description' => $stepData['description'],
                'is_optional' => $stepData['is_optional'],
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $stepId = $this->stepModel->addStep($stepInsert);
            foreach ($stepData['options'] as $option) {
                $option['step_id'] = $stepId;
                $this->optionModel->addOption($option);
            }
        }

        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->back()->withInput()->with('error', 'Failed to update preparation profile.');
        }

        $redirectUrl = ! empty($existing['variant_id'])
            ? base_url('product-variants/' . (int) $existing['variant_id'] . '/edit')
            : base_url('products/' . (int) $existing['product_id'] . '?tab=preparation');

        return redirect()->to($redirectUrl)
            ->with('success', 'Preparation profile updated successfully.');
    }

    public function delete($id)
    {
        $this->requireAuth();

        $id = (int) $id;
        $profile = $this->profileModel->find($id);
        if (! $profile) {
            return redirect()->back()->with('error', 'Preparation profile not found.');
        }

        $this->profileModel->softDeleteProfile($id);

        $redirectUrl = ! empty($profile['variant_id'])
            ? base_url('product-variants/' . (int) $profile['variant_id'] . '/edit')
            : base_url('products/' . (int) $profile['product_id'] . '?tab=preparation');

        return redirect()->to($redirectUrl)
            ->with('success', 'Preparation profile deleted successfully.');
    }

    private function buildPayloadFromPost(?int $forcedProductId = null, ?int $forcedVariantId = null): array
    {
        $productId = $forcedProductId ?? (int) ($this->request->getPost('product_id') ?? 0);
        $variantId = $forcedVariantId ?? (int) ($this->request->getPost('variant_id') ?? 0);
        $description = trim((string) ($this->request->getPost('description') ?? ''));

        if ($productId <= 0 || ! $this->productModel->find($productId)) {
            return ['ok' => false, 'message' => 'Invalid product selected.'];
        }

        if ($variantId > 0) {
            if (! \Config\Database::connect()->fieldExists('variant_id', 'preparation_profiles')) {
                return ['ok' => false, 'message' => 'Variant preparation is not enabled in database yet. Please run the latest migration.'];
            }

            $variant = $this->variantModel->find($variantId);
            if (! $variant) {
                return ['ok' => false, 'message' => 'Invalid variant selected.'];
            }
            if ((int) ($variant['product_id'] ?? 0) !== $productId) {
                return ['ok' => false, 'message' => 'Variant does not belong to selected product.'];
            }
        }

        $generatedName = $this->generateProfileName($productId, $variantId > 0 ? $variantId : null);

        $materialProductIds = $this->request->getPost('material_product_id') ?? [];
        $materialQty = $this->request->getPost('material_qty_per_unit') ?? [];
        $materialOptional = $this->request->getPost('material_is_optional') ?? [];

        $materials = [];
        foreach ((array) $materialProductIds as $index => $materialProductIdRaw) {
            $parsedMaterial = $this->parseMaterialSelection((string) $materialProductIdRaw);
            $materialProductId = (int) ($parsedMaterial['product_id'] ?? 0);
            $materialVariantId = isset($parsedMaterial['variant_id']) ? (int) ($parsedMaterial['variant_id'] ?? 0) : 0;
            $qty = isset($materialQty[$index]) ? (float) $materialQty[$index] : 0;

            if ($materialProductId <= 0) {
                continue;
            }
            if ($qty <= 0) {
                return ['ok' => false, 'message' => 'Each material must have qty per unit greater than 0.'];
            }

            $materials[] = [
                'product_id' => $materialProductId,
                'variant_id' => $materialVariantId > 0 ? $materialVariantId : null,
                'qty_per_unit' => number_format($qty, 4, '.', ''),
                'is_optional' => isset($materialOptional[$index]) ? 1 : 0,
                'created_at' => date('Y-m-d H:i:s'),
            ];
        }

        if (count($materials) < 1) {
            return ['ok' => false, 'message' => 'Profile must have at least 1 material.'];
        }

        $stepNames = $this->request->getPost('step_name') ?? [];
        $stepOrders = $this->request->getPost('step_order') ?? [];
        $stepDescriptions = $this->request->getPost('step_description') ?? [];
        $stepOptional = $this->request->getPost('step_is_optional') ?? [];

        $steps = [];
        foreach ((array) $stepNames as $index => $stepNameRaw) {
            $stepName = trim((string) $stepNameRaw);
            $stepOrder = isset($stepOrders[$index]) ? (int) $stepOrders[$index] : 0;
            $stepDescription = trim((string) ($stepDescriptions[$index] ?? ''));

            if ($stepName === '') {
                continue;
            }
            if ($stepOrder <= 0) {
                return ['ok' => false, 'message' => 'Each step must have a valid step order greater than 0.'];
            }

            $inhouseSelected = isset(($this->request->getPost('execution_inhouse') ?? [])[$index]);
            $vendorSelected = isset(($this->request->getPost('execution_vendor') ?? [])[$index]);
            $vendorId = (int) (($this->request->getPost('execution_vendor_id') ?? [])[$index] ?? 0);
            $notes = trim((string) (($this->request->getPost('execution_notes') ?? [])[$index] ?? ''));
            $defaultType = (string) (($this->request->getPost('execution_default') ?? [])[$index] ?? '');

            $options = [];
            if ($inhouseSelected) {
                $options[] = [
                    'execution_type' => 'inhouse',
                    'vendor_id' => null,
                    'notes' => $notes !== '' ? $notes : null,
                    'is_default' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                ];
            }

            if ($vendorSelected) {
                if ($vendorId <= 0) {
                    return ['ok' => false, 'message' => 'Vendor must be selected when vendor option is checked.'];
                }
                $options[] = [
                    'execution_type' => 'vendor',
                    'vendor_id' => $vendorId,
                    'notes' => $notes !== '' ? $notes : null,
                    'is_default' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                ];
            }

            if (count($options) < 1) {
                return ['ok' => false, 'message' => 'Each step must have at least 1 execution option.'];
            }

            foreach ($options as $optionIndex => $option) {
                if ($defaultType !== '' && $option['execution_type'] === $defaultType) {
                    $options[$optionIndex]['is_default'] = 1;
                }
            }

            $defaultSet = false;
            foreach ($options as $option) {
                if ((int) $option['is_default'] === 1) {
                    $defaultSet = true;
                    break;
                }
            }
            if (! $defaultSet) {
                $options[0]['is_default'] = 1;
            }

            $steps[] = [
                'step_order' => $stepOrder,
                'name' => $stepName,
                'description' => $stepDescription !== '' ? $stepDescription : null,
                'is_optional' => isset($stepOptional[$index]) ? 1 : 0,
                'options' => $options,
            ];
        }

        if (count($steps) < 1) {
            return ['ok' => false, 'message' => 'Profile must have at least 1 step.'];
        }

        usort($steps, static fn ($a, $b) => ((int) $a['step_order']) <=> ((int) $b['step_order']));

        return [
            'ok' => true,
            'profile' => [
                'product_id' => $productId,
                'variant_id' => $variantId > 0 ? $variantId : null,
                'name' => $generatedName,
                'description' => $description !== '' ? $description : null,
                'is_active' => 1,
            ],
            'materials' => $materials,
            'steps' => $steps,
        ];
    }

    private function generateProfileName(int $productId, ?int $variantId = null): string
    {
        $product = $this->productModel->find($productId);
        $productName = trim((string) ($product['name'] ?? ('Product #' . $productId)));

        if ($variantId !== null && $variantId > 0) {
            $variant = $this->variantModel->find($variantId);
            $variantName = trim((string) ($variant['name'] ?? ('Variant #' . $variantId)));
            return $productName . ' / ' . $variantName;
        }

        return $productName;
    }

    private function getMaterialSelectableItems(): array
    {
        $items = [];

        $products = $this->productModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
        foreach ($products as $product) {
            $productId = (int) ($product['id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }

            $productName = trim((string) ($product['name'] ?? ''));
            $productCode = trim((string) ($product['code'] ?? ''));
            $items[] = [
                'value' => 'product:' . $productId,
                'label' => $productName . ' (' . ($productCode !== '' ? $productCode : '-') . ')',
                'group' => 'Products',
            ];
        }

        $variantRows = $this->variantModel
            ->select('product_variants.id, product_variants.product_id, product_variants.name, product_variants.art_number, products.name AS product_name, products.code AS product_code')
            ->join('products', 'products.id = product_variants.product_id', 'left')
            ->orderBy('products.name', 'ASC')
            ->orderBy('product_variants.name', 'ASC')
            ->findAll();

        foreach ($variantRows as $variant) {
            $variantId = (int) ($variant['id'] ?? 0);
            if ($variantId <= 0) {
                continue;
            }

            $productName = trim((string) ($variant['product_name'] ?? 'Product'));
            $variantName = trim((string) ($variant['name'] ?? ('Variant #' . $variantId)));
            $artNumber = trim((string) ($variant['art_number'] ?? ''));

            $label = $productName . ' / ' . $variantName;
            if ($artNumber !== '') {
                $label .= ' [' . $artNumber . ']';
            }

            $items[] = [
                'value' => 'variant:' . $variantId,
                'label' => $label,
                'group' => 'Variant Products',
            ];
        }

        return $items;
    }

    private function parseMaterialSelection(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['product_id' => 0, 'variant_id' => null];
        }

        if (strpos($raw, 'variant:') === 0) {
            $variantId = (int) substr($raw, 8);
            if ($variantId <= 0) {
                return ['product_id' => 0, 'variant_id' => null];
            }

            $variant = $this->variantModel->find($variantId);
            if (! $variant) {
                return ['product_id' => 0, 'variant_id' => null];
            }

            return [
                'product_id' => (int) ($variant['product_id'] ?? 0),
                'variant_id' => $variantId,
            ];
        }

        if (strpos($raw, 'product:') === 0) {
            $productId = (int) substr($raw, 8);
            return ['product_id' => $productId, 'variant_id' => null];
        }

        $productId = (int) $raw;
        return ['product_id' => $productId, 'variant_id' => null];
    }
}
