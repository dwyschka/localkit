<?php

namespace Tests\Unit;

use App\Petkit\OutputSanitizer;
use Tests\TestCase;

class OutputSanitizerTest extends TestCase
{
    public function test_sanitizer_removes_configured_password()
    {
        config(['petkit.telnet_password' => 's3cr3t']);

        $in = "Welcome\nPassword: s3cr3t\n# ";
        $out = OutputSanitizer::sanitize($in);

        $this->assertStringNotContainsString('s3cr3t', $out);
        $this->assertStringContainsString('Welcome', $out);
        $this->assertStringContainsString('Password:', $out);
        $this->assertStringContainsString('[REDACTED]', $out);
    }

    public function test_sanitizer_noop_when_no_password()
    {
        config(['petkit.telnet_password' => null]);

        $in = "hello world";
        $this->assertSame($in, OutputSanitizer::sanitize($in));
    }
}
