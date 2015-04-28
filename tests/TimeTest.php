<?php
namespace tests;
use Ably\AblyRest;

require_once __DIR__ . '/factories/TestApp.php';

class TimeTest extends \PHPUnit_Framework_TestCase {

    protected static $testApp;
    protected static $defaultOptions;

    public static function setUpBeforeClass() {
        self::$testApp = new TestApp();
        self::$defaultOptions = self::$testApp->getOptions();
    }

    public static function tearDownAfterClass() {
        self::$testApp->release();
    }

    /**
     * Verify accuracy of time (to within 2 seconds of actual time)
     */
    public function testAccuracyWithTwoSecondVariation() {
        $ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'key' => self::$testApp->getAppKeyDefault()->string,
        ) ) );

        $reportedTime = intval($ably->time());
        $actualTime = intval(microtime(true)*1000);

        $this->assertTrue( abs($reportedTime - $actualTime) < 2000 );
    }

    /**
     * Verify time can be obtained without any valid key or token
     */
    public function testTimeWithoutValidKeyToken() {
        $ablyNoAuth = new AblyRest( self::$defaultOptions );

        $reportedTime = $ablyNoAuth->time();

        $this->assertNotNull( $reportedTime );
    }

    /**
     * Verify time fails without valid host
     */
    public function testTimeFailsWithInvalidHost() {
        $ablyInvalidHost = new AblyRest( array_merge( self::$defaultOptions, array(
            'key' => self::$testApp->getAppKeyDefault()->string,
            'host'  => 'this.host.does.not.exist',
        )));

        $this->setExpectedException('Ably\Exceptions\AblyRequestException');
        $reportedTime = $ablyInvalidHost->time();
    }

}