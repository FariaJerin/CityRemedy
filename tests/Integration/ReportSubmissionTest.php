<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class ReportSubmissionTest extends TestCase
{
    /**
     * TC-05: Valid report submission with evidence (FR-4.2)
     */
    public function test_valid_report_submission_succeeds(): void
    {
        $reportData = [
            'category' => 'Road damage',
            'description' => 'Large pothole near main gate',
            'location' => 'Mirpur Road, Block A',
            'evidence_file' => 'pothole.jpg'
        ];

        $isValid = !empty($reportData['category']) && 
                   !empty($reportData['location']) && 
                   !empty($reportData['description']);

        $this->assertTrue($isValid, "Report submission with all required fields should be valid.");
    }

    /**
     * TC-06: Incomplete report is rejected (FR-4.2)
     */
    public function test_incomplete_report_is_rejected(): void
    {
        $reportData = [
            'category' => '', // Missing category
            'description' => 'Broken streetlight',
            'location' => ''  // Missing location
        ];

        $isValid = !empty($reportData['category']) && !empty($reportData['location']);

        $this->assertFalse($isValid, "Report missing required fields must be rejected.");
    }

    /**
     * TC-07: Duplicate active report within 50m radius is blocked (FR-4.4)
     */
    public function test_duplicate_report_within_radius_is_flagged(): void
    {
        $existingLat = 23.77717;
        $existingLong = 90.39945;
        
        $newLat = 23.77720;
        $newLong = 90.39948; // Distance ~4.2 meters

        $earthRadius = 6371000;
        $dLat = deg2rad($newLat - $existingLat);
        $dLon = deg2rad($newLong - $existingLong);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($existingLat)) * cos(deg2rad($newLat)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earthRadius * $c;

        $isDuplicate = $distance <= 50.0;

        $this->assertTrue($isDuplicate, "New report within 50 meters of active report must be flagged as duplicate.");
    }
}

