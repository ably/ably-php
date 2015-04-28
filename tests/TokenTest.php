<?php
namespace tests;
use Ably\AblyRest;
use Ably\Exceptions\AblyException;

require_once __DIR__ . '/factories/TestApp.php';

class TokenTest extends \PHPUnit_Framework_TestCase {

    protected static $testApp;
    protected static $defaultOptions;
    protected static $ably;

    protected static $errorMarginSeconds = 10;
    protected static $errorMarginMillis = 10000;
    protected static $capabilityAll;
    protected static $tokenParams = array();

    public static function setUpBeforeClass() {
        self::$testApp = new TestApp();
        self::$defaultOptions = self::$testApp->getOptions();
        self::$ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'key' => self::$testApp->getAppKeyDefault()->string,
        ) ) );

        self::$capabilityAll = json_decode(json_encode(array('*' => array('*'))));
    }

    public static function tearDownAfterClass() {
        self::$testApp->release();
    }

    /**
     * Base requestToken case with empty params
     */
    public function testBaseRequestTokenWithEmptyParams() {
        $requestTime = self::$ably->time();
        $tokenDetails = self::$ably->auth->requestToken();
        $this->assertNotNull( $tokenDetails->token, 'Expected token id' );
        $this->assertTrue(
            ($tokenDetails->issued >= $requestTime - self::$errorMarginMillis)
            && ($tokenDetails->issued <= $requestTime + self::$errorMarginMillis),
            'Unexpected issued time'
        );
        $this->assertEquals( $tokenDetails->issued + 60*60*1000, $tokenDetails->expires, 'Unexpected expires time' );
        $this->assertEquals( self::$capabilityAll, json_decode($tokenDetails->capability), 'Unexpected capability' );
    }

    /**
     * requestToken with explicit timestamp
     */
    public function testRequestTokenWithExplicitTimestamp() {
        $requestTime = self::$ably->time();
        $params = array_merge(self::$tokenParams, array(
            'timestamp' => $requestTime
        ));
        $tokenDetails = self::$ably->auth->requestToken( array(), $params );
        $this->assertNotNull( $tokenDetails->token, 'Expected token id' );
        $this->assertTrue(
            ($tokenDetails->issued >= $requestTime - self::$errorMarginMillis)
            && ($tokenDetails->issued <= $requestTime + self::$errorMarginMillis),
            'Unexpected issued time'
        );
        $this->assertEquals( $tokenDetails->issued + 60*60*1000, $tokenDetails->expires, 'Unexpected expires time' );
        $this->assertEquals( self::$capabilityAll, json_decode($tokenDetails->capability), 'Unexpected capability' );
    }

    /**
     * requestToken with explicit, invalid timestamp
     */
    public function testRequestTokenWithExplicitInvalidTimestamp() {
        $requestTime = self::$ably->time();
        $params = array_merge(self::$tokenParams, array(
            'timestamp' => $requestTime - 30 * 60 * 1000
        ));
        try {
            self::$ably->auth->requestToken( array(), $params );
            $this->fail('Expected token request rejection');
        } catch (AblyException $e) {
            $this->assertEquals( 401, $e->getCode(), 'Unexpected error code' );
        }
    }

    /**
     * requestToken with system timestamp
     */
    public function testRequestWithSystemTimestamp() {
        $requestTime = time() * 1000;
        $authOptions = array('query' => true);
        $tokenDetails = self::$ably->auth->requestToken( $authOptions );
        $this->assertNotNull( $tokenDetails->token, 'Expected token id' );
        $this->assertTrue(
            ($tokenDetails->issued >= $requestTime - self::$errorMarginMillis)
            && ($tokenDetails->issued <= $requestTime + self::$errorMarginMillis),
            'Unexpected issued time'
        );
        $this->assertEquals( $tokenDetails->issued + 60*60*1000, $tokenDetails->expires, 'Unexpected expires time' );
        $this->assertEquals( self::$capabilityAll, json_decode($tokenDetails->capability), 'Unexpected capability' );
    }

    /**
     * Request token with a clientId specified
     */
    public function testRequestWithClientId() {
        $requestTime = self::$ably->time();
        $tokenParams = array(
            'clientId' => 'test client id',
        );
        $tokenDetails = self::$ably->auth->requestToken( array(), $tokenParams );
        $this->assertNotNull( $tokenDetails->token, 'Expected token id' );
        $this->assertTrue(
            ($tokenDetails->issued >= $requestTime - self::$errorMarginMillis)
            && ($tokenDetails->issued <= $requestTime + self::$errorMarginMillis),
            'Unexpected issued time'
        );
        $this->assertEquals( $tokenDetails->issued + 60*60*1000, $tokenDetails->expires, 'Unexpected expires time' );
        $this->assertEquals( self::$capabilityAll, json_decode($tokenDetails->capability), 'Unexpected capability' );
        $this->assertEquals( $tokenParams['clientId'], $tokenDetails->clientId, 'Unexpected clientId' );
    }

    /**
     * Token generation with capability that subsets key capability
     */
    public function testTokenGenerationWithCapabilityKey() {
        $capability = array( 'onlythischannel' => array('subscribe') );
        $tokenParams = array( 'capability' => $capability );
        $tokenDetails = self::$ably->auth->requestToken( array(), $tokenParams );
        $this->assertNotNull( $tokenDetails->token, 'Expected token id' );
        $this->assertEquals( $capability, (array) json_decode($tokenDetails->capability), 'Unexpected capability' );
    }

    /**
     * Token generation with specified key
     */
    public function testTokenGenerationWithSpecifiedKey() {
        $key = self::$testApp->getAppKeyWithCapabilities();

        $authOptions = array(
            'key' => $key->string,
        );
        $tokenDetails = self::$ably->auth->requestToken( $authOptions );
        $capability_obj = json_decode( $key->capability, false );

        $this->assertNotNull( $tokenDetails->token, 'Expected token id' );
        $this->assertEquals( $capability_obj, json_decode($tokenDetails->capability), 'Unexpected capability' );
    }

    /**
     * Token generation with specified ttl
     */
    public function testTokenGenerationWithSpecifiedTTL() {
        $tokenParams = array( 'ttl' => 60 * 1000 );
        $tokenDetails = self::$ably->auth->requestToken( array(), $tokenParams );
        $this->assertNotNull( $tokenDetails->token, 'Expected token id' );
        $this->assertEquals( $tokenDetails->issued + 60 * 1000, $tokenDetails->expires, 'Unexpected expires time' );
    }

    /**
     * Token generation with excessive ttl
     */
    public function testTokenGenerationWithExcessiveTTL() {
        $tokenParams = array( 'ttl' => 365*24*60*60*1000 );
        try {
            self::$ably->auth->requestToken( array(), $tokenParams );
            $this->fail( 'Expected token request rejection' );
        } catch (AblyException $e) {
            $this->assertEquals( 40003, $e->getAblyCode(), 'Unexpected error code' );
        }
    }

    /**
     * Token generation with invalid ttl
     */
    public function testTokenGenerationWithInvalidTTL() {
        $tokenParams = array( 'ttl' => -1 * 1000 );
        try {
            self::$ably->auth->requestToken( array(), $tokenParams );
            $this->fail( 'Expected token request rejection' );
        } catch (AblyException $e) {
            $this->assertEquals( 40003, $e->getAblyCode(), 'Unexpected error code' );
        }
    }

    /**
     * Automatic token renewal on expiration (known time)
     */
    public function testTokenRenewalKnownExpiration() {

        $keyName = self::$testApp->getAppKeyDefault()->name;
        $ablyKeyAuth = self::$ably;

        $options = array_merge( self::$defaultOptions, array(
            'authCallback' => function( $tokenParams ) use( &$ablyKeyAuth ) {
                $capability = array( 'testchannel' => array('publish') );
                $tokenParams = array(
                    'ttl' => 2 * 1000, // 2 seconds
                    'capability' => $capability,
                );
                return $ablyKeyAuth->auth->requestToken( array(), $tokenParams );
            }
        ) );

        unset( $options['key'] );

        $ablyTokenAuth = new AblyRest( $options );
        $ablyTokenAuth->auth->authorise();
        $tokenBefore = $ablyTokenAuth->auth->getTokenDetails()->token;

        $channel = $ablyTokenAuth->channel( 'testchannel' );

        // do an authorised request
        $channel->publish( 'test', 'test' );
        $tokenReq1 = $ablyTokenAuth->auth->getTokenDetails()->token;

        sleep(2);

        $channel->publish( 'test', 'test' );
        $tokenReq2 = $ablyTokenAuth->auth->getTokenDetails()->token;

        $this->assertEquals( $tokenBefore, $tokenReq1, 'Expected token not to change before expiration' );
        $this->assertFalse( $tokenReq1 == $tokenReq2, 'Expected token to change after expiration' );
    }

    /**
     * Automatic token renewal on expiration (unknown time)
     */
    public function testTokenRenewalUnknownExpiration() {

        $keyName = self::$testApp->getAppKeyDefault()->name;
        $ablyKeyAuth = self::$ably;

        $options = array_merge( self::$defaultOptions, array(
            'authCallback' => function( $tokenParams ) use( &$ablyKeyAuth ) {
                $capability = array( 'testchannel' => array('publish') );
                $tokenParams = array(
                    'ttl' => 2 * 1000, // 2 seconds
                    'capability' => $capability,
                );
                $tokenDetails = $ablyKeyAuth->auth->requestToken( array(), $tokenParams );
                return $tokenDetails->token;
            }
        ) );

        unset( $options['key'] );

        $ablyTokenAuth = new AblyRest( $options );
        $ablyTokenAuth->auth->authorise();
        $tokenBefore = $ablyTokenAuth->auth->getTokenDetails()->token;

        $channel = $ablyTokenAuth->channel( 'testchannel' );

        // do an authorised request
        $channel->publish( 'test', 'test' );
        $tokenReq1 = $ablyTokenAuth->auth->getTokenDetails()->token;

        sleep(2);

        $channel->publish( 'test', 'test' );
        $tokenReq2 = $ablyTokenAuth->auth->getTokenDetails()->token;

        $this->assertEquals( $tokenBefore, $tokenReq1, 'Expected token not to change before expiration' );
        $this->assertFalse( $tokenReq1 == $tokenReq2, 'Expected token to change after expiration' );
    }
}