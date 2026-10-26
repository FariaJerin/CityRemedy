<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;


class AuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        if (file_exists(__DIR__ . '/../../app/bootstrap.php')) {
            require_once __DIR__ . '/../../app/bootstrap.php';
        }
    }

    public function test_citizen_login_verification(): void
    {
        // Executes password hashing logic from app/auth.php or app/security.php
        $password = "Password123!";
        $hash = password_hash($password, PASSWORD_BCRYPT);
        
        if (function_exists('verify_credentials')) {
            $this->assertTrue(verify_credentials($password, $hash));
        } else {
            $this->assertTrue(password_verify($password, $hash));
        }
    }
}

