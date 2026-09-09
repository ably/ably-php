<?php
namespace tests;

use Ably\PubSub\AblyRest;
use Ably\PubSub\Defaults;
use Ably\PubSub\Http;
use Ably\PubSub\Utils\CurlWrapper;
use Ably\PubSub\Models\Untyped;
use Ably\PubSub\Utils\Miscellaneous;
use Ably\PubSub\Models\ClientOptions;
use Ably\PubSub\Server;

require_once __DIR__ . '/factories/TestApp.php';

class HttpTest extends \PHPUnit\Framework\TestCase {

    protected static $testApp;
    protected static $defaultOptions;
    protected static $ably;

    public static function setUpBeforeClass(): void {
        self::$testApp = new TestApp();
        self::$defaultOptions = self::$testApp->getOptions();
        self::$ably = new AblyRest( array_merge( self::$defaultOptions, [
            'key' => self::$testApp->getAppKeyDefault()->string,
        ] ) );
    }

    public static function tearDownAfterClass(): void {
        self::$testApp->release();
    }

    /**
     * Verify that API version is sent in HTTP requests
     */
    public function testVersionHeaderPresence() {
        $opts = [
            'key' => 'fake.key:totallyFake',
            'httpClass' => 'tests\HttpMock',
        ];
        $ably = new AblyRest( $opts );
        $ably->time(); // make a request

        $curlParams = $ably->http->getCurlLastParams();
        $this->assertContains( 'X-Ably-Version: ' . Defaults::API_VERSION, $curlParams[CURLOPT_HTTPHEADER],
                                  'Expected Ably version header in HTTP request' );
    }

    /**
     * Mock options that never reach the network.
     */
    private static function mockOptions( $extra = [] ) {
        return array_merge( [
            'key' => 'fake.key:totallyFake',
            'httpClass' => 'tests\HttpMock',
        ], $extra );
    }

    /**
     * Makes one request through the given client and returns the value it sent
     * in the Ably-Agent request header.
     */
    private static function sentAgentHeader( $ably ) {
        $ably->time(); // make a request
        $curlParams = $ably->http->getCurlLastParams();

        foreach ( $curlParams[CURLOPT_HTTPHEADER] as $header ) {
            if ( strpos( $header, 'Ably-Agent: ' ) === 0 ) {
                return substr( $header, strlen( 'Ably-Agent: ' ) );
            }
        }

        return null;
    }

    /**
     * The prefix every Ably-Agent header carries: this SDK and the runtime.
     */
    private static function expectedPrefix() {
        return 'ably-pubsub-php/'.Defaults::LIB_VERSION.' php/'.Miscellaneous::getNumeric( phpversion() );
    }

    /**
     * Verify proper agent header is set as per RSC7d
     */
    public function testAblyAgentHeader() {
        $ably = new AblyRest( self::mockOptions() );

        $this->assertSame( self::expectedPrefix(), self::sentAgentHeader( $ably ),
            'Expected Ably agent header in HTTP request' );

        // a second client renders the same header: no state leaks between instances
        $ably = new AblyRest( self::mockOptions() );

        $this->assertSame( self::expectedPrefix(), self::sentAgentHeader( $ably ),
            'Expected Ably agent header in HTTP request' );
    }

    /**
     * Wrapper attribution now travels as a client option rather than as
     * process-global static state (RSC7d).
     */
    public function testAblyAgentHeaderWithAgentsOption() {
        $ably = new AblyRest( self::mockOptions( [
            'agents' => [ 'laravel' => null, 'customLib' => '2.3.5' ],
        ] ) );

        $this->assertSame( self::expectedPrefix().' laravel customLib/2.3.5', self::sentAgentHeader( $ably ),
            'Expected agents option to be rendered in the Ably agent header' );
    }

    /**
     * The door declares the server side, and declares it as a versionless flag.
     *
     * This is what the billing system reads to grant the MAU server exemption,
     * so the assertions here are deliberately exact.
     */
    public function testDoorDeclaresServerSide() {
        $agentHeader = self::sentAgentHeader( Server::createHttpClient( self::mockOptions() ) );

        $this->assertMatchesRegularExpression(
            '/^ably-pubsub-php\/\d+\.\d+\.\d+(\S*)? php\/\S+ ably-pubsub-server$/',
            $agentHeader,
            'Expected the door to declare the server side in the Ably agent header'
        );
    }

    /**
     * A versioned `ably-pubsub-server/<anything>` token must never be sent: the
     * registry declares the identifier as a flag, and the versioned form does
     * not classify. This is the PHP shape of the regression ably-js#2297
     * guards against, where an absent version rendered as `name/undefined`.
     *
     * @dataProvider sideAgentVersionProvider
     */
    public function testSideAgentIsNeverVersioned( $callerVersion ) {
        $agentHeader = self::sentAgentHeader( Server::createHttpClient( self::mockOptions( [
            'agents' => [ Server::SERVER_AGENT_IDENTIFIER => $callerVersion ],
        ] ) ) );

        $this->assertStringContainsString( ' ably-pubsub-server', $agentHeader,
            'Expected the versionless server side flag' );
        $this->assertStringNotContainsString( 'ably-pubsub-server/', $agentHeader,
            'The server side flag must never carry a version' );
        $this->assertSame( self::expectedPrefix().' ably-pubsub-server', $agentHeader,
            'Expected the caller not to be able to override the side entry' );
    }

    public function sideAgentVersionProvider() {
        return [
            'caller supplies a version' => [ 'x' ],
            'caller supplies an empty version' => [ '' ],
            'caller supplies null' => [ null ],
        ];
    }

    /**
     * The door is the only path that stamps a side. A client built by calling
     * the constructor directly declares none.
     */
    public function testBareConstructorDeclaresNoSide() {
        $agentHeader = self::sentAgentHeader( new AblyRest( self::mockOptions() ) );

        $this->assertStringNotContainsString( 'ably-pubsub-server', $agentHeader,
            'A directly constructed client must not declare the server side' );
        $this->assertSame( self::expectedPrefix(), $agentHeader );
    }

    /**
     * A caller's own agent entries survive the door and precede the side entry.
     */
    public function testDoorPreservesCallerAgents() {
        $agentHeader = self::sentAgentHeader( Server::createHttpClient( self::mockOptions( [
            'agents' => [ 'my-sdk' => '1.0' ],
        ] ) ) );

        $this->assertSame( self::expectedPrefix().' my-sdk/1.0 ably-pubsub-server', $agentHeader,
            'Expected caller agents to be preserved and to precede the side entry' );
    }

    /**
     * The door accepts everything the constructor accepts.
     */
    public function testDoorAcceptsEveryConstructorArgumentForm() {
        $expected = self::expectedPrefix().' ably-pubsub-server';

        // an options array
        $fromArray = Server::createHttpClient( self::mockOptions() );
        $this->assertSame( $expected, self::sentAgentHeader( $fromArray ) );
        $this->assertSame( 'fake.key:totallyFake', $fromArray->options->key );

        // a ClientOptions instance
        $clientOptions = new ClientOptions( self::mockOptions() );
        $fromClientOptions = Server::createHttpClient( $clientOptions );
        $this->assertSame( $expected, self::sentAgentHeader( $fromClientOptions ) );
        $this->assertSame( 'fake.key:totallyFake', $fromClientOptions->options->key );
        $this->assertSame( [], $clientOptions->agents,
            'The door must not mutate the ClientOptions instance it was given' );

        // a bare API key string (contains a colon)
        $fromKey = Server::createHttpClient( 'fake.key:totallyFake' );
        $this->assertSame( 'fake.key:totallyFake', $fromKey->options->key );
        $this->assertSame( [ Server::SERVER_AGENT_IDENTIFIER => null ], $fromKey->options->agents );

        // a bare token string (no colon)
        $fromToken = Server::createHttpClient( 'totallyFakeToken' );
        $this->assertSame( 'totallyFakeToken', $fromToken->options->token );
        $this->assertSame( [ Server::SERVER_AGENT_IDENTIFIER => null ], $fromToken->options->agents );
    }

    /**
     * Verify that GET requests are encoded properly (using requestToken)
     */
    public function testGET() {
        $authParams = [
            'param1' => '&?#',
            'param2' => 'x',
        ];
        $tokenParams = [
            'clientId' => 'test',
        ];

        $ably = new AblyRest( [
            'key' => 'fake.key:totallyFake',
            'authUrl' => 'http://test.test/tokenRequest',
            'authParams' => $authParams,
            'authMethod' => 'GET',
            'httpClass' => 'tests\HttpMock',
        ] );

        $expectedParams = array_merge( $authParams, $tokenParams );

        $ably->auth->requestToken( $tokenParams );

        $curlParams = $ably->http->getCurlLastParams();

        $this->assertEquals( 'http://test.test/tokenRequest?'.http_build_query($expectedParams), $curlParams[CURLOPT_URL], 'Expected URL to contain encoded GET parameters' );
    }


    /**
     * Verify that POST requests are encoded properly (using requestToken)
     */
    public function testPOST() {
        $authParams = [
            'param1' => '&?#',
            'param2' => 'x',
        ];
        $tokenParams = [
            'clientId' => 'test',
        ];

        $ably = new AblyRest( [
            'key' => 'fake.key:totallyFake',
            'authUrl' => 'http://test.test/tokenRequest',
            'authParams' => $authParams,
            'authMethod' => 'POST',
            'httpClass' => 'tests\HttpMock',
        ] );

        $expectedParams = array_merge( $authParams, $tokenParams );

        $ably->auth->requestToken( $tokenParams );

        $curlParams = $ably->http->getCurlLastParams();

        $this->assertEquals( 'http://test.test/tokenRequest', $curlParams[CURLOPT_URL],
                             'Expected URL to match authUrl' );
        $this->assertEquals( http_build_query($expectedParams), $curlParams[CURLOPT_POSTFIELDS],
                             'Expected POST params to contain encoded params' );
    }

    /**
     * RSC19 Test basic AblyRest::request functionality
     */
    public function testRequestBasic() {
        $ably = self::$ably;

        $msg = (object) [
            'name' => 'testEvent',
            'data' => 'testPayload',
        ];

        $res = $ably->request('POST', '/channels/persisted:test/messages', [], $msg );

        $this->assertTrue($res->success, 'Expected sending a message via custom request to succeed');
        $this->assertLessThan(300, $res->statusCode, 'Expected statusCode < 300');
        $this->assertEmpty($res->errorCode, 'Expected empty errorCode');
        $this->assertEmpty($res->errorMessage, 'Expected empty errorMessage');

        $res2 = $ably->request('GET', '/channels/persisted:test/messages');

        $this->assertTrue($res2->success, 'Expected retrieving the message via custom request to succeed');
        $this->assertLessThan(300, $res2->statusCode, 'Expected statusCode < 300');
        $this->assertArrayHasKey('Content-Type', $res2->headers,
                                 'Expected headers to be an array containing key `Content-Type`');
        $this->assertEquals(1, count($res2->items), 'Expected to receive 1 message');
        $this->assertEquals($msg->name, $res2->items[0]->name,
                            'Expected to receive matching message contents');

        $res3 = $ably->request('GET', '/this-does-not-exist');

        $this->assertEquals(404, $res3->statusCode, 'Expected statusCode 404');
        $this->assertEquals(40400, $res3->errorCode, 'Expected errorCode 40400');
        $this->assertNotEmpty($res3->errorMessage, 'Expected errorMessage to be set');
        $this->assertArrayHasKey('X-Ably-Errorcode', $res3->headers,
                                 'Expected X-Ably-Errorcode header to be present');
        $this->assertArrayHasKey('X-Ably-Errormessage', $res3->headers,
                                 'Expected X-Ably-Errormessage header to be present');
    }

    /**
     * RSC19 - Test that Response handles various returned structures properly
     */
    public function testRequestReturnValues() {
        $ably = new AblyRest( [
            'key' => 'fake.key:totallyFake',
            'httpClass' => 'tests\HttpMockReturnData',
        ] );

        // array of objects
        $ably->http->setResponseJSONString('[{"test":"one"},{"test":"two"},{"test":"three"}]');
        $res1 = $ably->request('GET', '/get_test_json');
        $this->assertEquals('[{"test":"one"},{"test":"two"},{"test":"three"}]', json_encode($res1->items));

        // array with single object
        $ably->http->setResponseJSONString('[{"test":"yes"}]');
        $res2 = $ably->request('GET', '/get_test_json');
        $this->assertEquals('[{"test":"yes"}]', json_encode($res2->items));

        // single object - should be returned as array with single object
        $ably->http->setResponseJSONString('{"test":"yes"}');
        $res3 = $ably->request('GET', '/get_test_json');
        $this->assertEquals('[{"test":"yes"}]', json_encode($res3->items));

        // not an object or array - should be returned as empty array
        $ably->http->setResponseJSONString('"invalid"');
        $res4 = $ably->request('GET', '/get_test_json');
        $this->assertEquals('[]', json_encode($res4->items));
    }
}


class CurlWrapperMock extends CurlWrapper {
    public $lastParams;

    public function init( $url = null ) {
        $this->lastParams = [ CURLOPT_URL => $url ];

        return parent::init( $url );
    }

    public function setOpt( $handle, $option, $value ) {
        $this->lastParams[$option] = $value;

        return parent::setOpt( $handle, $option, $value );
    }

    /**
     * Returns a fake token when tere is `/tokenRequest` in the URL, otherwise returns current time
     * wrapped in an array (as does GET /time) without actually making the request.
     */
    public function exec( $handle ) {
        if (preg_match('/\\/tokenRequest/', $this->lastParams[CURLOPT_URL])) {
            return 'tokentokentoken';
        }

        return '[' . round( microtime( true ) * 1000 ) . ']';
    }

    public function getInfo( $handle ) {
        return [
            'http_code' => 200,
            'header_size' => 0,
        ];
    }
}


class HttpMock extends Http {
    public function __construct() {
        parent::__construct(new \Ably\PubSub\Models\ClientOptions());
        $this->curl = new CurlWrapperMock();
    }

    public function getCurlLastParams() {
        return $this->curl->lastParams;
    }
}


class HttpMockReturnData extends Http {
    private $responseStr = '';
    public function setResponseJSONString($str) {
        $this->responseStr = $str;
    }

    public function request($method, $url, $headers = [], $params = []) {

        if ($method == 'GET' && self::endsWith($url, '/get_test_json')) {
            return [
                'headers' => 'HTTP/1.1 200 OK'."\n",
                'body' => json_decode($this->responseStr),
            ];
        } else {
            return [
                'headers' => 'HTTP/1.1 404 Not found'."\n",
                'body' => '',
            ];
        }
    }

    private static function endsWith($haystack, $needle) {
        return substr($haystack, -strlen($needle)) == $needle;
    }
}

