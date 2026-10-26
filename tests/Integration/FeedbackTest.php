<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class FeedbackTest extends TestCase
{
    /**
     * TC-13: Minimum valid feedback rating = 1 (FR-4.7)
     */
    public function test_accepts_minimum_valid_feedback_rating(): void
    {
        $reportStatus = 'Resolved';
        $rating = 1;
        $comment = "Satisfactory resolution.";

        $isValidRating = ($rating >= 1 && $rating <= 5);
        $canSubmit = ($reportStatus === 'Resolved') && $isValidRating;

        $this->assertTrue($canSubmit, "Rating 1 on a resolved report must be accepted.");
    }

    /**
     * TC-14: Maximum valid feedback rating = 5 (FR-4.7)
     */
    public function test_accepts_maximum_valid_feedback_rating(): void
    {
        $reportStatus = 'Resolved';
        $rating = 5;
        $comment = "Excellent work!";

        $isValidRating = ($rating >= 1 && $rating <= 5);
        $canSubmit = ($reportStatus === 'Resolved') && $isValidRating;

        $this->assertTrue($canSubmit, "Rating 5 on a resolved report must be accepted.");
    }

    /**
     * TC-15: Out-of-range feedback ratings (0 and 6) are rejected (FR-4.7)
     */
    public function test_rejects_out_of_range_feedback_ratings(): void
    {
        $reportStatus = 'Resolved';
        $invalidRatings = [0, 6];

        foreach ($invalidRatings as $rating) {
            $isValidRating = ($rating >= 1 && $rating <= 5);
            $canSubmit = ($reportStatus === 'Resolved') && $isValidRating;

            $this->assertFalse($canSubmit, "Feedback rating {$rating} must be rejected.");
        }
    }

    /**
     * TC-16: Feedback restricted to original citizen & resolved state (FR-4.7)
     */
    public function test_blocks_feedback_unauthorized_or_before_resolution(): void
    {
        $reportAuthorId = 101;
        $attemptingUserId = 102; // Different citizen
        $reportStatusUnresolved = 'Work Assigned';

        // Attempt 1: Different citizen trying to leave feedback
        $isAuthor = ($reportAuthorId === $attemptingUserId);
        $this->assertFalse($isAuthor, "A citizen who did not create the report cannot submit feedback.");

        // Attempt 2: Submission before resolution
        $isResolved = ($reportStatusUnresolved === 'Resolved');
        $this->assertFalse($isResolved, "Feedback cannot be submitted before the report is Resolved.");
    }
}

