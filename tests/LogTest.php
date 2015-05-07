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
     * Test if logger outputs to stdout and uses warning level as default
     */
    public function testLog() {
        $opts = array(
            'key' => 'fake.key:veryFake',
        );
        $ably = new AblyRest( $opts );

        $test = $this;

        $this->setOutputCallback(function($out) use($test) {
            if (strpos($out, 'This is a test warning.') === false) {
                $test->fail('Expected warning level to be logged.');
            }

            if (strpos($out, 'This is a test error.') === false) {
                $test->fail('Expected error level to be logged.');
            }

            if (strpos($out, 'This is a test verbose message.') !== false) {
                $test->fail('Expected verbose level NOT to be logged.');
            }

            if (strpos($out, 'This is a test debug message.') !== false) {
                $test->fail('Expected debug level NOT to be logged.');
            }
        });
        
        $this->logMessages();
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

        $ably = new AblyRest( $opts );
        $this->logMessages();
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
        $this->logMessages();
        $this->assertFalse( $called, 'Log handler incorrectly called' );
    }
}
