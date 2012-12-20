<?php

require_once '../config.php';
require_once '../lib/ably.php';

class RestTest extends PHPUnit_Framework_TestCase {

    protected $ably;

    protected function setUp() {
        $this->ably = Ably::get_instance(array(
            'host' => ABLY_HOST,
            'key'  => ABLY_KEY
        ));
    }

    public function testTime() {
        $this->assertGreaterThanOrEqual(time(), $this->ably->time());
    }
}