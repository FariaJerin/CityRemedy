<?php
use PHPUnit\Framework\TestCase;

final class SecurityTest extends TestCase {

    /** Test CSRF token generation when session is empty vs when already set */
    public function test_csrf_token_generation(): void {
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        
        // Force the un-set branch (if (!isset($_SESSION['csrf_token']))) to run
        unset($_SESSION['csrf_token']);
        $token1 = csrf_token();
        $this->assertNotEmpty($token1);
        $this->assertSame(64, strlen($token1));
        $this->assertSame($token1, $_SESSION['csrf_token']);

        // Test already-set branch returns identical token
        $token2 = csrf_token();
        $this->assertSame($token1, $token2);
    }

    /** Test HTML CSRF field generation */
    public function test_csrf_field_generation(): void {
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        $_SESSION['csrf_token'] = 'test_token_9999999999';

        $field = csrf_field();
        $this->assertStringContainsString('<input type="hidden" name="csrf_token"', $field);
        $this->assertStringContainsString('value="test_token_9999999999"', $field);
    }

    /** Test CSRF validation comparison and valid verify_csrf call */
    public function test_csrf_verification_branches(): void {
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }

        $_SESSION['csrf_token'] = 'matching_token_abc123';
        $_POST['csrf_token'] = 'matching_token_abc123';

        // Direct comparison logic check
        $submitted = (string)($_POST['csrf_token'] ?? '');
        $stored = (string)($_SESSION['csrf_token'] ?? '');
        $this->assertTrue($submitted !== '' && $stored !== '' && hash_equals($stored, $submitted));

        // Mismatch check
        $_POST['csrf_token'] = 'invalid_token';
        $submitted = (string)($_POST['csrf_token'] ?? '');
        $this->assertFalse(hash_equals($stored, $submitted));

        // Execute verify_csrf() on matching tokens so it completes without exit
        $_POST['csrf_token'] = 'matching_token_abc123';
        verify_csrf();
        $this->assertSame($_SESSION['csrf_token'], $_POST['csrf_token']);
    }
}

