<?php
namespace tests;
use Ably\AblyRest;
use Ably\Http;
use Ably\Exceptions\AblyRequestException;
use Ably\Models\ClientOptions;
use Ably\Models\TokenDetails;

require_once __DIR__ . '/factories/TestApp.php';

class AblyRestTest extends \PHPUnit\Framework\TestCase {

    protected static $testApp;
    protected static $defaultOptions;

    public static function setUpBeforeClass(): void {
        self::$testApp = new \tests\TestApp();
        self::$defaultOptions = self::$testApp->getOptions();
    }

    public static function tearDownAfterClass(): void {
        self::$testApp->release();
    }

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
        $ably = new AblyRest( ['key' => $key ] );
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
        $ably = new AblyRest( [
            'token' => "this_is_not_really_a_token",
        ] );

        $this->assertFalse( $ably->auth->isUsingBasicAuth(), 'Expected token auth to be used' );
    }

    /**
     * Init library with a tokenDetails in options
     */
    public function testInitLibWithTokenDetailsOption() {
        $ably = new AblyRest( [
            'tokenDetails' => new TokenDetails( "this_is_not_really_a_token" ),
        ] );

        $this->assertFalse( $ably->auth->isUsingBasicAuth(), 'Expected token auth to be used' );
    }

    /**
     * Init library with a specified host
     */
    public function testInitLibWithSpecifiedHost() {
        $opts = [
            'key' => 'fake.key:veryFake',
            'restHost'  => 'some.other.host',
            'httpClass' => 'tests\HttpMockInitTest',
        ];
        $ably = new AblyRest( $opts );
        $ably->time(); // make a request
        $this->assertRegExp( '/^https?:\/\/some\.other\.host/', $ably->http->lastUrl, 'Unexpected host mismatch' );
    }

    /**
     * Init library with a specified port
     */
    public function testInitLibWithSpecifiedPort() {
        $opts = [
            'key' => 'fake.key:veryFake',
            'restHost'  => 'some.other.host',
            'tlsPort' => 999,
            'httpClass' => 'tests\HttpMockInitTest',
        ];
        $ably = new AblyRest( $opts );
        $ably->time(); // make a request
        $this->assertStringContainsString(
            'https://' . $opts['restHost'] . ':' . $opts['tlsPort'],
            $ably->http->lastUrl,
            'Unexpected host/port mismatch'
        );

        $opts = [
            'token' => 'fakeToken',
            'restHost'  => 'some.other.host',
            'port' => 999,
            'tls' => false,
            'httpClass' => 'tests\HttpMockInitTest',
        ];
        $ably = new AblyRest( $opts );
        $ably->time(); // make a request
        $this->assertStringContainsString(
            'http://' . $opts['restHost'] . ':' . $opts['port'],
            $ably->http->lastUrl,
            'Unexpected host/port mismatch'
        );
    }

    /**
     * Init library with specified environment
     */
    public function testInitLibWithSpecifiedEnv() {
        $ably = new AblyRest( [
            'key' => 'fake.key:veryFake',
            'environment'  => 'sandbox',
            'httpClass' => 'tests\HttpMockInitTest',
        ] );
        $ably->time(); // make a request
        $this->assertRegExp( '/^https?:\/\/sandbox-rest\.ably\.io\//', $ably->http->lastUrl, 'Unexpected host mismatch' );
    }

    /**
     * Init library with specified environment AND host
     */
    public function testInitLibWithSpecifiedEnvHost() {
        $ably = new AblyRest( [
            'key' => 'fake.key:veryFake',
            'restHost'  => 'some.other.host',
            'environment'  => 'sandbox',
            'httpClass' => 'tests\HttpMockInitTest',
        ] );
        $ably->time(); // make a request
        $this->assertRegExp( '/^https?:\/\/sandbox-some\.other\.host\//', $ably->http->lastUrl, 'Unexpected host mismatch' );
    }

    /**
     * Verify encrypted defaults to true, makes a request to https://rest.ably.io/...
     */
    public function testTLSDefaultIsTrue() {
        $opts = [
            'key' => 'fake.key:veryFake',
            'httpClass' => 'tests\HttpMockInitTest',
        ];
        $ably = new AblyRest( $opts );
        $ably->time(); // make a request
        $this->assertRegExp( '/^https:\/\/rest\.ably\.io/', $ably->http->lastUrl, 'Unexpected scheme/url mismatch' );
    }

    /**
     * Verify encrypted can be set to false, makes a request to http://rest.ably.io/...
     */
    public function testTLSCanBeFalse() {
        $opts = [
            'token' => 'fake.token',
            'httpClass' => 'tests\HttpMockInitTest',
            'tls' => false,
        ];
        $ably = new AblyRest( $opts );
        $ably->time(); // make a request
        $this->assertRegExp( '/^http:\/\/rest\.ably\.io/', $ably->http->lastUrl, 'Unexpected scheme/url mismatch' );
    }

    /**
     * Verify that connection is encrypted when set to true explicitly, makes a request to https://rest.ably.io/...
     */
    public function testTLSExplicitTrue() {
        $opts = [
            'token' => 'fake.token',
            'httpClass' => 'tests\HttpMockInitTest',
            'tls' => true,
        ];
        $ably = new AblyRest( $opts );
        $ably->time(); // make a request
        $this->assertRegExp( '/^https:\/\/rest\.ably\.io/', $ably->http->lastUrl, 'Unexpected scheme/url mismatch' );
    }

    /**
     * Verify that fallback hosts are working and used in correct order
     */
    public function testFallbackHosts() {
        $defaultOpts = new ClientOptions();
        $hostWithFallbacks = array_merge( [ $defaultOpts->restHost ], $defaultOpts->fallbackHosts );
        $hostWithFallbacksSorted = $hostWithFallbacks; // copied by value
        sort($hostWithFallbacksSorted);

        $opts = [
            'key' => 'fake.key:veryFake',
            'httpClass' => 'tests\HttpMockInitTestTimeout',
            'httpMaxRetryCount' => 5,
        ];
        $ably = new AblyRest( $opts );
        try {
            $ably->time(); // make a request
            $this->fail('Expected the request to fail');
        } catch(AblyRequestException $e) {
            $this->assertEquals( $hostWithFallbacks[0], $ably->http->failedHosts[0],
                                 'Expected to try restHost first' );
            $this->assertNotEquals( $hostWithFallbacks, $ably->http->failedHosts,
                                    'Expected to have fallback hosts randomized' );

            $failedHostsSorted = $ably->http->failedHosts; // copied by value;
            sort($failedHostsSorted);
            $this->assertEquals( $hostWithFallbacksSorted, $failedHostsSorted,
                                 'Expected to have tried all the fallback hosts' );
        }
    }

    /**
     * Verify that custom restHost and custom fallbackHosts are working
     */
    public function testCustomHostAndFallbacks() {
        $defaultOpts = new ClientOptions([
            'restHost' => 'rest.custom.com',
            'fallbackHosts' => [
                'first-fallback.custom.com',
                'second-fallback.custom.com',
                'third-fallback.custom.com',
            ],
        ]);
        $hostWithFallbacks = array_merge( [ $defaultOpts->restHost ], $defaultOpts->fallbackHosts );
        $hostWithFallbacksSorted = $hostWithFallbacks; // copied by value
        sort($hostWithFallbacksSorted);

        $opts = array_merge ( $defaultOpts->toArray(), [
            'key' => 'fake.key:veryFake',
            'httpClass' => 'tests\HttpMockInitTestTimeout',
            'httpMaxRetryCount' => 3,
        ] );
        $ably = new AblyRest( $opts );
        try {
            $ably->time(); // make a request
            $this->fail('Expected the request to fail');
        } catch(AblyRequestException $e) {
           $this->assertEquals( $hostWithFallbacks[0], $ably->http->failedHosts[0], 'Expected to try restHost first' );
            // $this->assertNotEquals( $hostWithFallbacks, $ably->http->failedHosts, 'Expected to have fallback hosts randomized' ); // this may fail when randomized order matches the original order
            
           $failedHostsSorted = $ably->http->failedHosts; // copied by value;
           sort($failedHostsSorted);
           $this->assertEquals( $hostWithFallbacksSorted, $failedHostsSorted, 'Expected to have tried all the fallback hosts' );
        }
    }

    /**
     * Verify that fallback hosts are not called on a 400 error
     */
    public function testFallbackHosts400() {

        $opts = [
            'key' => 'fake.key:veryFake',
            'httpClass' => 'tests\HttpMockInitTestTimeout',
        ];

        $ably = new AblyRest( $opts );
        $ably->http->httpErrorCode = 401;
        $ably->http->errorCode = 40101; // auth error

        try {
            $ably->time(); // make a request
            $this->fail('Expected the request to fail');
        } catch(AblyRequestException $e) {
            $this->assertEquals( [ 'rest.ably.io' ], $ably->http->failedHosts, 'Expected to have tried only the default host' );
        }
    }

    /**
     * Verify that default fallback hosts are NOT used when using a custom host
     */
    public function testNoFallbackOnCustomHost() {

        // reuse default options so that fallback host order is not randomized again
        $opts = [
            'key' => 'fake.key:veryFake',
            'httpClass' => 'tests\HttpMockInitTestTimeout',
            'restHost' => 'custom.host.com',
        ];
        $ably = new AblyRest( $opts );
        try {
            $ably->time(); // make a request
            $this->fail('Expected the request to fail');
        } catch(AblyRequestException $e) {
            $this->assertEquals( [ 'custom.host.com' ], $ably->http->failedHosts, 'Expected to have tried only the custom host' );
        }
    }

    /**
     * Verify that fallback hosts are working - first 3 fail, 4th works
     */
    public function testFallbackHostsFailFirst3() {
        $opts = [
            'key' => 'fake.key:veryFake',
            'httpClass' => 'tests\HttpMockInitTestTimeout',
            'httpMaxRetryCount' => 5,
        ];
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
        $opts = [
            'key' => 'fake.key:veryFake',
            'httpClass' => 'tests\HttpMockInitTestTimeout',
            'httpMaxRetryCount' => 2,
        ];

        $ably = new AblyRest( $opts );
        try {
            $ably->time(); // make a request
            $this->fail('Expected the request to fail');
        } catch(AblyRequestException $e) {
            $this->assertEquals( 3, count($ably->http->failedHosts), 'Expected to have tried main host and 2 fallback hosts' );
        }
    }


    /**
     * RSC15f Cached fallback host
     */
    public function testCachedFallback() {
        $timeout = 2000;
        $ably = new AblyRest( array_merge( self::$defaultOptions, [
            'key' => self::$testApp->getAppKeyDefault()->string,
            'fallbackRetryTimeout' => $timeout,
            'httpClass' => 'tests\HttpMockCachedFallback',
            'fallbackHosts' => [
                'a.ably-realtime.com',
                'b.ably-realtime.com',
                'c.ably-realtime.com',
                'd.ably-realtime.com',
                'e.ably-realtime.com',
            ],
        ]));

        // The main host is called and there's an error
        $ably->time();
        $this->assertEquals( 1, $ably->http->errors );

        // The cached host is used: no error
        $ably->time();
        $ably->time();
        $ably->time();
        $this->assertEquals( 1, $ably->http->errors );

        // The cached host has expired, we've an error again
        sleep( $timeout / 1000 );
        $ably->time();
        $this->assertEquals( 2, $ably->http->errors );
    }

    /**
     * Verify accuracy of time (to within 2 seconds of actual time)
     */
    public function testTimeAndAccuracy() {
        $opts = [
            'key' => 'fake.key:veryFake',
        ];
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
        $ablyInvalidHost = new AblyRest( [
            'key' => 'fake.key:veryFake',
            'restHost' => 'this.host.does.not.exist',
        ]);

        $this->expectException(AblyRequestException::class);
        $reportedTime = $ablyInvalidHost->time();
    }

    /**
     * Verify that custom request timeout works.
     * Connection/open timeout not reliably testable.
     */
    public function testHttpTimeout() {
        $ably = new AblyRest( [
            'key' => 'fake.key:veryFake',
        ]);

        $ablyTimeout = new AblyRest( [
            'key' => 'fake.key:veryFake',
            'httpRequestTimeout' => 20, // 20 ms
        ]);

        $ably->http->get('https://cdn.ably.io/lib/ably.js'); // should work
        $this->expectException(AblyRequestException::class);
        $this->expectExceptionCode(50003);
        $ablyTimeout->http->get('https://cdn.ably.io/lib/ably.js'); // guaranteed to take more than 20 ms
    }
}


class HttpMockInitTest extends Http {
    public $lastUrl;
    
    public function request($method, $url, $headers = [], $params = []) {
        $this->lastUrl = $url;

        // mock response to /time
        return [
            'headers' => '',
            'body' => [ round( microtime( true ) * 1000 ), 0 ]
        ];
    }
}


class HttpMockInitTestTimeout extends Http {
    public $failedHosts = [];
    public $failAttempts = 100; // number of attempts to time out before starting to return data
    public $httpErrorCode = 500;
    public $errorCode = 50003; // timeout
    
    public function request($method, $url, $headers = [], $params = []) {

        if ($this->failAttempts > 0) {
            preg_match('/\/\/([a-z0-9\.\-]+)\//', $url, $m);
            $this->failedHosts[] = $m[1];
            
            $this->failAttempts--;

            throw new AblyRequestException( 'Fake error', $this->errorCode, $this->httpErrorCode );
        }

        return [
            'headers' => 'HTTP/1.1 200 OK'."\n",
            'body' => [ 999999, 0 ],
        ];
    }
}


class HttpMockCachedFallback extends Http {
    private $restHost;
    public $errors;

    public function __construct( $clientOptions ) {
        parent::__construct( $clientOptions );
        $this->restHost = $clientOptions->restHost;
        $this->errors = 0;
    }

    public function request( $method, $url, $headers = [], $params = [] ) {
        if ( parse_url($url, PHP_URL_HOST) == $this->restHost ) {
            $this->errors++;
            throw new AblyRequestException( 'fake error', 50000, 500 );
        }

        return parent::request($method, $url, $headers, $params);
    }
}
