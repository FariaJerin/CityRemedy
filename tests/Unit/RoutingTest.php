<?php
use PHPUnit\Framework\TestCase;

final class RoutingTest extends TestCase {
    /** TC-01: Category maps to correct department (FR-4.3) */
    public function test_TC01_category_maps_to_correct_department(): void {
        $this->assertSame('roads', category_department_slug('Road damage'));
        $this->assertSame('lighting', category_department_slug('Streetlight failure'));
        $this->assertSame('water', category_department_slug('Water leakage'));
        $this->assertSame('waste', category_department_slug('Garbage accumulation'));
        $this->assertSame('drainage', category_department_slug('Drainage blockage'));
        $this->assertSame('general', category_department_slug('Other'));
    }
}

