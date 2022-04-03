<?php
namespace tests;
use Ably\Defaults;
use Ably\Models\ClientOptions;

class ClientOptionsTest extends \PHPUnit\Framework\TestCase {
    /**
     * @testdox RSC15e RSC15g3
     */
    public function testWithDefaultOptions() {
        $clientOptions = new ClientOptions();
        self::assertEquals('rest.ably.io', $clientOptions->getRestHost());
        self::assertTrue($clientOptions->tls);
        self::assertEquals(443, $clientOptions->tlsPort);
        $fallbackHosts = $clientOptions->getFallbackHosts();
        sort($fallbackHosts);
        $this->assertEquals(Defaults::$fallbackHosts, $fallbackHosts);
    }
}