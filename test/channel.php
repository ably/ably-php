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
        $channel0 = $this->app->channel('my_channel');
        $this->assertEquals('my_channel', $channel0->name);
    }

    public function testChannelHistory() {
        # pending implementation
        $channel0 = $this->app->channel('my_channel');
        $history = $channel0->history();
        $this->assertFalse(true);
    }

    public function testChannelPresence() {
        # pending implementation
        $channel0 = $this->app->channel('my_channel');
        $presence = $channel0->presence();
        $this->assertFalse(true);
    }

    public function testChannelPublish() {
        # pending implementation
        $channel0 = $this->app->channel('my_channel');
        $channel0->publish('caphun', 'this is awesome!');
        $this->assertFalse(true);
    }

    public function testChannelStats() {
        # pending implementation
        $channel0 = $this->app->channel('my_channel');
        $stats = $channel0->stats();
        $this->assertFalse(true);
    }

}