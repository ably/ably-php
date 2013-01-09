<?php

require_once '../config.php';
require_once '../lib/ably.php';

class ChannelTest extends PHPUnit_Framework_TestCase {

    protected $app;

    protected function setUp() {
        $this->app = new Ably(array(
            'host' => defined('ABLY_HOST') ? ABLY_HOST : '',
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

    public function testChannelPresenceHistory() {
        echo '== testChannelPresenceHistory()';
        # pending implementation
        $channel0 = $this->app->channel('my_channel');
        $history = $channel0->presence_history();
        $this->assertFalse(true);
    }

    public function testChannelPublish() {
        echo '== testChannelPublish()';
        # pending implementation
        $channel0 = $this->app->channel('my_channel');
        $channel0->publish("publish0", TRUE);
        $channel0->publish("publish1", 24);
        $channel0->publish("publish2", 24.234);
        $channel0->publish("publish3", 'This is a string message payload');
        sleep(10);
        $messages = $channel0->history();
        var_dump($messages);
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