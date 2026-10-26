<?php
use PHPUnit\Framework\TestCase;

final class ReportTest extends TestCase {

    /** TC-05: Valid report lookup and category check (FR-4.2, FR-4.3) */
    public function test_TC05_valid_report_submission_and_lookup(): void {
        $categoriesList = categories();
        $this->assertIsArray($categoriesList);
        $this->assertContains('Road damage', $categoriesList);

        $slug = category_department_slug('Road damage');
        $this->assertEquals('roads', $slug);

        // Test non-existent complaint lookup
        $complaint = find_complaint('NON_EXISTENT_ID');
        $this->assertNull($complaint);
    }

    /** TC-06: Incomplete report location logic check (FR-4.2) */
    public function test_TC06_similar_complaints_short_location_returns_empty(): void {
        // Location under 4 chars returns empty array immediately
        $result = similar_complaints('Road damage', 'St');
        $this->assertIsArray($result);
        $this->assertEmpty($result);

        // Location >= 4 chars triggers database query execution
        $resultValid = similar_complaints('Road damage', 'Main Street', 'CMP-1001', 5);
        $this->assertIsArray($resultValid);
    }

    /** TC-07: Public resolutions and timeline queries (FR-4.4, FR-4.5) */
    public function test_TC07_public_resolutions_and_timeline(): void {
        $resolutions = public_resolutions(5);
        $this->assertIsArray($resolutions);

        $timeline = complaint_timeline('CMP-1001');
        $this->assertIsArray($timeline);

        $evidence = complaint_evidence('CMP-1001');
        $this->assertIsArray($evidence);
    }

    /** TC-08: Public stats aggregation logic (FR-4.4, NFR-5.4b) */
    public function test_TC08_public_stats_aggregation(): void {
        $stats = public_stats();
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('reviewing', $stats);
        $this->assertArrayHasKey('active', $stats);
        $this->assertArrayHasKey('resolved', $stats);
    }

    /** TC-09: Department complaints filtering (FR-4.4, BR-5.5.1) */
    public function test_TC09_department_complaint_rows_filtering(): void {
        $adminUser = ['role' => 'system_admin', 'department_id' => 1];
        $officerUser = ['role' => 'officer', 'department_id' => 1];

        // System Admin checks
        $openAdmin = department_complaint_rows($adminUser, 'open');
        $resolvedAdmin = department_complaint_rows($adminUser, 'resolved');
        $rejectedAdmin = department_complaint_rows($adminUser, 'rejected');

        $this->assertIsArray($openAdmin);
        $this->assertIsArray($resolvedAdmin);
        $this->assertIsArray($rejectedAdmin);

        // Department Officer check
        $openOfficer = department_complaint_rows($officerUser, 'open');
        $this->assertIsArray($openOfficer);
    }

    /** TC-10: Admin metrics calculation for admin and officer roles (BR-5.5.1, BR-5.5.2) */
    public function test_TC10_admin_metrics_by_role(): void {
        $adminUser = ['role' => 'system_admin', 'department_id' => 1];
        $officerUser = ['role' => 'officer', 'department_id' => 1];

        $adminMetrics = admin_metrics($adminUser);
        $this->assertIsArray($adminMetrics);
        $this->assertArrayHasKey('total', $adminMetrics);
        $this->assertArrayHasKey('awaiting_review', $adminMetrics);

        $officerMetrics = admin_metrics($officerUser);
        $this->assertIsArray($officerMetrics);
        $this->assertArrayHasKey('fast_track', $officerMetrics);
    }
}


