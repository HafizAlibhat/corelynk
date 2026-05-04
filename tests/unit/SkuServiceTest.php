<?php

use PHPUnit\Framework\TestCase;

/**
 * Basic unit tests for SkuService.
 * These are lightweight and assert behavior for missing categories.
 * Note: These tests expect the application DB configuration to be available.
 */
class SkuServiceTest extends TestCase
{
    public function testAllocateSkuThrowsForMissingCategory()
    {
        $this->expectException(Exception::class);
        $service = new \App\Services\SkuService();
        // Use an unlikely category id to trigger not-found
        $service->allocateSku(-999999);
    }
}
