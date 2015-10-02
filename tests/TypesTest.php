<?php
namespace tests;
use Ably\AblyRest;
use Ably\Auth;
use Ably\Exceptions\AblyException;

require_once __DIR__ . '/factories/TestApp.php';

class TypesTest extends \PHPUnit_Framework_TestCase {

    public static function setUpBeforeClass() {
    }

    public static function tearDownAfterClass() {
    }

    protected function verifyClassMembers( $class, $expectedMembers ) {
        $valid = true;
        foreach( $expectedMembers as $member ) {
            if ( !property_exists( $class, $member ) ) {
                $valid = false;
                break;
            }
        }

        $this->assertTrue( $valid, "Expected class `$class` to contain a field named `$member`." );
    }

    protected function verifyClassConstants( $class, $expectedMembers ) {
        $valid = true;
        foreach( $expectedMembers as $member => $value ) {
            if ( constant( "$class::$member" ) != $value ) {
                $valid = false;
                break;
            }
        }

        $this->assertTrue( $valid, "Expected class `$class` to have a constant `$member` with a value of `$value`." );
    }

    public function testMessageType() {
        $this->verifyClassMembers( '\Ably\Models\Message', array(
            'id',
            'clientId',
            'connectionId',
            'data',
            'encoding',
            'timestamp',
        ) );
    }

    public function testPresenceMessageType() {
        $this->verifyClassMembers( '\Ably\Models\PresenceMessage', array(
            'id',
            'action',
            'clientId',
            'connectionId',
            'data',
            'encoding',
            'timestamp',
            'memberKey'
        ) );

        $this->verifyClassConstants( '\Ably\Models\PresenceMessage', array(
            'ABSENT'  => 0,
            'PRESENT' => 1,
            'ENTER'   => 2,
            'LEAVE'   => 3,
            'UPDATE'  => 4
        ) );
    }

    public function testTokenRequestType() {
        $this->verifyClassMembers( '\Ably\Models\TokenRequest', array(
            'keyName',
            'clientId',
            'nonce',
            'mac',
            'capability',
            'ttl',
        ) );
    }

    public function testTokenDetailsType() {
        $this->verifyClassMembers( '\Ably\Models\TokenDetails', array(
            'token',
            'expires',
            'issued',
            'capability',
            'clientId',
        ) );
    }

    public function testStatsType() {
        $this->verifyClassMembers( '\Ably\Models\Stats', array(
            'all',
            'apiRequests',
            'channels',
            'connections',
            'inbound',
            'intervalGranularity',
            'intervalId',
            'intervalTime',
            'outbound',
            'persisted',
            'tokenRequests'
        ) );
    }

    public function testErrorInfoType() {
        $this->verifyClassMembers( '\Ably\Models\ErrorInfo', array(
            'code',
            'statusCode',
            'message',
        ) );
    }

    public function testClientOptionsType() {
        $this->verifyClassMembers( '\Ably\Models\ClientOptions', array(
            'clientId',
            'logLevel',
            'logHandler',
            'tls',
            'useBinaryProtocol',
            'key',
            'token',
            'tokenDetails',
            'useTokenAuth',
            'authCallback',
            'authUrl',
            'authMethod',
            'authHeaders',
            'authParams',
            'queryTime',
            'environment',
            'restHost',
            'port',
            'tlsPort'
        ) );
    }

    public function testTokenParamsType() {
        $this->verifyClassMembers( '\Ably\Models\TokenParams', array(
            'ttl',
            'capability',
            'clientId',
            'timestamp',
        ) );
    }

    public function testChannelOptionsType() {
        $this->verifyClassMembers( '\Ably\Models\ChannelOptions', array(
            'encrypted',
            'cipherParams',
        ) );
    }

    public function testCipherParamsType() {
        $this->verifyClassMembers( '\Ably\Models\CipherParams', array(
            'algorithm',
            'keyLength',
            'mode'
        ) );
    }
}