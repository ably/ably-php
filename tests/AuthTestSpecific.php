<?php
namespace tests;
use Ably\AblyRest;

require_once __DIR__ . '/factories/TestApp.php';

class AuthTestSpecific extends \PHPUnit_Framework_TestCase {

    protected static $testApp;
    protected static $defaultOptions;

    public static function setUpBeforeClass() {
        self::$testApp = new TestApp();
        self::$defaultOptions = self::$testApp->getOptions();
    }

    public static function tearDownAfterClass() {
        self::$testApp->release();
    }

    /**
     * (RSA8f1) Request a token with a null value clientId, authenticate a client with the token,
     * publish a message without an explicit clientId, and ensure the message published does not
     * have a clientId. Check that Auth#clientId is null   
     */
    public function testRSA8f1() {
        $ablyMain = new AblyRest( array_merge( self::$defaultOptions, array(
            'key' => self::$testApp->getAppKeyDefault()->string,
        ) ) );

        $tokenDetails = $ablyMain->auth->requestToken( array(), array( 'clientId' => null ) ); // this will override the implicit '*'

        $ablyClient = new AblyRest( array_merge( self::$defaultOptions, array(
            'tokenDetails' => $tokenDetails,
        ) ) );

        $channel = $ablyClient->channels->get( 'persisted:RSA8f1' );
        $channel->publish( 'testEvent', 'testData' );

        $this->assertNull( $ablyClient->auth->getClientId(), 'Expected clientId to be null' );
        $this->assertNull( $channel->history()->items[0]->clientId, 'Expected message not to have a clientId' );
        $this->assertEquals( 'testData', $channel->history()->items[0]->data, 'Expected message payload to match' );
    }

    /**
     * (RSA8f2) Request a token with a null value clientId, authenticate a client with the token,
     * publish a message with an explicit clientId value, and ensure that the message is rejected    
     */
    public function testRSA8f2() {
        $ablyMain = new AblyRest( array_merge( self::$defaultOptions, array(
            'key' => self::$testApp->getAppKeyDefault()->string,
        ) ) );

        $tokenDetails = $ablyMain->auth->requestToken( array(), array( 'clientId' => null ) ); // this will override the implicit '*'

        $ablyClient = new AblyRest( array_merge( self::$defaultOptions, array(
            'tokenDetails' => $tokenDetails,
        ) ) );

        $channel = $ablyClient->channels->get( 'persisted:RSA8f2' );

        $this->setExpectedException( 'Ably\Exceptions\AblyRequestException' );
        $channel->publish( 'testEvent', 'testData', 'testClientId' );
    }

    /**
     * (RSA8f3) Request a token with a wildcard '*' value clientId, authenticate a client with the token,
     * publish a message without an explicit clientId, and ensure the message published does not have
     * a clientId. Check that Auth#clientId is null
     */
    public function testRSA8f3() {
        $ablyMain = new AblyRest( array_merge( self::$defaultOptions, array(
            'key' => self::$testApp->getAppKeyDefault()->string,
        ) ) );

        $tokenDetails = $ablyMain->auth->requestToken( array(), array( 'clientId' => '*' ) );

        $ablyClient = new AblyRest( array_merge( self::$defaultOptions, array(
            'tokenDetails' => $tokenDetails,
        ) ) );

        $channel = $ablyClient->channels->get( 'persisted:RSA8f3' );
        $channel->publish( 'testEvent', 'testData' );

        $this->assertNull( $ablyClient->auth->getClientId(), 'Expected clientId to be null' );
        $this->assertNull( $channel->history()->items[0]->clientId, 'Expected message not to have a clientId' );
        $this->assertEquals( 'testData', $channel->history()->items[0]->data, 'Expected message payload to match' );
    }

    /**
     * (RSA8f4) Request a token with a wildcard '*' value clientId, authenticate a client with the token,
     * publish a message with an explicit clientId value, and ensure that the message published has
     * the provided clientId
     */
    public function testRSA8f4() {
        $ablyMain = new AblyRest( array_merge( self::$defaultOptions, array(
            'key' => self::$testApp->getAppKeyDefault()->string,
        ) ) );

        $tokenDetails = $ablyMain->auth->requestToken( array(), array( 'clientId' => '*' ) );

        $ablyClient = new AblyRest( array_merge( self::$defaultOptions, array(
            'tokenDetails' => $tokenDetails,
        ) ) );

        $channel = $ablyClient->channels->get( 'persisted:RSA8f4' );
        $channel->publish( 'testEvent', 'testData', 'testClientId' );

        $this->assertNull( $ablyClient->auth->getClientId(), 'Expected clientId to be null' );
        $this->assertEquals( 'testClientId', $channel->history()->items[0]->clientId, 'Expected message clientId to match' );
        $this->assertEquals( 'testData', $channel->history()->items[0]->data, 'Expected message payload to match' );
    }
}