<?php
namespace tests;
use Ably\AblyRest;

require_once dirname(__FILE__) . '/factories/TestOption.php';

class TimeTest extends \PHPUnit_Framework_TestCase {

    protected static $options;
    protected $defaults;

    public static function setUpBeforeClass() {

        self::$options = TestOption::get_instance()->get_opts();

    }

    public static function tearDownAfterClass() {
        TestOption::get_instance()->clear_opts();
    }

    protected function setUp() {

        $options = self::$options;
        $defaults = array(
            'debug' => false,
            'encrypted' => $options['encrypted'],
            'host' => $options['host'],
            'port' => $options['port'],
        );

        $this->defaults = $defaults;
    }

    /**
     * Verify accuracy of time (to within 2 seconds of actual time)
     */
    public function testAccuracyWithTwoSecondVariation() {
        $ably = new AblyRest(array_merge($this->defaults, array(
            'key' => self::$options['first_private_api_key'],
        )));

        $reportedTime = intval($ably->time());
        $actualTime = intval(microtime(true)*1000);

        $this->assertTrue( abs($reportedTime - $actualTime) < 2000 );
    }

    /**
     * Verify time can be obtained without any valid key or token
     */
    public function testTimeWithoutValidKeyToken() {
        $ablyNoAuth = new AblyRest(array_merge( $this->defaults, array(
            'appId' => 'fakeAppId',
        )));

        $reportedTime = $ablyNoAuth->time();

        $this->assertNotNull( $reportedTime );
    }

    /**
     * Verify time fails without valid host
     */
    public function testTimeFailsWithInvalidHost() {
        $ablyInvalidHost = new AblyRest(array_merge( $this->defaults, array(
            'key' => self::$options['first_private_api_key'],
            'host'  => 'this.host.does.not.exist',
        )));

        $this->setExpectedException('Ably\Exceptions\AblyRequestException');
        $reportedTime = $ablyInvalidHost->time();
    }

}