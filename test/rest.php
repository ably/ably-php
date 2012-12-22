<?php

require_once '../config.php';
require_once '../lib/ably.php';

class RestTest extends PHPUnit_Framework_TestCase {

    protected $app;

    protected function setUp() {
        $this->app = Ably::rest(array(
            'host' => ABLY_HOST,
            'key'  => ABLY_KEY,
            'debug' => true
        ));
    }

    public function testTime() {
        echo '== testTime()';
        $this->assertGreaterThanOrEqual(time(), $this->app->time());
    }

    public function testHistory() {
        echo '== testHistory()';
        $res = $this->app->history(array(
            'start'     => (time()-3600*24)*1000, // yesterday epoch in milliseconds
            'end'       => time()*1000, // now epoch in milliseconds
            'limit'     => 100,
            'direction' => 'forwards', // backwards and forwards?
            'by'        => 'message', // message, bundle or hour
        ));
        // TODO: do a better assertion once the format of the data return is verified
        $this->assertObjectHasAttribute('name', $res);
    }

    public function testStats() {
        echo '== testStats()';
        $res = $this->app->stats();
        // TODO: do a better assertion once the format of the data return is verified
        $this->assertObjectHasAttribute('published', $res);
    }
}