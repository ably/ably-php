<?php

require_once '../config.php';
require_once '../lib/ably.php';

class ChannelTest extends PHPUnit_Framework_TestCase {

    protected $app;

    protected function setUp() {
        $this->app = new Ably(array(
            'host' => ABLY_HOST,
            'key'  => ABLY_KEY,
            'debug' => true
        ));
    }

    public function testChannel() {
        echo '== testChannel()';
        $channel0 = $this->app->channel('my_channel');
        $this->assertEquals('my_channel', $channel0->name);
    }

    public function testChannelHistory() {
        echo '== testChannelHistory()';
        # pending implementation
        $channel0 = $this->app->channel('my_channel');
        $history = $channel0->history();
        $this->assertFalse(true);
    }

    public function testChannelPresence() {
        echo '== testChannelPresence()';
        # pending implementation
        $channel0 = $this->app->channel('my_channel');
        $presence = $channel0->presence();
        $this->assertFalse(true);
    }

    public function testChannelPublish() {
        echo '== testChannelPublish()';
        # pending implementation
        $channel0 = $this->app->channel('my_channel');
        $channel0->publish('caphun', 'this is awesome!');
        $this->assertFalse(true);
    }

    public function testChannelStats() {
        echo '== testChannelStats()';
        # pending implementation
        $channel0 = $this->app->channel('my_channel');
        $stats = $channel0->stats();
        $this->assertFalse(true);
    }

}