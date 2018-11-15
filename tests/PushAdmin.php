<?php
namespace tests;
use Ably\AblyRest;

require_once __DIR__ . '/factories/TestApp.php';


class PushAdminTest extends \PHPUnit_Framework_TestCase {
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
        $channelName = 'pushenabled:push_admin_publish-ok';
        $recipient = [
            'transportType' => 'ablyChannel',
            'channel' => $channelName,
            'ablyKey' => self::$ably->options->key,
            'ablyUrl' => self::$testApp->server
        ];
        $data = [ 'data' => [ 'foo' => 'bar' ] ];

        $res = self::$ably->push->admin->publish( $recipient, $data , true );
        $this->assertEquals($res['info']['http_code'] , 204 );

        $channel = self::$ably->channel($channelName);
        $history = $channel->history();
        $this->assertEquals( 1, count($history->items), 'Expected 1 message' );
    }

    public function badValues() {
        $recipient = [ 'clientId' => 'ablyChannel' ];
        $data = [ 'data' => [ 'foo' => 'bar' ] ];

        return [
            [ [], $data ],
            [ $recipient, [] ],
        ];
    }

    /**
     * @dataProvider badValues
     * @expectedException InvalidArgumentException
     */
    public function testAdminPublishInvalid($recipient, $data) {
        self::$ably->push->admin->publish( $recipient, $data );
    }
}
