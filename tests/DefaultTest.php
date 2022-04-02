<?php
namespace tests;
use Ably\Defaults;
use Ably\Models\ClientOptions;

class DefaultTest extends \PHPUnit\Framework\TestCase {

    public function testFallbackHosts() {
        $expectedFallbackHosts = [
            "a.ably-realtime.com",
            "b.ably-realtime.com",
            "c.ably-realtime.com",
            "d.ably-realtime.com",
            "e.ably-realtime.com"
        ];
        $fallbackHosts = Defaults::$fallbackHosts;
        $this->assertEquals($expectedFallbackHosts, $fallbackHosts);
    }

    public function testEnvironmentFallbackHosts() {
        $expectedFallbackHosts = [
            "sandbox-a-fallback.ably-realtime.com",
            "sandbox-b-fallback.ably-realtime.com",
            "sandbox-c-fallback.ably-realtime.com",
            "sandbox-d-fallback.ably-realtime.com",
            "sandbox-e-fallback.ably-realtime.com"
        ];
        $fallbackHosts = Defaults::getEnvironmentFallbackHosts("sandbox");
        $this->assertEquals($expectedFallbackHosts, $fallbackHosts);
    }
}