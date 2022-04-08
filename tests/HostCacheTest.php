<?php
namespace tests;

use Ably\Defaults;
use Ably\HostCache;

class HostCacheTest extends \PHPUnit\Framework\TestCase {

    public function testHost() {
        $hostCache = new HostCache(3);
        $hostCache->put(Defaults::$restHost);

        self::assertEquals("rest.ably.io", $hostCache->get());
    }

    public function testExpiredHost() {
        $hostCache = new HostCache(1);
        $hostCache->put(Defaults::$restHost);
        sleep(2);
        self::assertEquals("", $hostCache->get());
    }
}
