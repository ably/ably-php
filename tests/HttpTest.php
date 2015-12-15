<?php
namespace tests;

use Ably\AblyRest;
use Ably\Http;
use Ably\Utils\CurlWrapper;
use Ably\Exceptions\AblyRequestException;

require_once __DIR__ . '/factories/TestApp.php';

class HttpTest extends \PHPUnit_Framework_TestCase {

    /**
     * Verify that API version is sent in HTTP requests
     */
    public function testVersionHeaderPresence() {
        $opts = array(
            'key' => 'fake.key:totallyFake',
            'httpClass' => 'tests\HttpMock',
        );
        $ably = new AblyRest( $opts );
        $ably->time(); // make a request

        $curlParams = $ably->http->getCurlLastParams();

        $this->assertArrayHasKey( 'X-Ably-Version', $curlParams[CURLOPT_HTTPHEADER], 'Expected Ably version header in HTTP request' );
        $this->assertEquals( AblyRest::API_VERSION, $curlParams[CURLOPT_HTTPHEADER]['X-Ably-Version'], 'Expected Ably version in HTTP header to match AblyRest constant' );
    }


    /**
     * Verify that GET requests are encoded properly (using requestToken)
     */
    public function testGET() {
        $authParams = array(
            'param1' => '&?#',
            'param2' => 'x',
        );
        $tokenParams = array(
            'clientId' => 'test',
        );

        $ably = new AblyRest( array(
            'key' => 'fake.key:totallyFake',
            'authUrl' => 'http://test.test/tokenRequest',
            'authParams' => $authParams,
            'authMethod' => 'GET',
            'httpClass' => 'tests\HttpMock',
        ) );

        $expectedParams = array_merge( $authParams, $tokenParams );
        
        $ably->auth->requestToken( $tokenParams );

        $curlParams = $ably->http->getCurlLastParams();
        
        $this->assertEquals( 'http://test.test/tokenRequest?'.http_build_query($expectedParams), $curlParams[CURLOPT_URL], 'Expected URL to contain encoded GET parameters' );
    }


    /**
     * Verify that POST requests are encoded properly (using requestToken)
     */
    public function testPOST() {
        $authParams = array(
            'param1' => '&?#',
            'param2' => 'x',
        );
        $tokenParams = array(
            'clientId' => 'test',
        );

        $ably = new AblyRest( array(
            'key' => 'fake.key:totallyFake',
            'authUrl' => 'http://test.test/tokenRequest',
            'authParams' => $authParams,
            'authMethod' => 'POST',
            'httpClass' => 'tests\HttpMock',
        ) );

        $expectedParams = array_merge( $authParams, $tokenParams );
        
        $ably->auth->requestToken( $tokenParams );

        $curlParams = $ably->http->getCurlLastParams();
        
        $this->assertEquals( 'http://test.test/tokenRequest', $curlParams[CURLOPT_URL], 'Expected URL to match authUrl' );
        $this->assertEquals( http_build_query($expectedParams), $curlParams[CURLOPT_POSTFIELDS], 'Expected POST params to contain encoded params' );
    }
}


class CurlWrapperMock extends CurlWrapper {
    public $lastParams;

    public function init( $url = null ) {
        $this->lastParams = array( CURLOPT_URL => $url );

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
        return array(
            'http_code' => 200,
            'header_size' => 0,
        );
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