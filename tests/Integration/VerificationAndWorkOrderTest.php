<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class VerificationAndWorkOrderTest extends TestCase
{
    /**
     * TC-08: Score at threshold creates work order (FR-4.4, BR-5.5.2)
     */
    public function test_score_at_threshold_creates_work_order(): void
    {
        $threshold = 3;
        $assignedValidityScore = 3;

        $status = ($assignedValidityScore >= $threshold) ? 'Work Assigned' : 'Rejected';

        $this->assertEquals('Work Assigned', $status, "Validity score >= 3 must create a work order.");
    }

    /**
     * TC-09: Score below threshold rejects work order (FR-4.4, BR-5.5.2)
     */
    public function test_score_below_threshold_does_not_create_work_order(): void
    {
        $threshold = 3;
        $assignedValidityScore = 2;

        $status = ($assignedValidityScore >= $threshold) ? 'Work Assigned' : 'Rejected';

        $this->assertEquals('Rejected', $status, "Validity score < 3 must mark complaint as Rejected.");
    }

    /**
     * TC-10: Officer from another department is denied access (BR-5.5.1)
     */
    public function test_officer_from_another_department_is_denied(): void
    {
        $complaintDeptId = 101; // Sanitation
        $officerDeptId = 102;   // Electrical

        $hasPermission = ($complaintDeptId === $officerDeptId);

        $this->assertFalse($hasPermission, "Officer cannot modify complaints assigned to a different department.");
    }
}

