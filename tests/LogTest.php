<?php
namespace tests;
use Ably\AblyRest;
use Ably\Log;

require_once __DIR__ . '/factories/TestApp.php';

class LogTest extends \PHPUnit_Framework_TestCase {

    public static function tearDownAfterClass() {
        // ensure the logger is reset to default
        $ably = new AblyRest( array(
            'key' => 'fake.key:totallyFake'
        ) );
    }

    private function logMessages() {
        Log::v('This is a test verbose message.');
        Log::d('This is a test debug message.');
        Log::w('This is a test warning.');
        Log::e('This is a test error.');
    }

    /**
     * Test if logger uses warning level as default
     */
    public function testLogDefault() {
        $out = '';

        $opts = array(
            'key' => 'fake.key:veryFake',
            'logHandler' => function( $level, $args ) use ( &$out ) {
                $out .= $args[0] . "\n";
            },
        );
        $ably = new AblyRest( $opts );

        $this->logMessages();

        if (strpos($out, 'This is a test warning.') === false) {
            $this->fail('Expected warning level to be logged.');
        }

        if (strpos($out, 'This is a test error.') === false) {
            $this->fail('Expected error level to be logged.');
        }

        if (strpos($out, 'This is a test verbose message.') !== false) {
            $this->fail('Expected verbose level NOT to be logged.');
        }

        if (strpos($out, 'This is a test debug message.') !== false) {
            $this->fail('Expected debug level NOT to be logged.');
        }
    }

    /**
     * Test verbose log level with a handler
     */
    public function testLogVerbose() {
        $out = '';

        $opts = array(
            'key' => 'fake.key:veryFake',
            'logLevel' => Log::VERBOSE,
            'logHandler' => function( $level, $args ) use ( &$out ) {
                $out .= $args[0] . "\n";
            },
        );

        $ably = new AblyRest( $opts );
        $this->logMessages();
        
        if (strpos($out, 'This is a test warning.') === false) {
            $this->fail('Expected warning level to be logged.');
        }

        if (strpos($out, 'This is a test error.') === false) {
            $this->fail('Expected error level to be logged.');
        }

        if (strpos($out, 'This is a test verbose message.') === false) {
            $this->fail('Expected verbose level to be logged.');
        }

        if (strpos($out, 'This is a test debug message.') === false) {
            $this->fail('Expected debug level to be logged.');
        }
    }

    /**
     * Test log level == NONE
     */
    public function testLogNone() {
        $called = false;
        $opts = array(
            'key' => 'fake.key:veryFake',
            'logLevel' => Log::NONE,
            'logHandler' => function( $level, $args ) use ( &$called ) {
                $called = true;
            },
        );

        $ably = new AblyRest( $opts );
        $this->logMessages();
        $this->assertFalse( $called, 'Log handler incorrectly called' );
    }
}
