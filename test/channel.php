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

    public function testChannel() {
        # pending implementation
        $this->assertFalse(true);
    }

    public function testChannelHistory() {
        # pending implementation
        $this->assertFalse(true);
    }

    public function testChannelPresence() {
        # pending implementation
        $this->assertFalse(true);
    }

    public function testChannelPresenceHistory() {
        # pending implementation
        $this->assertFalse(true);
    }

    public function testChannelPublish() {
        # pending implementation
        $this->assertFalse(true);
    }

    public function testChannelStats() {
        # pending implementation
        $this->assertFalse(true);
    }

}