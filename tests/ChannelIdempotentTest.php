<?php
namespace tests;
use Ably\AblyRest;
use Ably\Models\Message;

require_once __DIR__ . '/factories/TestApp.php';


class ChannelIdempotentTest extends \PHPUnit_Framework_TestCase {
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
     * RSL1j
     */
    public function testMessageSerialization() {
        $channel = self::$ably->channel( 'messageSerialization' );

        $msg = new Message();
        $msg->name = 'name';
        $msg->data = 'data';
        $msg->clientId = 'clientId';
        $msg->id = 'foobar';

        $body = $channel->__publish_request_body( $msg );
        print_r($body);
        $body = json_decode($body);

        $this->assertTrue( property_exists($body, 'name') );
        $this->assertTrue( property_exists($body, 'data') );
        $this->assertTrue( property_exists($body, 'clientId') );
        $this->assertTrue( property_exists($body, 'id') );
    }

}
