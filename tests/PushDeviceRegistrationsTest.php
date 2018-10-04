<?php
namespace tests;
use Ably\AblyRest;
use Ably\Models\DeviceDetails;
use Ably\Exceptions\AblyException;

require_once __DIR__ . '/factories/TestApp.php';


function random_string ( $n ) {
    return bin2hex(openssl_random_pseudo_bytes($n / 2));
}

function data () {
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


class PushDeviceRegistrationsTest extends \PHPUnit_Framework_TestCase {
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
     * RSH1b1
     */
    public function testGet() {
        // Save
        $data = data();
        self::$ably->push->admin->deviceRegistrations->save($data);

        // Found
        $deviceDetails = self::$ably->push->admin->deviceRegistrations->get($data['id']);
        $this->assertEquals($data['id'], $deviceDetails->id);
        $this->assertEquals($data['platform'], $deviceDetails->platform);
        $this->assertEquals($data['formFactor'], $deviceDetails->formFactor);
        $this->assertEquals($data['deviceSecret'], $deviceDetails->deviceSecret);

        // Not Found
        $this->expectException(AblyException::class);
        self::$ably->push->admin->deviceRegistrations->get("not-found");
    }

    /**
     * RSH1b3
     */
    public function testSave() {
        $data = data();

        // Create
        $deviceDetails = self::$ably->push->admin->deviceRegistrations->save($data);
        $this->assertInstanceOf(DeviceDetails::class, $deviceDetails);
        $this->assertEquals($deviceDetails->id, $data['id']);

        // Update
        $new_data = array_merge($data, [ 'formFactor' => 'tablet' ]);
        $deviceDetails = self::$ably->push->admin->deviceRegistrations->save($new_data);

        // Fail
        $this->expectException(AblyException::class);
        $new_data = array_merge($data, [ 'deviceSecret' => random_string(12) ]);
        self::$ably->push->admin->deviceRegistrations->save($new_data);
    }

    public function badValues() {
        $data = data();
        return [
            [
                array_merge($data, [
                    'push' => [ 'recipient' => array_merge($data['push']['recipient'], ['transportType' => 'xyz']) ]
                ])
            ],
            [ array_merge($data, [ 'platform' => 'native' ]) ],
            [ array_merge($data, [ 'formFactor' => 'fridge' ]) ],
        ];
    }

    /**
     * @dataProvider badValues
     * @expectedException Ably\Exceptions\AblyRequestException
     */
    public function testSaveInvalid($data) {
        self::$ably->push->admin->deviceRegistrations->save($data);
    }

}
