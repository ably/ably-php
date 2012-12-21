<?php

require_once '../config.php';
require_once '../lib/ably.php';

class ChannelTest extends PHPUnit_Framework_TestCase {

    protected $app;

    protected function setUp() {
        $this->app = Ably::get_instance(array(
            'host' => ABLY_HOST,
            'key'  => ABLY_KEY,
            'debug' => true
        ));
    }

    public function testGetChannel() {
        // testGetChannel
    }
}