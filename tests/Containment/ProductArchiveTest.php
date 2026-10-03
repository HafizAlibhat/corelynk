<?php

namespace Tests\Containment;

use Tests\Support\ContainmentTestCase;

/**
 * TEST G -- a delete request against a referenced product must leave the product
 *           physically present and merely deactivate it.
 *
 * Products 7 and 8 were the only CONFIRMED real data loss in this system. They were
 * hard-deleted (ProductModel::$useSoftDeletes is false and `products` has no
 * deleted_at), orphaning quotation lines with no recovery path: binlog is off, there
 * is no audit entry, and both are absent from every surviving dump.
 *
 * Batch 1 added a reference guard covering 5 tables. Phase 2 established that 42
 * tables carry product_id / service_product_id and that three live foreign keys
 * CASCADE on product delete, so the guard failed open for 37 tables. Batch 2A stops
 * enumerating tables and removes the destructive operation from the normal path
 * instead.
 */
final class ProductArchiveTest extends ContainmentTestCase
{
    private function seedReferencedProduct(): array
    {
        $this->db->table('products')->insert([
            'name'         => 'TEST Tactical Knife',
            'code'         => 'TEST-TK-00001',
            'is_active'    => 1,
        ]);
        $productId = (int) $this->db->insertID();

        $this->db->table('quotations')->insert([
            'quote_number' => 'TEST-Q-PRODREF',
            'customer_id'  => 1,
            'status'       => 'draft',
        ]);
        $quotationId = (int) $this->db->insertID();

        $this->db->table('quotation_lines')->insert([
            'quotation_id' => $quotationId,
            'product_id'   => $productId,
            'description'  => 'TEST Tactical Knife',
            'quantity'     => 2,
            'unit_price'   => 29.50,
        ]);
        $lineId = (int) $this->db->insertID();

        return ['product' => $productId, 'quotation' => $quotationId, 'line' => $lineId];
    }

    /**
     * The behavioural guarantee: archive, do not destroy.
     */
    public function testArchivingLeavesTheProductPhysicallyPresentAndInactive(): void
    {
        $ids = $this->seedReferencedProduct();

        // The operation the controller now performs.
        $this->db->table('products')->where('id', $ids['product'])->update(['is_active' => 0]);

        $product = $this->db->table('products')->where('id', $ids['product'])->get()->getRowArray();

        self::assertNotNull($product, 'The product row was physically destroyed.');
        self::assertSame(
            0,
            (int) $product['is_active'],
            'The product was not deactivated, so it remains sellable after a delete request.'
        );
        self::assertSame('TEST-TK-00001', $product['code'], 'Product identity was altered.');
    }

    /**
     * The reason it matters: the referencing document line must still resolve.
     * This is precisely what broke for products 7 and 8.
     */
    public function testHistoricalReferenceStillResolvesAfterArchiving(): void
    {
        $ids = $this->seedReferencedProduct();

        $this->db->table('products')->where('id', $ids['product'])->update(['is_active' => 0]);

        $joined = $this->db->query(
            'SELECT ql.id, p.id AS product_id, p.name
             FROM quotation_lines ql
             INNER JOIN products p ON p.id = ql.product_id
             WHERE ql.id = ?',
            [$ids['line']]
        )->getRowArray();

        self::assertNotNull(
            $joined,
            'The quotation line no longer resolves to a product -- this is an orphaned line, '
            . 'the exact damage caused by the hard delete of products 7 and 8.'
        );
        self::assertSame($ids['product'], (int) $joined['product_id']);
    }

    public function testNoOrphanedLinesAreCreatedByArchiving(): void
    {
        $ids = $this->seedReferencedProduct();

        $this->db->table('products')->where('id', $ids['product'])->update(['is_active' => 0]);

        $orphans = $this->db->query(
            'SELECT COUNT(*) AS n
             FROM quotation_lines ql
             LEFT JOIN products p ON p.id = ql.product_id
             WHERE ql.id = ? AND p.id IS NULL',
            [$ids['line']]
        )->getRowArray();

        self::assertSame(0, (int) $orphans['n'], 'Archiving orphaned a document line.');
    }

    // ------------------------------------------------------ structural guards

    /**
     * The delete endpoint must contain no destructive operation at all. This is the
     * assertion that stops the hard delete from being reintroduced.
     */
    public function testDeleteEndpointPerformsNoPhysicalDeletion(): void
    {
        $body = $this->deleteMethodBody();

        self::assertStringNotContainsString(
            'productModel->delete(',
            $body,
            'Products::delete performs a physical delete again. Referenced products would be '
            . 'destroyed and their document lines orphaned, unrecoverably.'
        );
        self::assertStringNotContainsString(
            '->delete(',
            $body,
            'Products::delete contains a delete() call. The normal UI/API path must only archive. '
            . 'Note this previously also deleted ProductProcessModel rows, destroying process configuration.'
        );
    }

    public function testDeleteEndpointArchivesAndSaysSo(): void
    {
        $body = $this->deleteMethodBody();

        self::assertStringContainsString(
            "'is_active' => 0",
            $body,
            'Products::delete no longer deactivates the product.'
        );
        self::assertStringContainsString(
            'archived',
            $body,
            'The response must tell the user the product was archived, not deleted.'
        );
    }

    /**
     * RBAC must still gate the operation. Batch 1 restored this check after it had
     * been commented out with "Skip permission check for testing", which is how any
     * authenticated user -- including a viewer -- could destroy a product.
     */
    public function testRbacIsStillEnforcedOnTheEndpoint(): void
    {
        $body = $this->deleteMethodBody();

        self::assertStringContainsString(
            "hasPermission('products.delete')",
            $body,
            'The products.delete permission check is missing again.'
        );
        self::assertStringContainsString('403', $body, 'The endpoint no longer answers 403 when unauthorised.');
        self::assertStringNotContainsString(
            'Skip permission check',
            $body,
            'The permission check has been disabled again.'
        );
    }

    private function deleteMethodBody(): string
    {
        $src = $this->sourceOf('app/Controllers/Products.php');

        $start = strpos($src, 'public function delete($id = null)');
        self::assertNotSame(false, $start, 'Products::delete not found.');

        $next = strpos($src, "\n    private function findDocumentReference", $start);
        if ($next === false) {
            $next = $start + 6000;
        }

        return substr($src, $start, $next - $start);
    }
}
