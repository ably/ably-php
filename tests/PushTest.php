<?php
namespace tests;
use Ably\AblyRest;

require_once __DIR__ . '/factories/TestApp.php';


class PushTest extends \PHPUnit_Framework_TestCase {
    protected static $testApp;
    protected static $defaultOptions;
    protected static $ably;

    public static function setUpBeforeClass() {
        self::$testApp = new TestApp();
        self::$defaultOptions = self::$testApp->getOptions();
        self::$ably = new AblyRest( array_merge( self::$defaultOptions, [
            'key' => self::$testApp->getAppKeyDefault()->string,
        ] ) );
    }

    public static function tearDownAfterClass() {
        self::$testApp->release();
    }

    /**
     * RSH1a
     */
    public function testAdminPublish() {
        $recipient = [ 'clientId' => 'ablyChannel' ];
        $data = [ 'data' => [ 'foo' => 'bar' ] ];

        $res = self::$ably->push->admin->publish( $recipient, $data , true );
        $this->assertEquals($res['info']['http_code'] , 204 );

        $this->expectException(\InvalidArgumentException::class);
        self::$ably->push->admin->publish( [], $data );
        self::$ably->push->admin->publish( $recipient, [] );
    }

}
