<?php
namespace tests;
use Ably\AblyRest;
use Ably\Http;
use Ably\Log;
use Ably\Exceptions\AblyRequestException;
use Ably\Models\ClientOptions;

require_once __DIR__ . '/factories/TestApp.php';

class InitTest extends \PHPUnit_Framework_TestCase {

    /**
     * Init library with a key only
     */
    public function testInitLibWithKeyOnly() {
        $key = 'fake.key:veryFake';
        new AblyRest( $key );
    }

    /**
     * Init library with a key in options
     */
    public function testInitLibWithKeyOption() {
        $key = 'fake.key:veryFake';
        new AblyRest( array('key' => $key ) );
    }

    /**
     * Init library with specified host
     */
    public function testInitLibWithSpecifiedHost() {
        $opts = array(
            'key' => 'fake.key:veryFake',
            'host'  => 'some.other.host',
            'httpClass' => 'tests\HttpMockInitTest',
        );
        $ably = new AblyRest( $opts );
        $ably->time(); // make a request
        $this->assertRegExp( '/^https?:\/\/some\.other\.host/', $ably->http->lastUrl, 'Unexpected host mismatch' );
    }

    /**
     * Verify encrypted defaults to true
     */
    public function testEncryptedDefaultIsTrue() {
        $opts = array(
            'key' => 'fake.key:veryFake',
            'httpClass' => 'tests\HttpMockInitTest',
        );
        $ably = new AblyRest( $opts );
        $ably->time(); // make a request
        $this->assertRegExp( '/^https:\/\//', $ably->http->lastUrl, 'Unexpected scheme mismatch' );
    }

    /**
     * Verify encrypted can be set to false
     */
    public function testEncryptedCanBeFalse() {
        $opts = array(
            'token' => 'fake.token',
            'httpClass' => 'tests\HttpMockInitTest',
            'tls' => false,
        );
        $ably = new AblyRest( $opts );
        $ably->time(); // make a request
        $this->assertRegExp( '/^http:\/\//', $ably->http->lastUrl, 'Unexpected scheme mismatch' );
    }


    /**
     * Verify if fallback hosts are working and used in correct order
     */
    public function testFallbackHosts() {
        $defaultOpts = new ClientOptions();
        $hostWithFallbacks = array_merge( array( $defaultOpts->host ), $defaultOpts->fallbackHosts );

        // reuse default options so that fallback host order is not randomized again
        $opts = array_merge ( $defaultOpts->toArray(), array(
            'key' => 'fake.key:veryFake',
            'httpClass' => 'tests\HttpMockInitTestTimeout',
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
     * Verify if fallback hosts are working - first 3 fail, 4th works
     */
    public function testFallbackHostsFailFirst3() {
        $opts = array(
            'key' => 'fake.key:veryFake',
            'httpClass' => 'tests\HttpMockInitTestTimeout',
        );
        $ably = new AblyRest( $opts );
        $ably->http->failAttempts = 3;
        $data = $ably->time(); // make a request
        
        $this->assertEquals( 999999, $data, 'Expected to receive test data' );
        $this->assertEquals( 3, count( $ably->http->failedHosts ), 'Expected 3 hosts to fail' );
    }

    /**
     * Verify if fallback host cycling is working - every host works at 1st attempt, fails at 2nd attempt
     */
    public function testFallbackHostsCycling() {
        $defaultOpts = new ClientOptions();
        $hostWithFallbacks = array_merge( array( $defaultOpts->host ), $defaultOpts->fallbackHosts );

        // reuse default options so that fallback host order is not randomized again
        $opts = array_merge ( $defaultOpts->toArray(), array(
            'key' => 'fake.key:veryFake',
            'httpClass' => 'tests\HttpMockInitTestTimeout',
        ) );
        $ably = new AblyRest( $opts );

        // try every host twice
        for ($i = 0; $i < count( $hostWithFallbacks ); $i++) {
            // host should work
            $ably->http->failAttempts = 0;
            $ably->time();

            // host should fail, host list should cycle
            $ably->http->failAttempts = 1;
            $ably->time();
        }

        $this->assertEquals( count( $hostWithFallbacks ), count( $ably->http->failedHosts ), 'Expected ' . count( $hostWithFallbacks ) . ' host failures' );
        $this->assertEquals( $hostWithFallbacks, $ably->http->failedHosts, 'Expected fallback hosts to cycle' );
    }

    /**
     * Init with log handler; check if called
     */
    public function testLogHandler() {
        $called = false;
        $opts = array(
            'key' => 'fake.key:veryFake',
            'logLevel' => Log::VERBOSE,
            'logHandler' => function( $level, $args ) use ( &$called ) {
                $called = true;
            },
        );

        new AblyRest( $opts );
        $this->assertTrue( $called, 'Log handler not called' );
    }

    /**
     * Init with log handler; check if not called when logLevel == NONE
     */
    public function testLoggerNotCalledWithDebugFalse() {
        $called = false;
        $opts = array(
            'key' => 'fake.key:veryFake',
            'logLevel' => Log::NONE,
            'logHandler' => function( $level, $args ) use ( &$called ) {
                $called = true;
            },
        );

        $ably = new AblyRest( $opts );
        $this->assertFalse( $called, 'Log handler incorrectly called' );
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
    
    public function request($method, $url, $headers = array(), $params = array()) {

        if ($this->failAttempts > 0) {
            preg_match('/\/\/([a-z0-9\.\-]+)\//', $url, $m);
            $this->failedHosts[] = $m[1];
            
            $this->failAttempts--;

            throw new AblyRequestException( 'Fake time out', 500, 50003 );
        }

        return array(
            'headers' => '',
            'body' => array( 999999, 0 )
        );
    }
}