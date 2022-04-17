<?php
namespace tests;

use Ably\AblyRest;
use Ably\Http;
use Ably\Utils\CurlWrapper;
use Ably\Models\Untyped;
use Ably\Utils\Miscellaneous;

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
        $this->assertContains( 'X-Ably-Version: ' . AblyRest::API_VERSION, $curlParams[CURLOPT_HTTPHEADER],
                                  'Expected Ably version header in HTTP request' );

        AblyRest::setLibraryFlavourString();
    }

    /**
     * Verify proper agent header is set as per RSC7d
     */
    public function testAblyAgentHeader() {
        $opts = [
            'key' => 'fake.key:totallyFake',
            'httpClass' => 'tests\HttpMock',
        ];
        $ably = new AblyRest( $opts );
        $ably->time(); // make a request
        $curlParams = $ably->http->getCurlLastParams();

        $expectedAgentHeader = 'ably-php/'.AblyRest::LIB_VERSION.' '.'php/'.Miscellaneous::getNumeric(phpversion());
        $this->assertContains( 'Ably-Agent: '. $expectedAgentHeader, $curlParams[CURLOPT_HTTPHEADER],
            'Expected Ably agent header in HTTP request' );

        $ably = new AblyRest( $opts );
        $ably->time(); // make a request

        $curlParams = $ably->http->getCurlLastParams();

        $this->assertContains( 'Ably-Agent: '. $expectedAgentHeader, $curlParams[CURLOPT_HTTPHEADER],
            'Expected Ably agent header in HTTP request' );

        AblyRest::setLibraryFlavourString( 'laravel');
        AblyRest::setAblyAgentHeader('customLib', '2.3.5');
        $ably = new AblyRest( $opts );
        $ably->time(); // make a request

        $curlParams = $ably->http->getCurlLastParams();

        $expectedAgentHeader = 'ably-php/'.AblyRest::LIB_VERSION.' '.'php/'.Miscellaneous::getNumeric(phpversion()).' laravel'.' customLib/2.3.5';
        $this->assertContains( 'Ably-Agent: '. $expectedAgentHeader, $curlParams[CURLOPT_HTTPHEADER],
            'Expected Ably agent header in HTTP request' );

        AblyRest::setLibraryFlavourString();
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
        parent::__construct(new \Ably\Models\ClientOptions());
        $this->curl = new CurlWrapperMock();
    }

    public function getCurlLastParams() {
        return $this->curl->lastParams;
    }
}

