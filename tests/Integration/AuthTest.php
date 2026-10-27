<?php
use PHPUnit\Framework\TestCase;

final class AuthTest extends TestCase {

    protected function setUp(): void {
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
    }

    /** Test current_user() helper */
    // tool-assisted: Qodo; verified against FR-3.1 by Faria Jerin
    public function test_current_user_helper(): void {
        unset($_SESSION['cityremedy_user']);
        $this->assertNull(current_user());

        $_SESSION['cityremedy_user'] = [
            'user_id' => 1,
            'full_name' => 'John Citizen',
            'email' => 'john@example.com',
            'role' => 'citizen',
            'department_id' => null
        ];

        $user = current_user();
        $this->assertIsArray($user);
        $this->assertSame('John Citizen', $user['full_name']);
    }

    /** Test role check helpers */
    // tool-assisted: Qodo; verified against FR-3.2 by Faria Jerin
    public function test_role_check_helpers(): void {
        $_SESSION['cityremedy_user'] = ['role' => 'citizen'];
        $this->assertTrue(is_citizen());
        $this->assertFalse(is_admin());
        $this->assertFalse(is_system_admin());

        $_SESSION['cityremedy_user'] = ['role' => 'department_admin'];
        $this->assertFalse(is_citizen());
        $this->assertTrue(is_admin());
        $this->assertFalse(is_system_admin());

        $_SESSION['cityremedy_user'] = ['role' => 'system_admin'];
        $this->assertFalse(is_citizen());
        $this->assertTrue(is_admin());
        $this->assertTrue(is_system_admin());
    }

    /** Test require_citizen() and require_admin() logic when authenticated */
    // tool-assisted: Qodo; verified against FR-3.3 by Faria Jerin
    public function test_require_auth_helpers(): void {
        $_SESSION['cityremedy_user'] = ['user_id' => 1, 'role' => 'citizen'];
        $cit = require_citizen();
        $this->assertIsArray($cit);

        $_SESSION['cityremedy_user'] = ['user_id' => 2, 'role' => 'system_admin'];
        $adm = require_admin();
        $this->assertIsArray($adm);
    }

    /** Test department level permission checks */
    // tool-assisted: Qodo; verified against FR-3.4 by Faria Jerin
    public function test_department_access_permissions(): void {
        $sysAdmin = ['role' => 'system_admin', 'department_id' => null];
        $deptOfficer = ['role' => 'department_admin', 'department_id' => 2];

        $this->assertTrue(can_access_department($sysAdmin, 1));
        $this->assertTrue(can_access_department($sysAdmin, 2));

        $this->assertTrue(can_access_department($deptOfficer, 2));
        $this->assertFalse(can_access_department($deptOfficer, 1));
    }

    /** Test login_user() and logout_user() session handling safely in CLI */
    // tool-assisted: Qodo; verified against FR-3.5 by Faria Jerin
    public function test_login_and_logout_user(): void {
        $userData = [
            'user_id' => 10,
            'full_name' => 'Officer Smith',
            'email' => 'smith@city.gov',
            'role' => 'department_admin',
            'department_id' => 3,
            'department_name' => 'Water Department'
        ];

        @login_user($userData);
        $this->assertSame('Officer Smith', $_SESSION['cityremedy_user']['full_name']);
        $this->assertSame(3, $_SESSION['cityremedy_user']['department_id']);

        @logout_user();
        $this->assertArrayNotHasKey('cityremedy_user', $_SESSION);
        $this->assertNull(current_user());
    }
}

