<?php
namespace tests;
use Ably\AblyRest;
use Ably\Models\PushChannelSubscription;
use Ably\Models\PaginatedResult;
use Ably\Exceptions\AblyException;

require_once __DIR__ . '/factories/TestApp.php';


function random_string ( $n ) {
    return bin2hex(openssl_random_pseudo_bytes($n / 2));
}

function deviceData () {
    return [
        'id' => random_string(26),
        'clientId' => random_string(12),
        'platform' => 'ios',
        'formFactor' => 'phone',
        'push' => [
            'recipient' => [
                'transportType' => 'apns',
                'deviceToken' => '740f4707bebcf74f9b7c25d48e3358945f6aa01da5ddb387462c7eaf61bb78ad'
            ]
        ],
        'deviceSecret' => random_string(12),
    ];
}


class PushChannelSubscriptionsTest extends \PHPUnit_Framework_TestCase {
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
     * RSH1b3
     */
    public function testSave() {
        // Create
        $data = deviceData();
        self::$ably->push->admin->deviceRegistrations->save($data);

        // Subscribe
        $channelSubscription = self::$ably->push->admin->channelSubscriptions->save([
            'channel' => 'pushenabled:test',
            'deviceId' => $data['id'],
        ]);
        $this->assertInstanceOf(PushChannelSubscription::class, $channelSubscription);
        $this->assertEquals($channelSubscription->channel, 'pushenabled:test');
        $this->assertEquals($channelSubscription->deviceId, $data['id']);

        // Update, doesn't fail
        self::$ably->push->admin->channelSubscriptions->save([
            'channel' => 'pushenabled:test',
            'deviceId' => $data['id'],
        ]);

        // Fail
        $clientId = random_string(12);
        $this->expectException(\InvalidArgumentException::class);
        self::$ably->push->admin->channelSubscriptions->save([
            'channel' => 'pushenabled:test',
            'deviceId' => $data['id'],
            'clientId' => $clientId,
        ]);
    }

    public function badValues() {
        $data = deviceData();
        return [
            [ [ 'channel' => 'notallowed', 'deviceId' => $data['id'] ] ],
            [ [ 'channel' => 'pushenabled:test', 'deviceId' => 'notregistered' ] ]
        ];
    }

    /**
     * @dataProvider badValues
     * @expectedException Ably\Exceptions\AblyException
     */
    public function testSaveInvalid($data) {
        self::$ably->push->admin->channelSubscriptions->save($data);
    }

}
