<?php

require_once '../config.php';
require_once '../lib/ably.php';

class TimeTest extends PHPUnit_Framework_TestCase {

    protected $defaults;

    protected function setUp() {

        $defaults = array( 'host' => getenv("WEBSOCKET_ADDRESS"), 'debug' => true );

        if (empty($defaults['host'])) {
            $defaults['host'] = "staging-rest.ably.io";
            $defaults['encrypted'] = true;
        } else {
            $defaults['encrypted'] = $defaults['host'] != "localhost";
            $defaults['port'] = $defaults['encrypted'] ? 8081 : 8080;
        }

        $this->defaults = $defaults;
    }

    /**
     * Verify accuracy of time (to within 2 seconds of actual time)
     */
    public function testAccuracyWithTwoSecondVariation() {
        echo '== testAccuracyWithTwoSecondVariation()';
        $ably = new Ably(array_merge($this->defaults, array(
            'host' => defined('ABLY_HOST') ? ABLY_HOST : '',
            'key'  => ABLY_KEY,
        )));

        $reportedTime = intval($ably->time());
        $actualTime = intval(microtime(true)*1000);

        $this->assertTrue( abs($reportedTime - $actualTime) < 2000 );
    }

    /**
     * Verify time can be obtained without any valid key or token
     */
    public function testTimeWithoutValidKeyToken() {
        echo '== testTimeWithoutValidKeyToken()';
        $ablyNoAuth = new Ably(array_merge( $this->defaults, array(
            'appId' => 'fakeAppId',
        )));

        $actualTime = intval(microtime(true)*1000);
        $reportedTime = $ablyNoAuth->time();

        $this->assertGreaterThanOrEqual( $actualTime, $reportedTime );
    }

    /**
     * Verify time fails without valid host
     */
    public function testTimeFailsWithInvalidHost() {
        echo '== testTimeFailsWithInvalidHost()';
        $ablyInvalidHost = new Ably(array_merge( $this->defaults, array(
            'appId' => 'fakeAppId',
            'host'  => 'this.host.does.not.exist',
        )));

        $reportedTime = $ablyInvalidHost->time();

        $this->assertNull( $reportedTime );
    }

}