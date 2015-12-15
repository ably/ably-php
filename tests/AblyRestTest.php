<?php
namespace tests;
use Ably\AblyRest;
use Ably\Http;
use Ably\Log;
use Ably\Exceptions\AblyRequestException;
use Ably\Models\ClientOptions;
use Ably\Models\TokenDetails;

require_once __DIR__ . '/factories/TestApp.php';

class AblyRestTest extends \PHPUnit_Framework_TestCase {

    /**
     * Init library with a key string
     */
    public function testInitLibWithKeyString() {
        $key = 'fake.key:veryFake';
        $ably = new AblyRest( $key );
        $this->assertTrue( $ably->auth->isUsingBasicAuth(), 'Expected basic auth to be used' );
    }

    /**
     * Init library with a key in options
     */
    public function testInitLibWithKeyOption() {
        $key = 'fake.key:veryFake';
        $ably = new AblyRest( array('key' => $key ) );
        $this->assertTrue( $ably->auth->isUsingBasicAuth(), 'Expected basic auth to be used' );
    }

    /**
     * Init library with a token string
     */
    public function testInitLibWithTokenString() {
        $token = 'fake_token'; // token string never contains a colon
        $ably = new AblyRest( $token );
        $this->assertFalse( $ably->auth->isUsingBasicAuth(), 'Expected token auth to be used' );
    }

    /**
     * Init library with a token string in options
     */
    public function testInitLibWithTokenOption() {
        $ably = new AblyRest( array(
            'token' => "this_is_not_really_a_token",
        ) );

        $this->assertFalse( $ably->auth->isUsingBasicAuth(), 'Expected token auth to be used' );
    }

    /**
     * Init library with a tokenDetails in options
     */
    public function testInitLibWithTokenDetailsOption() {
        $ably = new AblyRest( array(
            'tokenDetails' => new TokenDetails( "this_is_not_really_a_token" ),
        ) );

        $this->assertFalse( $ably->auth->isUsingBasicAuth(), 'Expected token auth to be used' );
    }

    /**
     * Init library with a specified host
     */
    public function testInitLibWithSpecifiedHost() {
        $opts = array(
            'key' => 'fake.key:veryFake',
            'restHost'  => 'some.other.host',
            'httpClass' => 'tests\HttpMockInitTest',
        );
        $ably = new AblyRest( $opts );
        $ably->time(); // make a request
        $this->assertRegExp( '/^https?:\/\/some\.other\.host/', $ably->http->lastUrl, 'Unexpected host mismatch' );
    }

    /**
     * Init library with a specified port
     */
    public function testInitLibWithSpecifiedPort() {
        $opts = array(
            'key' => 'fake.key:veryFake',
            'restHost'  => 'some.other.host',
            'tlsPort' => 999,
            'httpClass' => 'tests\HttpMockInitTest',
        );
        $ably = new AblyRest( $opts );
        $ably->time(); // make a request
        $this->assertContains( 'https://' . $opts['restHost'] . ':' . $opts['tlsPort'], $ably->http->lastUrl, 'Unexpected host/port mismatch' );

        $opts = array(
            'token' => 'fakeToken',
            'restHost'  => 'some.other.host',
            'port' => 999,
            'tls' => false,
            'httpClass' => 'tests\HttpMockInitTest',
        );
        $ably = new AblyRest( $opts );
        $ably->time(); // make a request
        $this->assertContains( 'http://' . $opts['restHost'] . ':' . $opts['port'], $ably->http->lastUrl, 'Unexpected host/port mismatch' );
    }

    /**
     * Init library with specified environment
     */
    public function testInitLibWithSpecifiedEnv() {
        $ably = new AblyRest( array(
            'key' => 'fake.key:veryFake',
            'environment'  => 'sandbox',
            'httpClass' => 'tests\HttpMockInitTest',
        ) );
        $ably->time(); // make a request
        $this->assertRegExp( '/^https?:\/\/sandbox-rest\.ably\.io\//', $ably->http->lastUrl, 'Unexpected host mismatch' );
    }

    /**
     * Init library with specified environment AND host
     */
    public function testInitLibWithSpecifiedEnvHost() {
        $ably = new AblyRest( array(
            'key' => 'fake.key:veryFake',
            'restHost'  => 'some.other.host',
            'environment'  => 'sandbox',
            'httpClass' => 'tests\HttpMockInitTest',
        ) );
        $ably->time(); // make a request
        $this->assertRegExp( '/^https?:\/\/sandbox-some\.other\.host\//', $ably->http->lastUrl, 'Unexpected host mismatch' );
    }

    /**
     * Verify encrypted defaults to true, makes a request to https://rest.ably.io/...
     */
    public function testTLSDefaultIsTrue() {
        $opts = array(
            'key' => 'fake.key:veryFake',
            'httpClass' => 'tests\HttpMockInitTest',
        );
        $ably = new AblyRest( $opts );
        $ably->time(); // make a request
        $this->assertRegExp( '/^https:\/\/rest\.ably\.io/', $ably->http->lastUrl, 'Unexpected scheme/url mismatch' );
    }

    /**
     * Verify encrypted can be set to false, makes a request to http://rest.ably.io/...
     */
    public function testTLSCanBeFalse() {
        $opts = array(
            'token' => 'fake.token',
            'httpClass' => 'tests\HttpMockInitTest',
            'tls' => false,
        );
        $ably = new AblyRest( $opts );
        $ably->time(); // make a request
        $this->assertRegExp( '/^http:\/\/rest\.ably\.io/', $ably->http->lastUrl, 'Unexpected scheme/url mismatch' );
    }

    /**
     * Verify that connection is encrypted when set to true explicitly, makes a request to https://rest.ably.io/...
     */
    public function testTLSExplicitTrue() {
        $opts = array(
            'token' => 'fake.token',
            'httpClass' => 'tests\HttpMockInitTest',
            'tls' => true,
        );
        $ably = new AblyRest( $opts );
        $ably->time(); // make a request
        $this->assertRegExp( '/^https:\/\/rest\.ably\.io/', $ably->http->lastUrl, 'Unexpected scheme/url mismatch' );
    }

    /**
     * Verify that fallback hosts are working and used in correct order
     */
    public function testFallbackHosts() {
        $defaultOpts = new ClientOptions();
        $hostWithFallbacks = array_merge( array( $defaultOpts->restHost ), $defaultOpts->fallbackHosts );

        // reuse default options so that fallback host order is not randomized again
        $opts = array_merge ( $defaultOpts->toArray(), array(
            'key' => 'fake.key:veryFake',
            'httpClass' => 'tests\HttpMockInitTestTimeout',
            'httpMaxRetryCount' => 5,
        ) );
        $ably = new AblyRest( $opts );
        try {
            $ably->time(); // make a request
            $this->fail('Expected the request to fail');
        } catch(AblyRequestException $e) {
            $this->assertEquals( $hostWithFallbacks, $ably->http->failedHosts, 'Expected to have tried all defined fallback hosts' );
        }
    }

    /**
     * Verify that fallback hosts are not called on a 400 error
     */
    public function testFallbackHosts400() {

        $opts = array(
            'key' => 'fake.key:veryFake',
            'httpClass' => 'tests\HttpMockInitTestTimeout',
        );

        $ably = new AblyRest( $opts );
        $ably->http->httpErrorCode = 401;
        $ably->http->errorCode = 40101; // auth error

        try {
            $ably->time(); // make a request
            $this->fail('Expected the request to fail');
        } catch(AblyRequestException $e) {
            $this->assertEquals( array( 'rest.ably.io' ), $ably->http->failedHosts, 'Expected to have tried only the default host' );
        }
    }

    /**
     * Verify that fallback hosts are NOT used when using a custom host
     */
    public function testNoFallbackOnCustomHost() {

        // reuse default options so that fallback host order is not randomized again
        $opts = array(
            'key' => 'fake.key:veryFake',
            'httpClass' => 'tests\HttpMockInitTestTimeout',
            'restHost' => 'custom.host.com',
        );
        $ably = new AblyRest( $opts );
        try {
            $ably->time(); // make a request
            $this->fail('Expected the request to fail');
        } catch(AblyRequestException $e) {
            $this->assertEquals( array( 'custom.host.com' ), $ably->http->failedHosts, 'Expected to have tried only the custom host' );
        }
    }

    /**
     * Verify that fallback hosts are working - first 3 fail, 4th works
     */
    public function testFallbackHostsFailFirst3() {
        $opts = array(
            'key' => 'fake.key:veryFake',
            'httpClass' => 'tests\HttpMockInitTestTimeout',
            'httpMaxRetryCount' => 5,
        );
        $ably = new AblyRest( $opts );
        $ably->http->failAttempts = 3;
        $data = $ably->time(); // make a request
        
        $this->assertEquals( 999999, $data, 'Expected to receive test data' );
        $this->assertEquals( 3, count( $ably->http->failedHosts ), 'Expected 3 hosts to fail' );
    }

    /**
     * Verify that the httpMaxRetryCount option is honored
     */
    public function testMaxRetryCount() {
        $opts = array(
            'key' => 'fake.key:veryFake',
            'httpClass' => 'tests\HttpMockInitTestTimeout',
            'httpMaxRetryCount' => 2,
        );

        $ably = new AblyRest( $opts );
        try {
            $ably->time(); // make a request
            $this->fail('Expected the request to fail');
        } catch(AblyRequestException $e) {
            $this->assertEquals( 3, count($ably->http->failedHosts), 'Expected to have tried main host and 2 fallback hosts' );
        }
    }

    /**
     * Verify accuracy of time (to within 2 seconds of actual time)
     */
    public function testTimeAndAccuracy() {
        $opts = array(
            'key' => 'fake.key:veryFake',
        );
        $ably = new AblyRest( $opts );

        $reportedTime = intval($ably->time());
        $actualTime = intval(microtime(true)*1000);

        $this->assertTrue( abs($reportedTime - $actualTime) < 2000,
            'The time difference was larger than 2000ms: ' . ($reportedTime - $actualTime) .'. Please check your system clock.' );
    }

    /**
     * Verify that time fails without valid host
     */
    public function testTimeFailsWithInvalidHost() {
        $ablyInvalidHost = new AblyRest( array(
            'key' => 'fake.key:veryFake',
            'restHost' => 'this.host.does.not.exist',
        ));

        $this->setExpectedException('Ably\Exceptions\AblyRequestException');
        $reportedTime = $ablyInvalidHost->time();
    }

    /**
     * Verify that custom request timeout works.
     * Connection/open timeout not reliably testable.
     */
    public function testHttpTimeout() {
        $ably = new AblyRest( array(
            'key' => 'fake.key:veryFake',
        ));

        $ablyTimeout = new AblyRest( array(
            'key' => 'fake.key:veryFake',
            'httpRequestTimeout' => 50, // 50 ms
        ));

        $ably->http->get('https://cdn.ably.io/lib/ably.js'); // should work
        $this->setExpectedException('Ably\Exceptions\AblyRequestException', '', 50003);
        $ablyTimeout->http->get('https://cdn.ably.io/lib/ably.js'); // guaranteed to take more than 50 ms
    }    
}


class HttpMockInitTest extends Http {
    public $lastUrl;
    
    public function request($method, $url, $headers = array(), $params = array()) {
        $this->lastUrl = $url;

        // mock response to /time
        return array(
            'headers' => '',
            'body' => array( round( microtime( true ) * 1000 ), 0 )
        );
    }
}


class HttpMockInitTestTimeout extends Http {
    public $failedHosts = array();
    public $failAttempts = 100; // number of attempts to time out before starting to return data
    public $httpErrorCode = 500;
    public $errorCode = 50003; // timeout
    
    public function request($method, $url, $headers = array(), $params = array()) {

        if ($this->failAttempts > 0) {
            preg_match('/\/\/([a-z0-9\.\-]+)\//', $url, $m);
            $this->failedHosts[] = $m[1];
            
            $this->failAttempts--;

            throw new AblyRequestException( 'Fake error', $this->errorCode, $this->httpErrorCode );
        }

        return array(
            'headers' => 'HTTP/1.1 200 OK'."\n",
            'body' => array( 999999, 0 ),
        );
    }
}