<?php
namespace tests;
use Ably\AblyRest;
use Ably\Auth;
use Ably\Exceptions\AblyException;

require_once __DIR__ . '/factories/TestApp.php';

class TokenTest extends \PHPUnit_Framework_TestCase {

    protected static $testApp;
    protected static $defaultOptions;
    protected static $ably;

    protected static $errorMarginMs = 10000;
    protected static $capabilityAll;
    protected static $tokenParams = array();
    protected static $defaultTTLms = 3600000; // 1 hour in milliseconds

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
            ($tokenDetails->issued >= $requestTime - self::$errorMarginMs)
            && ($tokenDetails->issued <= $requestTime + self::$errorMarginMs),
            'Unexpected issued time'
        );
        $this->assertEquals( $tokenDetails->issued + self::$defaultTTLms, $tokenDetails->expires, 'Unexpected expires time' );
        $this->assertEquals( self::$capabilityAll, json_decode($tokenDetails->capability), 'Unexpected default capability' );
    }

    /**
     * requestToken with explicit timestamp
     */
    public function testRequestTokenWithExplicitTimestamp() {
        $requestTime = self::$ably->time();
        $tokenParams = array_merge(self::$tokenParams, array(
            'timestamp' => $requestTime
        ));
        $tokenDetails = self::$ably->auth->requestToken( $tokenParams );
        $this->assertNotNull( $tokenDetails->token, 'Expected token id' );
        $this->assertTrue(
            ($tokenDetails->issued >= $requestTime - self::$errorMarginMs)
            && ($tokenDetails->issued <= $requestTime + self::$errorMarginMs),
            'Unexpected issued time'
        );
        $this->assertEquals( $tokenDetails->issued + self::$defaultTTLms, $tokenDetails->expires, 'Unexpected expires time' );
        $this->assertEquals( self::$capabilityAll, json_decode($tokenDetails->capability), 'Unexpected capability' );
    }

    /**
     * requestToken with explicit, invalid timestamp
     */
    public function testRequestTokenWithExplicitInvalidTimestamp() {
        $requestTime = self::$ably->time();
        $tokenParams = array_merge(self::$tokenParams, array(
            'timestamp' => $requestTime - 30 * 60 * 1000 // half an hour ago
        ));
        $this->setExpectedException( 'Ably\Exceptions\AblyException', 'Timestamp not current', 40101 );
        self::$ably->auth->requestToken( $tokenParams );
    }

    /**
     * requestToken with system timestamp
     */
    public function testRequestWithSystemTimestamp() {
        $requestTime = time() * 1000;
        $authOptions = array('query' => true);
        $tokenDetails = self::$ably->auth->requestToken( array(), $authOptions );
        $this->assertNotNull( $tokenDetails->token, 'Expected token id' );
        $this->assertTrue(
            ($tokenDetails->issued >= $requestTime - self::$errorMarginMs)
            && ($tokenDetails->issued <= $requestTime + self::$errorMarginMs),
            'Unexpected issued time'
        );
        $this->assertEquals( $tokenDetails->issued + self::$defaultTTLms, $tokenDetails->expires, 'Unexpected expires time' );
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
        $tokenDetails = self::$ably->auth->requestToken( $tokenParams );
        $this->assertNotNull( $tokenDetails->token, 'Expected token id' );
        $this->assertTrue(
            ($tokenDetails->issued >= $requestTime - self::$errorMarginMs)
            && ($tokenDetails->issued <= $requestTime + self::$errorMarginMs),
            'Unexpected issued time'
        );
        $this->assertEquals( $tokenDetails->issued + self::$defaultTTLms, $tokenDetails->expires, 'Unexpected expires time' );
        $this->assertEquals( self::$capabilityAll, json_decode($tokenDetails->capability), 'Unexpected capability' );
        $this->assertEquals( $tokenParams['clientId'], $tokenDetails->clientId, 'Unexpected clientId' );
    }

    /**
     * Token generation with capability that subsets key capability
     */
    public function testTokenGenerationWithCapabilityKey() {
        $capability = array( 'onlythischannel' => array('subscribe') );
        $tokenParams = array( 'capability' => $capability );
        $tokenDetails = self::$ably->auth->requestToken( $tokenParams );
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

        $ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'key' => 'fake.key:veryFake',
        ) ) );

        $tokenDetails = $ably->auth->requestToken( array(), $authOptions ); // fake key should get overridden with the real key
        $capability_obj = json_decode( $key->capability, false );

        $this->assertNotNull( $tokenDetails->token, 'Expected token id' );
        $this->assertEquals( $capability_obj, json_decode($tokenDetails->capability), 'Unexpected capability' );
    }

    /**
     * Token generation with specified ttl
     */
    public function testTokenGenerationWithSpecifiedTTL() {
        $tokenParams = array( 'ttl' => 60 * 1000 );
        $tokenDetails = self::$ably->auth->requestToken( $tokenParams );
        $this->assertNotNull( $tokenDetails->token, 'Expected token id' );
        $this->assertEquals( $tokenDetails->issued + 60 * 1000, $tokenDetails->expires, 'Unexpected expires time' );
    }

    /**
     * Token generation with default ttl
     */
    public function testTokenGenerationWithDefaultTTL() {
        $tokenDetails = self::$ably->auth->requestToken();
        $this->assertNotNull( $tokenDetails->token, 'Expected token id' );
        $this->assertEquals( $tokenDetails->issued + self::$defaultTTLms, $tokenDetails->expires, 'Expected the default expire time to be 1 hour' );
    }

    /**
     * Token generation with excessive ttl
     */
    public function testTokenGenerationWithExcessiveTTL() {
        $oneYearMs = 365*24*3600*1000;
        $tokenParams = array( 'ttl' => $oneYearMs );
        $this->setExpectedException( 'Ably\Exceptions\AblyException', '', 40003 );
        self::$ably->auth->requestToken( $tokenParams );
    }

    /**
     * Token generation with invalid ttl
     */
    public function testTokenGenerationWithInvalidTTL() {
        $tokenParams = array( 'ttl' => -1 * 1000 );
        $this->setExpectedException( 'Ably\Exceptions\AblyException', '', 40003 );
        self::$ably->auth->requestToken( $tokenParams );
    }

    /**
     * Automatic token renewal on expiration (known time)
     */
    public function testTokenRenewalKnownExpiration() {
        $ablyKeyAuth = self::$ably;

        $options = array_merge( self::$defaultOptions, array(
            'authCallback' => function( $tokenParams ) use( &$ablyKeyAuth ) {
                $capability = array( 'testchannel' => array('publish') );
                $tokenParams = array(
                    'ttl' => 2 * 1000 + Auth::TOKEN_EXPIRY_MARGIN, // 2 seconds + expiry margin
                    'capability' => $capability,
                );
                return $ablyKeyAuth->auth->requestToken( $tokenParams );
            }
        ) );

        $ablyTokenAuth = new AblyRest( $options );
        $ablyTokenAuth->auth->authorise();
        $tokenBefore = $ablyTokenAuth->auth->getTokenDetails()->token;

        $channel = $ablyTokenAuth->channel( 'testchannel' );

        // do an authorised request
        $channel->publish( 'test', 'test' );
        $tokenReq1 = $ablyTokenAuth->auth->getTokenDetails()->token;

        sleep(3);

        $channel->publish( 'test', 'test' );
        $tokenReq2 = $ablyTokenAuth->auth->getTokenDetails()->token;

        $this->assertEquals( $tokenBefore, $tokenReq1, 'Expected token not to change before expiration' );
        $this->assertFalse( $tokenReq1 == $tokenReq2, 'Expected token to change after expiration' );
    }

    /**
     * Automatic token renewal on expiration (unknown time)
     */
    public function testTokenRenewalUnknownExpiration() {
        $ablyKeyAuth = self::$ably;

        $options = array_merge( self::$defaultOptions, array(
            'authCallback' => function( $tokenParams ) use( &$ablyKeyAuth ) {
                $capability = array( 'testchannel' => array('publish') );
                $tokenParams = array(
                    'ttl' => 2 * 1000, // 2 seconds
                    'capability' => $capability,
                );
                $tokenDetails = $ablyKeyAuth->auth->requestToken( $tokenParams );
                return $tokenDetails->token; // returning just the token string, not TokenDetails => expiry time is unknown
            }
        ) );

        $ablyTokenAuth = new AblyRest( $options );
        $ablyTokenAuth->auth->authorise();
        $tokenBefore = $ablyTokenAuth->auth->getTokenDetails()->token;

        $channel = $ablyTokenAuth->channel( 'testchannel' );

        // do an authorised request
        $channel->publish( 'test', 'test' );
        $tokenReq1 = $ablyTokenAuth->auth->getTokenDetails()->token;

        sleep(3);

        $channel->publish( 'test', 'test' );
        $tokenReq2 = $ablyTokenAuth->auth->getTokenDetails()->token;

        $this->assertEquals( $tokenBefore, $tokenReq1, 'Expected token not to change before expiration' );
        $this->assertFalse( $tokenReq1 == $tokenReq2, 'Expected token to change after expiration' );
    }

    /**
     * Automatic token renewal failure raises an exception
     */
    public function testTokenRenewalUnknownExpirationFailure() {
        $ablyKeyAuth = self::$ably;

        $tokenParams = array(
            'ttl' => 2 * 1000 // 2 seconds
        );
        $tokenDetails = $ablyKeyAuth->auth->requestToken( $tokenParams );
        $token = $tokenDetails->token;

        $options = array_merge( self::$defaultOptions, array(
            'authCallback' => function( $tokenParams ) use( &$token ) {
                return $token;
            }
        ) );

        $ablyTokenAuth = new AblyRest( $options );
        $channel = $ablyTokenAuth->channel( 'testchannel' );

        // do an authorised request with the valid token
        $channel->publish( 'test', 'test' );

        sleep(3);

        $this->setExpectedException( 'Ably\Exceptions\AblyException', '', 40142 ); // token expired
        $channel->publish( 'test', 'test' ); // token is no longer valid
    }

    /**
     * Automatic token renewal on expiration (unknown time) should fail with no means of renewal
     */
    public function testFailingTokenRenewalUnknownExpiration() {
        $ablyKeyAuth = self::$ably;

        $tokenParams = array(
            'ttl' => 2 * 1000, // 2 seconds
        );
        $tokenDetails = $ablyKeyAuth->auth->requestToken( $tokenParams );

        $options = array_merge( self::$defaultOptions, array(
            'token' => $tokenDetails->token,
        ) );
        $ablyTokenAuth = new AblyRest( $options );
        $channel = $ablyTokenAuth->channel( 'testchannel' );
        $channel->publish( 'test', 'test' ); // this should work

        sleep(3);

        $this->setExpectedException( 'Ably\Exceptions\AblyException', '', 40101 );
        $channel->publish( 'test', 'test' ); // this should fail
    }
}
