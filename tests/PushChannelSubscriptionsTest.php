<?php
namespace tests;
use Ably\AblyRest;
use Ably\Models\PushChannelSubscription;
use Ably\Models\PaginatedResult;
use Ably\Exceptions\AblyException;

require_once __DIR__ . '/factories/TestApp.php';
require_once __DIR__ . '/Utils.php';


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
     * RSH1c1
     */
    public function testList() {
        $channel = 'pushenabled:list';

        // Register devices and subscribe
        $deviceIds = [];
        $clientIds = [];
        foreach(range(1,5) as $index) {
            // Register device
            $data = deviceData();
            self::$ably->push->admin->deviceRegistrations->save($data);
            $deviceIds[] = $data['id'];
            // Subscribe
            self::$ably->push->admin->channelSubscriptions->save([
                'channel' => $channel,
                'deviceId' => $data['id'],
            ]);
        }
        foreach(range(1,5) as $index) {
            // Register device
            $data = deviceData();
            self::$ably->push->admin->deviceRegistrations->save($data);
            $clientIds[] = $data['clientId'];
            // Subscribe
            self::$ably->push->admin->channelSubscriptions->save([
                'channel' => $channel,
                'clientId' => $data['clientId'],
            ]);
        }

        $params = [ 'channel' => $channel ];
        $response = self::$ably->push->admin->channelSubscriptions->list_($params);
        $this->assertInstanceOf(PaginatedResult::class, $response);
        $this->assertGreaterThanOrEqual(10, count($response->items));
        $this->assertInstanceOf(PushChannelSubscription::class, $response->items[0]);
        $this->assertContains($response->items[0]->deviceId, $deviceIds);

        // limit
        $response = self::$ably->push->admin->channelSubscriptions->list_(
            array_merge($params, ['limit' => 2])
        );
        $this->assertEquals(2, count($response->items));

        // Filter by device id
        $deviceId = $deviceIds[0];
        $response = self::$ably->push->admin->channelSubscriptions->list_(
            array_merge($params, ['deviceId' => $deviceId])
        );
        $this->assertEquals(1, count($response->items));
        $response = self::$ably->push->admin->channelSubscriptions->list_(
            array_merge($params, ['deviceId' => random_string(26)])
        );
        $this->assertEquals(0, count($response->items));

        // Filter by client id
        $clientId = $clientIds[0];
        $response = self::$ably->push->admin->channelSubscriptions->list_(
            array_merge($params, ['clientId' => $clientId])
        );
        $this->assertEquals(1, count($response->items));
        $response = self::$ably->push->admin->channelSubscriptions->list_(
            array_merge($params, ['clientId' => random_string(12)])
        );
        $this->assertEquals(0, count($response->items));
    }

    /**
     * RSH1c3
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
