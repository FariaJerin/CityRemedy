<?php
use PHPUnit\Framework\TestCase;

final class WorkflowTest extends TestCase {
    public function test_report_statuses_have_their_expected_display_classes(): void {
        $this->assertSame('status-submitted', status_class('Submitted'));
        $this->assertSame('status-review', status_class('Under Verification'));
        $this->assertSame('status-assigned', status_class('Work Assigned'));
        $this->assertSame('status-progress', status_class('In Progress'));
        $this->assertSame('status-resolved', status_class('Resolved'));
        $this->assertSame('status-resolved', status_class('Closed'));
        $this->assertSame('status-rejected', status_class('Rejected'));
    }

    public function test_unknown_report_status_uses_neutral_class(): void {
        $this->assertSame('status-neutral', status_class('Unexpected'));
    }
}
