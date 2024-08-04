<?php
namespace tests;
use Ably\Defaults;
use Ably\Models\ClientOptions;

class DefaultsTest extends \PHPUnit\Framework\TestCase {

    /**
     * @testdox RSC15h
     */
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

    /**
     * @testdox RSC15i
     */
    public function testEnvironmentFallbackHosts() {
        $expectedFallbackHosts = [
            "lmars-dev-a-fallback.ably-realtime.com",
            "lmars-dev-b-fallback.ably-realtime.com",
            "lmars-dev-c-fallback.ably-realtime.com",
            "lmars-dev-d-fallback.ably-realtime.com",
            "lmars-dev-e-fallback.ably-realtime.com"
        ];
        $fallbackHosts = Defaults::getEnvironmentFallbackHosts("lmars-dev");
        $this->assertEquals($expectedFallbackHosts, $fallbackHosts);
    }
}
