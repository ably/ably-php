<?php
namespace tests;
use Ably\AblyRest;
use Ably\Http;
use Ably\Log;
use \Exception;

require_once __DIR__ . '/factories/TestApp.php';

class InitTest extends \PHPUnit_Framework_TestCase {

    /**
     * Init library with a key only
     */
    public function testInitLibWithKeyOnly() {
        $key = "fake.key:veryFake";
        new AblyRest( $key );
    }

    /**
     * Init library with a key in options
     */
    public function testInitLibWithKeyOption() {
        $key = "fake.key:veryFake";
        new AblyRest( array('key' => $key ) );
    }

    /**
     * Init library with specified host
     */
    public function testInitLibWithSpecifiedHost() {
        $opts = array(
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
            'httpClass' => 'tests\HttpMockInitTest',
            'tls' => false,
        );
        $ably = new AblyRest( $opts );
        $ably->time(); // make a request
        $this->assertRegExp( '/^http:\/\//', $ably->http->lastUrl, 'Unexpected scheme mismatch' );
    }

    /**
     * Init with log handler; check if called
     */
    public function testLogHandler() {
        $called = false;
        $opts = array(
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