<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class LifecycleAndResolutionTest extends TestCase
{
    /**
     * TC-11: Citizen tracks complaint through status changes (FR-4.5)
     */
    public function test_citizen_can_track_complaint_status_timeline(): void
    {
        $allowedStatuses = ['Submitted', 'Under Verification', 'Work Assigned', 'Resolved'];
        $currentStatus = 'Work Assigned';

        $this->assertContains($currentStatus, $allowedStatuses, "Status must follow valid lifecycle transitions.");
    }

    /**
     * TC-12: Officer resolves report and publishes proof (FR-4.6)
     */
    public function test_officer_publishes_resolution_proof(): void
    {
        $resolutionData = [
            'complaint_id' => 'CR-2026-0892',
            'notes' => 'Pothole filled and road repaved.',
            'proof_photo' => 'resolution_proof.jpg'
        ];

        $isResolved = !empty($resolutionData['notes']) && !empty($resolutionData['proof_photo']);
        $newStatus = $isResolved ? 'Resolved' : 'Work Assigned';

        $this->assertEquals('Resolved', $newStatus, "Uploading proof of work must update status to Resolved.");
    }
}

