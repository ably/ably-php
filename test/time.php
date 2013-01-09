<?php

require_once '../config.php';
require_once '../lib/ably.php';

class TimeTest extends PHPUnit_Framework_TestCase {

    protected $app;

    protected function setUp() {
        $this->app = new Ably(array(
            'host' => defined('ABLY_HOST') ? ABLY_HOST : '',
            'key'  => ABLY_KEY,
            'debug' => true,
            'encrypted' => false,
            'queryTime' => true,
        ));
    }

    public function testTime() {
        echo '== testTime()';
        $this->assertGreaterThanOrEqual(time(), $this->app->time());
    }

}