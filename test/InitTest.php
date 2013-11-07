<?php

require_once dirname(__FILE__) . '/../lib/ably.php';
require_once 'factories/TestOption.php';

class InitTest extends PHPUnit_Framework_TestCase {

    protected static $options;
    protected $ably;

    public static function setUpBeforeClass() {

        self::$options = TestOption::get_instance()->get_opts();

    }

    public static function tearDownAfterClass() {
        TestOption::get_instance()->clear_opts();
    }

    /**
     * Init library with a key only
     */
    public function testInitLibWithKeyOnly() {
        echo '==testInitLibWithKeyOnly()';
        try {
            $key = self::$options['keys'][0];
            new AblyRest( $key->key_str );
        } catch (Exception $e) {
            $this->fail('Unexpected exception instantiating library');
        }
    }

    /**
     * Init library with a key in options
     */
    public function testInitLibWithKeyOption() {
        echo '==testInitLibWithKeyOption()';
        try {
            $key = self::$options['keys'][0];
            new AblyRest( array('key' => $key->key_str) );
        } catch (Exception $e) {
            $this->fail('Unexpected exception instantiating library');
        }
    }

    /**
     * Init library with appId
     */
    public function testInitLibWithAppId() {
        echo '==testInitLibWithAppId()';
        try {
            new AblyRest( array('appId' => self::$options['appId']) );
        } catch (Exception $e) {
            $this->fail('Unexpected exception instantiating library');
        }
    }

    /**
     * Verify library fails to init when both appId and key are missing
     */
    public function testFailInitOnMissingAppIdAndKey() {
        echo '==testFailInitOnMissingAppIdAndKey()';
        try {
            new AblyRest( array() );
            $this->fail('Unexpected success instantiating library');
        } catch (Exception $e) {
            # do nothing
        }
    }

    /**
     * Init library with specified host
     */
    public function testInitLibWithSpecifiedHost() {
        echo '==testInitLibWithSpecifiedHost()';
        try {
            $opts = array(
                'appId' => self::$options['appId'],
                'host'  => 'some.other.host',
            );
            $ably = new AblyRest( $opts );
            $this->assertEquals( $opts['host'], $ably->get_setting('host'), 'Unexpected host mismatch' );
        } catch (Exception $e) {
            $this->fail('Unexpected exception instantiating library');
        }
    }

    /**
     * Init library with specified port
     */
    public function testInitLibWithSpecifiedPort() {
        echo '==testInitLibWithSpecifiedPort()';
        try {
            $opts = array(
                'appId' => self::$options['appId'],
                'port'  => 9999,
            );
            $ably = new AblyRest( $opts );
            $this->assertEquals( $opts['port'], $ably->get_setting('port'), 'Unexpected port mismatch' );
        } catch (Exception $e) {
            $this->fail('Unexpected exception instantiating library');
        }
    }

    /**
     * Verify encrypted defaults to true
     */
    public function testEncryptedDefaultIsTrue() {
        echo '==testEncryptedDefaultIsTrue()';
        try {
            $opts = array(
                'appId' => self::$options['appId'],
            );
            $ably = new AblyRest( $opts );
            $this->assertEquals( 'https', $ably->get_setting('scheme'), 'Unexpected scheme mismatch' );
        } catch (Exception $e) {
            $this->fail('Unexpected exception instantiating library');
        }
    }

    /**
     * Verify encrypted can be set to false
     */
    public function testEncryptedCanBeFalse() {
        echo '==testEncryptedCanBeFalse()';
        try {
            $opts = array(
                'appId' => self::$options['appId'],
                'encrypted' => false,
            );
            $ably = new AblyRest( $opts );
            $this->assertEquals( 'http', $ably->get_setting('scheme'), 'Unexpected scheme mismatch' );
        } catch (Exception $e) {
            $this->fail('Unexpected exception instantiating library');
        }
    }

    /**
     * Init with log handler; check called
     */
    protected $init8_logCalled = false;
    public function testLoggerIsCalledWithDebugTrue() {
        echo '==testLoggerIsCalledWithDebugTrue()';
        try {
            $opts = array(
                'appId' => self::$options['appId'],
                'debug' => function( $output ) {
                    $this->init8_logCalled = true;
                    return $output;
                },
            );
            new AblyRest( $opts );
            $this->assertTrue( $this->init8_logCalled, 'Log handler not called' );
        } catch (Exception $e) {
            $this->fail('Unexpected exception instantiating library');
        }
    }

    /**
     * Init with log handler; check not called if logLevel == NONE
     */
    public function testLoggerNotCalledWithDebugFalse() {
        echo '==testLoggerIsCalledWithDebugFalse()';
        try {
            $opts = array(
                'appId' => self::$options['appId'],
                'debug' => false,
            );
            $ably = new AblyRest( $opts );
            # There is no logLevel in the PHP library so we'll simply assert log_action returns false
            $this->assertFalse( $ably->log_action('test()', 'called'), 'Log handler incorrectly called' );
        } catch (Exception $e) {
            $this->fail('Unexpected exception instantiating library');
        }
    }
}