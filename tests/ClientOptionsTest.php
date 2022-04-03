<?php
namespace tests;
use Ably\Defaults;
use Ably\Models\ClientOptions;
use http\Env;

/**
 * @testdox RSC15b
 */
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

    /**
     * @testdox RSC15h
     */
    public function testWithProductionEnvironment() {
        $clientOptions = new ClientOptions();
        $clientOptions->environment = "Production";
        self::assertEquals('rest.ably.io', $clientOptions->getRestHost());
        self::assertTrue($clientOptions->tls);
        self::assertEquals(443, $clientOptions->tlsPort);
        $fallbackHosts = $clientOptions->getFallbackHosts();
        sort($fallbackHosts);
        $this->assertEquals(Defaults::$fallbackHosts, $fallbackHosts);
    }

    /**
     * @testdox RSC15g2 RTC1e
     */
    public function testWithCustomEnvironment() {
        $clientOptions = new ClientOptions();
        $clientOptions->environment = "sandbox";
        self::assertEquals('sandbox-rest.ably.io', $clientOptions->getRestHost());
        self::assertTrue($clientOptions->tls);
        self::assertEquals(443, $clientOptions->tlsPort);
        $fallbackHosts = $clientOptions->getFallbackHosts();
        sort($fallbackHosts);
        $this->assertEquals(Defaults::getEnvironmentFallbackHosts('sandbox'), $fallbackHosts);
    }

}