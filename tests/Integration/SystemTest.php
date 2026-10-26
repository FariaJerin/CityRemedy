<?php
use PHPUnit\Framework\TestCase;

final class SystemTest extends TestCase {
    /** NF-01: Verify application initialization (NFR-5.1a) */
    public function test_database_factory_is_loaded(): void {
        $this->assertTrue(function_exists('db'));
    }

    /** SEC-01: Verify base URL configuration (NFR-5.3) */
    public function test_application_base_url_is_configured(): void {
        $this->assertNotSame('', APP_BASE_URL);
    }

    /** NF-02: Verify test database connection (NFR-5.2, NFR-5.4b) */
    public function test_database_connection_when_mysql_driver_is_available(): void {
        if (!extension_loaded('pdo_mysql')) {
            $this->markTestSkipped('pdo_mysql is required for the database connection test.');
        }

        $this->assertInstanceOf(PDO::class, db());
    }

    /** SEC-01: Verify application name (NFR-5.3) */
    public function test_application_name_is_configured(): void {
        $this->assertSame('CityRemedy', APP_NAME);
    }

    /** PT-01: Measure response time for category slug routing (NFR-5.1b) */
    public function test_PT01_endpoint_response_time(): void {
        $start = microtime(true);
        category_department_slug('Road damage');
        $durationMs = (microtime(true) - $start) * 1000;
        
        $this->assertLessThan(500, $durationMs, "Execution took {$durationMs}ms (Limit: 500ms)");
    }
}

