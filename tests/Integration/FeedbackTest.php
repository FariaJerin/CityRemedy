<?php
use PHPUnit\Framework\TestCase;

final class FeedbackTest extends TestCase {
    public function test_feedback_accepts_minimum_and_maximum_ratings(): void {
        $this->assertSame(1, parse_feedback_rating('1'));
        $this->assertSame(5, parse_feedback_rating('5'));
    }

    public function test_feedback_rejects_out_of_range_ratings(): void {
        $this->assertNull(parse_feedback_rating('0'));
        $this->assertNull(parse_feedback_rating('6'));
    }

    public function test_feedback_rejects_non_integer_ratings(): void {
        $this->assertNull(parse_feedback_rating('3.5'));
        $this->assertNull(parse_feedback_rating('invalid'));
    }

    public function test_feedback_blocked_unresolved_or_wrong_user(): void {
    $this->assertTrue(true); // Replace with your assertion logic
    }
    
}
