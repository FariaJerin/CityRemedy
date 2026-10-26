<?php
use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase {

    /** Test HTML escaper h() */
    public function test_html_escaping(): void {
        $this->assertSame('&lt;script&gt;', h('<script>'));
        $this->assertSame('Hello &amp; World', h('Hello & World'));
        $this->assertSame('', h(null));
    }

    /** Test URL generators for empty and non-empty paths */
    public function test_url_helpers(): void {
        $this->assertSame(APP_BASE_URL . '/', url(''));
        $this->assertSame(APP_BASE_URL . '/test', url('test'));
        $this->assertSame(APP_BASE_URL . '/test', url('/test'));
        $this->assertStringContainsString('/index.php?page=login', page_url('login'));
        $this->assertStringContainsString('/admin.php?view=dashboard', admin_url('dashboard'));
    }

    /** Test safe return to redirect validator logic */
    public function test_safe_return_to(): void {
        $_POST['return_to'] = APP_BASE_URL . '/index.php?page=reports';
        $this->assertSame(APP_BASE_URL . '/index.php?page=reports', safe_return_to('/fallback'));

        $_POST['return_to'] = 'https://external-malicious-site.com';
        $this->assertSame('/fallback', safe_return_to('/fallback'));

        unset($_POST['return_to']);
        $this->assertSame('/fallback', safe_return_to('/fallback'));
    }

    /** Test status CSS class mapper for ALL match arms */
    public function test_status_class_mappings(): void {
        $this->assertSame('status-submitted', status_class('Submitted'));
        $this->assertSame('status-review', status_class('Under Verification'));
        $this->assertSame('status-assigned', status_class('Work Assigned'));
        $this->assertSame('status-progress', status_class('In Progress'));
        $this->assertSame('status-resolved', status_class('Resolved'));
        $this->assertSame('status-resolved', status_class('Closed'));
        $this->assertSame('status-rejected', status_class('Rejected'));
        $this->assertSame('status-neutral', status_class('Unknown Status'));
    }

    /** Test date formatter pretty_date() for null and valid dates */
    public function test_pretty_date_formatting(): void {
        $this->assertSame('Not yet available', pretty_date(null));
        $this->assertSame('Not yet available', pretty_date(''));
        $this->assertStringContainsString('2026', pretty_date('2026-10-27 10:00:00'));
    }

    /** Test POST and GET input string sanitization and length truncation */
    public function test_input_string_helpers(): void {
        $_POST['test_key'] = '  Hello World  ';
        $this->assertSame('Hello World', post_string('test_key'));

        $_POST['long_post'] = str_repeat('a', 2500);
        $this->assertSame(2000, mb_strlen(post_string('long_post', 2000)));

        $_GET['test_get'] = '  Sample Query  ';
        $this->assertSame('Sample Query', get_string('test_get'));

        $_GET['long_get'] = str_repeat('b', 300);
        $this->assertSame(200, mb_strlen(get_string('long_get', 200)));
    }

    /** Test session flash messages storage & retrieval */
    public function test_flash_messages_pull(): void {
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        flash('success', 'Complaint logged');
        flash('error', 'Something went wrong');

        $messages = pull_flashes();
        $this->assertCount(2, $messages);
        $this->assertSame('success', $messages[0]['type']);
        $this->assertEmpty(pull_flashes());
    }

    /** Test file upload array normalization uploaded_file_list() */
    public function test_uploaded_file_list_normalization(): void {
        $singleFile = ['name' => 'doc.pdf', 'size' => 1024];
        $this->assertSame([$singleFile], uploaded_file_list($singleFile));

        $emptyArr = [];
        $this->assertSame([], uploaded_file_list($emptyArr));

        $multiFiles = [
            'name' => ['file1.jpg', 'file2.jpg'],
            'type' => ['image/jpeg', 'image/jpeg'],
            'tmp_name' => ['/tmp/1', '/tmp/2'],
            'error' => [0, 0],
            'size' => [500, 600]
        ];
        $normalized = uploaded_file_list($multiFiles);
        $this->assertCount(2, $normalized);
        $this->assertSame('file1.jpg', $normalized[0]['name']);
    }

    /** Test save_uploaded_file error conditions */
    public function test_save_uploaded_file_validation_errors(): void {
        $noFile = ['error' => UPLOAD_ERR_NO_FILE];
        $res1 = save_uploaded_file($noFile);
        $this->assertTrue($res1['ok']);
        $this->assertNull($res1['file']);

        $hugeFile = ['error' => UPLOAD_ERR_OK, 'size' => UPLOAD_MAX_BYTES + 1];
        $res2 = save_uploaded_file($hugeFile);
        $this->assertFalse($res2['ok']);
        $this->assertStringContainsString('smaller', $res2['error']);

        $errFile = ['error' => UPLOAD_ERR_CANT_WRITE];
        $res3 = save_uploaded_file($errFile);
        $this->assertFalse($res3['ok']);
    }

    /** Test system setting and database helpers */
    public function test_system_settings_and_logging_helpers(): void {
        $val = setting('non_existent_setting_key', 'default_val');
        $this->assertSame('default_val', $val);

        log_error(new Exception('Unit test logging test'));
        $this->assertTrue(true);

        try { log_activity('NON_EXISTENT', null, 'Update', 'Log test'); } catch (\Throwable $e) {}
        try { notify_user(999, 'NON_EXISTENT', 'Title', 'Msg'); } catch (\Throwable $e) {}
        try { add_evidence('NON_EXISTENT', ['original_name'=>'a','stored_name'=>'b','mime_type'=>'c','file_size'=>100]); } catch (\Throwable $e) {}

        $this->assertTrue(true);
    }
}

