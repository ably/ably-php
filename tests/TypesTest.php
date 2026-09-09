<?php
namespace tests;
use Ably\PubSub\AblyRest;
use Ably\PubSub\Auth;
use Ably\PubSub\Defaults;
use Ably\PubSub\Exceptions\AblyException;

require_once __DIR__ . '/factories/TestApp.php';

class TypesTest extends \PHPUnit\Framework\TestCase {

    public static function setUpBeforeClass(): void {
    }

    public static function tearDownAfterClass(): void {
    }

    protected function verifyClassMembers( $class, $expectedMembers ) {
        $valid = true;
        foreach( $expectedMembers as $member ) {
            $this->assertTrue( property_exists( $class, $member ),
                               "Expected class `$class` to contain a field named `$member`." );
        }
    }

    protected function verifyClassConstants( $class, $expectedMembers ) {
        foreach( $expectedMembers as $member => $value ) {
            $this->assertEquals( $value, constant( "$class::$member" ),
                "Expected class `$class` to have a constant `$member` with a value of `$value`."
            );
        }
    }

    protected function verifyObjectTypes( $obj, $expectedTypes ) {
        foreach( $obj as $key => $value ) {
            if ( gettype( $value ) == 'object' ) {
                $this->assertEquals( $expectedTypes[$key], get_class( $value ),
                    "Expected object (".get_class($obj).") to contain a member `$key` of type `".$expectedTypes[$key]."`."
                );
            } else {
                $this->assertEquals( $expectedTypes[$key], gettype( $value ),
                    "Expected object (".get_class($obj).") to contain a member `$key` of type `".$expectedTypes[$key]."`."
                );
            }
        }
    }

    public function testMessageType() {
        $this->verifyClassMembers( '\Ably\PubSub\Models\Message', [
            'id',
            'clientId',
            'connectionId',
            'connectionKey',
            'name',
            'data',
            'encoding',
            'timestamp',
        ] );
    }

    public function testPresenceMessageType() {
        $this->verifyClassMembers( '\Ably\PubSub\Models\PresenceMessage', [
            'id',
            'action',
            'clientId',
            'connectionId',
            'data',
            'encoding',
            'timestamp',
            'memberKey'
        ] );

        $this->verifyClassConstants( '\Ably\PubSub\Models\PresenceMessage', [
            'ABSENT'  => 0,
            'PRESENT' => 1,
            'ENTER'   => 2,
            'LEAVE'   => 3,
            'UPDATE'  => 4
        ] );
    }

    public function testTokenRequestType() {
        $this->verifyClassMembers( '\Ably\PubSub\Models\TokenRequest', [
            'keyName',
            'clientId',
            'nonce',
            'mac',
            'capability',
            'ttl',
        ] );
    }

    public function testTokenDetailsType() {
        $this->verifyClassMembers( '\Ably\PubSub\Models\TokenDetails', [
            'token',
            'expires',
            'issued',
            'capability',
            'clientId',
        ] );
    }

    public function testStatsType() {
        $this->verifyClassMembers( '\Ably\PubSub\Models\Stats', [
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
        ] );
    }

    public function testErrorInfoType() {
        $this->verifyClassMembers( '\Ably\PubSub\Models\ErrorInfo', [
            'code',
            'statusCode',
            'message',
        ] );
    }

    public function testClientOptionsType() {
        $this->verifyClassMembers( '\Ably\PubSub\Models\ClientOptions', [
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
            'tlsPort',
            'httpOpenTimeout',
            'httpRequestTimeout',
            'httpMaxRetryCount',
            'idempotentRestPublishing',
        ] );

        $co = new \Ably\PubSub\Models\ClientOptions();
        $this->assertEquals( 4000, $co->httpOpenTimeout );
        $this->assertEquals( 10000, $co->httpRequestTimeout );
        $this->assertEquals( 3, $co->httpMaxRetryCount );
        $this->assertEquals( 15000, $co->httpMaxRetryDuration );
    }

    // TO3n
    public function testClientOptionsIdempotent()
    {
        // Test default value
        $co = new \Ably\PubSub\Models\ClientOptions();
        if (Defaults::API_VERSION <= '1.1') {
            $this->assertEquals( false, $co->idempotentRestPublishing );
        } else {
            $this->assertEquals( true, $co->idempotentRestPublishing );
        }

        // Test explicit value
        $co = new \Ably\PubSub\Models\ClientOptions( array( 'idempotentRestPublishing' => true ) );
        $this->assertEquals( true, $co->idempotentRestPublishing );

        $co = new \Ably\PubSub\Models\ClientOptions( array( 'idempotentRestPublishing' => false ) );
        $this->assertEquals( false, $co->idempotentRestPublishing );
    }

    public function testAuthOptionsType() {
        $this->verifyClassMembers( '\Ably\PubSub\Models\ClientOptions', [
            'key',
            'authCallback',
            'authUrl',
            'authMethod',
            'authHeaders',
            'authParams',
            'queryTime',
        ] );
    }

    public function testTokenParamsType() {
        $this->verifyClassMembers( '\Ably\PubSub\Models\TokenParams', [
            'ttl',
            'capability',
            'clientId',
            'timestamp',
        ] );
    }

    public function testChannelOptionsType() {
        $this->verifyClassMembers( '\Ably\PubSub\Models\ChannelOptions', [
            'cipher',
        ] );
    }

    public function testCipherParamsType() {
        $this->verifyClassMembers( '\Ably\PubSub\Models\CipherParams', [
            'algorithm',
            'key',
            'keyLength',
            'mode'
        ] );
    }

    public function testStatsTypes() {
        $stats = new \Ably\PubSub\Models\Stats();
        $this->verifyObjectTypes( $stats, [
            'all'                 => 'Ably\PubSub\Models\Stats\MessageTypes',
            'inbound'             => 'Ably\PubSub\Models\Stats\MessageTraffic',
            'outbound'            => 'Ably\PubSub\Models\Stats\MessageTraffic',
            'persisted'           => 'Ably\PubSub\Models\Stats\MessageTypes',
            'connections'         => 'Ably\PubSub\Models\Stats\ConnectionTypes',
            'channels'            => 'Ably\PubSub\Models\Stats\ResourceCount',
            'apiRequests'         => 'Ably\PubSub\Models\Stats\RequestCount',
            'tokenRequests'       => 'Ably\PubSub\Models\Stats\RequestCount',
            'intervalId'          => 'string',
            'intervalGranularity' => 'string',
            'intervalTime'        => 'integer',
        ] );

        // verify MessageTypes
        $this->verifyObjectTypes( $stats->all, [
            'all'      => 'Ably\PubSub\Models\Stats\MessageCount',
            'messages' => 'Ably\PubSub\Models\Stats\MessageCount',
            'presence' => 'Ably\PubSub\Models\Stats\MessageCount',
        ] );

        // verify MessageCount
        $this->verifyObjectTypes( $stats->all->all, [
            'count' => 'integer',
            'data'  => 'integer',
        ] );

        // verify MessageTraffic
        $this->verifyObjectTypes( $stats->inbound, [
            'all'      => 'Ably\PubSub\Models\Stats\MessageTypes',
            'realtime' => 'Ably\PubSub\Models\Stats\MessageTypes',
            'rest'     => 'Ably\PubSub\Models\Stats\MessageTypes',
            'webhook'  => 'Ably\PubSub\Models\Stats\MessageTypes',
        ] );

        // verify ConnectionTypes
        $this->verifyObjectTypes( $stats->connections, [
            'all'   => 'Ably\PubSub\Models\Stats\ResourceCount',
            'plain' => 'Ably\PubSub\Models\Stats\ResourceCount',
            'tls'   => 'Ably\PubSub\Models\Stats\ResourceCount',
        ] );

        // verify ResourceCount
        $this->verifyObjectTypes( $stats->connections->all, [
            'mean'    => 'integer',
            'min'     => 'integer',
            'opened'  => 'integer',
            'peak'    => 'integer',
            'refused' => 'integer',
        ] );

        // verify RequestCount
        $this->verifyObjectTypes( $stats->apiRequests, [
            'failed'    => 'integer',
            'refused'   => 'integer',
            'succeeded' => 'integer',
        ] );
    }

    public function testHttpPaginatedResponseType() {
        $this->verifyClassMembers( '\Ably\PubSub\Models\HttpPaginatedResponse', [
            'items',
            'statusCode',
            'success',
            'errorCode',
            'errorMessage',
            'headers',
        ] );
    }
}
