<?php
namespace tests;
use Ably\AblyRest;
use Ably\Http;
use Ably\Models\TokenDetails;
use Ably\Models\TokenRequest;
use Ably\Exceptions\AblyRequestException;

require_once __DIR__ . '/factories/TestApp.php';

class AuthTest extends \PHPUnit_Framework_TestCase {

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
     * Init library with a key over unsecure connection
     */
    public function testAuthWithKeyInsecure() {
        $this->setExpectedException( 'Ably\Exceptions\AblyException', '', 40103 );

        $ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'key' => self::$testApp->getAppKeyDefault()->string,
            'tls' => false,
        ) ) );
    }

    /**
     * Init library without any valid auth
     */
    public function testNoAuthParams() {
        $this->setExpectedException( 'Ably\Exceptions\AblyException', '', 40103 );

        $ably = new AblyRest( );
    }

    /**
     * Init library with a token callback that returns a signed TokenRequest
     */
    public function testAuthWithTokenCallbackTokenRequest() {
        $callbackCalled = false;

        $ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'authCallback' => function( $tokenParams ) use( &$callbackCalled ) {
                $callbackCalled = true;

                return new TokenRequest( array(
                    'token' => 'this_is_not_really_a_token_request',
                    'timestamp' => time()*1000,
                    'keyName' => 'fakeKeyName',
                    'mac' => 'not_really_hmac',
                ) );
            },
            'httpClass' => 'tests\HttpMockAuthTest',
        ) ) );
        
        $ably->auth->authorise();

        $this->assertTrue( $callbackCalled, 'Expected token callback to be called' );
        $this->assertEquals( 'mock_token_requestToken', $ably->auth->getTokenDetails()->token, 'Expected mock token to be used' );
        $this->assertFalse( $ably->auth->isUsingBasicAuth(), 'Expected token auth to be used' );
    }

    /**
     * Init library with a token callback that returns TokenDetails
     */
    public function testAuthWithTokenCallbackTokenDetails() {
        $callbackCalled = false;

        $ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'authCallback' => function( $tokenParams ) use( &$callbackCalled ) {
                $callbackCalled = true;

                return new TokenDetails( array(
                    'token' => 'mock_TokenDetails',
                    'issued' => time()*1000,
                    'expires' => time()*1000 + 3600*1000,
                ) );
            },
            'httpClass' => 'tests\HttpMockAuthTest',
        ) ) );
        
        $ably->auth->authorise();

        $this->assertTrue( $callbackCalled, 'Expected token callback to be called' );
        $this->assertEquals( 'mock_TokenDetails', $ably->auth->getTokenDetails()->token, 'Expected mock token to be used' );
        $this->assertFalse( $ably->auth->isUsingBasicAuth(), 'Expected token auth to be used' );
    }

    /**
     * Init library with a token callback that returns token as a string
     */
    public function testAuthWithTokenCallbackTokenString() {
        $callbackCalled = false;

        $ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'authCallback' => function( $tokenParams ) use( &$callbackCalled ) {
                $callbackCalled = true;

                return 'mock_tokenString';
            },
            'httpClass' => 'tests\HttpMockAuthTest',
        ) ) );
        
        $ably->auth->authorise();

        $this->assertTrue( $callbackCalled, 'Expected token callback to be called' );
        $this->assertEquals( 'mock_tokenString', $ably->auth->getTokenDetails()->token, 'Expected mock token to be used' );
        $this->assertFalse( $ably->auth->isUsingBasicAuth(), 'Expected token auth to be used' );
    }

    /**
     * Init library with an authUrl that returns a signed TokenRequest
     */
    public function testAuthWithAuthUrlTokenRequest() {
        $method = 'POST';

        $ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'authUrl' => 'TEST/tokenRequest',
            'httpClass' => 'tests\HttpMockAuthTest',
        ) ) );
        
        // make a call to trigger a token request
        $ably->auth->authorise();
        
        $this->assertEquals( 'mock_token_requestToken', $ably->auth->getTokenDetails()->token, 'Expected mock token to be used' );
        $this->assertFalse( $ably->auth->isUsingBasicAuth(), 'Expected token auth to be used' );
    }

    /**
     * Init library with an authUrl that returns TokenDetails
     */
    public function testAuthWithAuthUrlTokenDetails() {
        $ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'authUrl' => 'TEST/tokenDetails',
            'httpClass' => 'tests\HttpMockAuthTest',
        ) ) );
        
        // make a call to trigger a token request
        $ably->auth->authorise();
        
        $this->assertEquals( 'mock_token_authurl_TokenDetails', $ably->auth->getTokenDetails()->token, 'Expected mock token to be used' );
        $this->assertFalse( $ably->auth->isUsingBasicAuth(), 'Expected token auth to be used' );
    }

    /**
     * Init library with an authUrl that returns a token string
     */
    public function testAuthWithAuthUrlTokenString() {
        $ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'authUrl' => 'TEST/tokenString',
            'httpClass' => 'tests\HttpMockAuthTest',
        ) ) );
        
        // make a call to trigger a token request
        $ably->auth->authorise();
        
        $this->assertEquals( 'mock_token_authurl_tokenString', $ably->auth->getTokenDetails()->token, 'Expected mock token to be used' );
        $this->assertFalse( $ably->auth->isUsingBasicAuth(), 'Expected token auth to be used' );
    }

    /**
     * Verify that parameter overriding works properly when using authUrl
     */
    public function testAuthWithAuthUrlParams() {
        $headers = array( 'Test header: yes', 'Another one: no' );
        $authParams = array( 'param1' => 'value1', 'test' => 1, 'ttl' => 720000 );
        $overridenTokenParams = array( 'ttl' => 360000 );
        // authParams and tokenParams should be merged, and since we aren't specifying any clientId when instancing the library,
        // a special wildcard clientId should be assigned to tokenParams automatically;
        // `ttl` should be overwritten by $overridenTokenParams
        $expectedAuthParams = array( 'param1' => 'value1', 'test' => 1, 'ttl' => 360000 );
        $method = 'POST';

        $ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'authUrl' => 'TEST/tokenRequest',
            'authHeaders' => $headers,
            'authParams' => $authParams,
            'authMethod' => $method,
            'httpClass' => 'tests\HttpMockAuthTest',
        ) ) );
        
        $ably->auth->authorise( $overridenTokenParams ); // make a call to trigger a token request
        
        $this->assertTrue( is_a( $ably->http, '\tests\HttpMockAuthTest' ) , 'Expected HttpMock class to be used' );
        $this->assertEquals( $headers, $ably->http->headers, 'Expected authHeaders to match' );
        $this->assertEquals( $expectedAuthParams, $ably->http->params, 'Expected authParams to match' );
        $this->assertEquals( $method, $ably->http->method, 'Expected authMethod to match' );

        $overridenAuthParams = array(
            'authHeaders' => array( 'CompletelyNewHeaders: true' ),
            'authParams' => array( 'completelyNewParams' => 'yes' ),
        );
        $expectedAuthParams = array( 'completelyNewParams' => 'yes', 'ttl' => 360000 );
        $forceReauth = true;

        $ably->auth->authorise( $overridenTokenParams, $overridenAuthParams, $forceReauth ); // make a call to trigger a token request

        $this->assertEquals( $overridenAuthParams['authHeaders'], $ably->http->headers, 'Expected authHeaders to be completely replaced' );
        $this->assertEquals( $expectedAuthParams, $ably->http->params, 'Expected authParams to be completely replaced' );
    }

    /**
     * Init library with a token
     */
    public function testAuthWithToken() {
        $ably_for_token = new AblyRest( array_merge( self::$defaultOptions, array(
            'key' => self::$testApp->getAppKeyDefault()->string,
        ) ) );
        $tokenDetails = $ably_for_token->auth->requestToken();

        $this->assertNotNull($tokenDetails->token, 'Expected token id' );

        $ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'tokenDetails' => $tokenDetails,
        ) ) );

        $this->assertFalse( $ably->auth->isUsingBasicAuth(), 'Expected token auth to be used' );

        $ably->stats(); // authorized request, should pass
    }

    /**
     * Init library with a key, force use of token with useTokenAuth
     */
    public function testAuthWithKeyForceToken() {
        $ably = new AblyRest( array(
            'key' => 'fake.key:totallyFake',
            'useTokenAuth' => true,
        ) );

        $this->assertFalse( $ably->auth->isUsingBasicAuth(), 'Expected token auth to be used' );
    }

    /**
     * Init library without providing a key or a token, force use of token with useTokenAuth
     */
    public function testAuthEmptyForceToken() {
        $this->setExpectedException( 'Ably\Exceptions\AblyException', '', 40103 );

        $ably = new AblyRest( array(
            'useTokenAuth' => true,
        ) );
    }

    /**
     * Verify than token auth works without TLS
     */
    public function testTokenWithoutTLS() {
        $ably_for_token = new AblyRest( array_merge( self::$defaultOptions, array(
            'key' => self::$testApp->getAppKeyDefault()->string,
        ) ) );
        $tokenDetails = $ably_for_token->auth->requestToken();

        $this->assertNotNull($tokenDetails->token, 'Expected token id' );

        $ablyInsecure = new AblyRest( array_merge( self::$defaultOptions, array(
            'tokenDetails' => $tokenDetails,
            'tls' => false,
        ) ) );

        $this->assertFalse( $ablyInsecure->auth->isUsingBasicAuth(), 'Expected token auth to be used' );

        $ablyInsecure->stats(); // authorized request, should pass
    }

    /**
     * Verify that createTokenRequest() creates valid signed token requests
     */
    public function testCreateTokenRequest() {
        $ablyKey = new AblyRest( array_merge( self::$defaultOptions, array(
            'key' => self::$testApp->getAppKeyDefault()->string,
        ) ) );

        $tokenRequest = $ablyKey->auth->createTokenRequest( array(), array(
            'ttl' => 60 * 1000,
        ));

        $tokenRequest2 = $ablyKey->auth->createTokenRequest( array(), array(
            'ttl' => 60 * 1000,
        ));

        $this->assertTrue( strlen( $tokenRequest->nonce ) >= 16, 'Expected nonce to be at least 16 bytes long' );
        $this->assertFalse( $tokenRequest->nonce == $tokenRequest2->nonce, 'Expected nonces to be unique' );
        $this->assertNotNull( $tokenRequest->mac, 'Expected hmac to be generated' );

        $ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'authCallback' => function( $tokenParams ) use( $tokenRequest ) {
                return $tokenRequest;
            },
        ) ) );
        
        $ably->auth->authorise();

        $this->assertFalse( $ably->auth->isUsingBasicAuth(), 'Expected token auth to be used' );

        $ably->stats(); // authorized request, should pass
    }

    /**
     * Verify that authorise() switches to token auth, keeps using the same token, and renews it when forced
     */
    public function testAuthorise() {
        $ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'key' => self::$testApp->getAppKeyDefault()->string,
        ) ) );

        $this->assertTrue( $ably->auth->isUsingBasicAuth(), 'Expected basic auth to be used' );

        $tokenOriginal = $ably->auth->authorise();

        $this->assertFalse( $ably->auth->isUsingBasicAuth(), 'Expected token auth to be used' );
        $this->assertInstanceOf( 'Ably\Models\TokenDetails', $tokenOriginal, 'Expected authorise to return a TokenDetails object' );

        $ably->auth->authorise();
        $this->assertEquals( $tokenOriginal->token, $ably->auth->getTokenDetails()->token, 'Expected token not to renew' );

        $ably->auth->authorise(array(), array(), $force = true);
        $this->assertFalse( $tokenOriginal->token == $ably->auth->getTokenDetails()->token, 'Expected token to renew' );
    }

    /**
     * Verify that authorise() stores the provided parameters and uses them as defaults from then on
     */
    public function testAuthoriseRememberDefaults() {
        $ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'key' => self::$testApp->getAppKeyDefault()->string,
            'clientId' => 'originalClientId',
        ) ) );

        $token1 = $ably->auth->authorise(array(
            'ttl' => 10000,
        ), array(
            'clientId' => 'overridenClientId',
        ));

        $forceReauth = true;
        $token2 = $ably->auth->authorise( array(), array(), $forceReauth );

        $this->assertFalse( $token1 == $token2, 'Expected different tokens to be issued') ;
        $this->assertEquals( 'overridenClientId', $ably->auth->getClientId(), 'Expected to use a new clientId as a default' );
        $this->assertLessThan( $ably->systemTime() + 20000, $token2->expires, 'Expected to use a new ttl as a default' );
    }

    /**
     * When using Basic Auth, the API key is sent in the `Authorization: Basic` header with a Base64 encoded value
     */
    public function testHTTPHeadersKey() {
        $fakeKey = 'fake.key:totallyFake';
        $ably = new AblyRest( array(
            'key' => $fakeKey,
            'httpClass' => 'tests\HttpMockAuthTest',
        ) );

        $ably->get("/dummy_test");

        $this->assertRegExp('/Authorization\s*:\s*Basic\s+'.base64_encode($fakeKey).'/i', $ably->http->headers[0]);
    }

    /**
     * Verify that the token string is Base64 encoded and used in the `Authorization: Bearer` header
     */
    public function testHTTPHeadersToken() {
        $fakeToken = 'fakeToken';
        $ably = new AblyRest( array(
            'token' => $fakeToken,
            'httpClass' => 'tests\HttpMockAuthTest',
        ) );

        $ably->get("/dummy_test");

        $this->assertRegExp('/Authorization\s*:\s*Bearer\s+'.base64_encode($fakeToken).'/i', $ably->http->headers[0]);
    }
}

class HttpMockAuthTest extends Http {
    public $headers;
    public $params;
    public $method;
    
    public function request($method, $url, $headers = array(), $params = array()) {
        if ($url == 'TEST/tokenRequest') {
            $this->method = $method;
            $this->headers = $headers;
            $this->params = $params;

            $tokenRequest = new TokenRequest( array(
                'timestamp' => time()*1000,
                'keyName' => 'fakeKeyName',
                'mac' => 'not_really_hmac',
            ) );
            
            $response = json_encode( $tokenRequest->toArray() );
            
            return array(
                'headers' => 'HTTP/1.1 200 OK'."\n",
                'body' => json_decode ( $response ),
            );
        } else if ($url == 'TEST/tokenDetails') {
            $this->method = $method;
            $this->headers = $headers;
            $this->params = $params;
            
            $tokenDetails = new TokenDetails( array(
                'token' => 'mock_token_authurl_TokenDetails',
                'issued' => time()*1000,
                'expires' => time()*1000 + 3600*1000,
            ) );
            
            $response = json_encode( $tokenDetails->toArray() );
            
            return array(
                'headers' => 'HTTP/1.1 200 OK'."\n",
                'body' => json_decode ( $response ),
            );
        } else if ($url == 'TEST/tokenString') {
            $this->method = $method;
            $this->headers = $headers;
            $this->params = $params;
            
            return array(
                'headers' => 'HTTP/1.1 200 OK'."\n",
                'body' => 'mock_token_authurl_tokenString',
            );
        } else if ( preg_match( '/\/keys\/[^\/]*\/requestToken$/', $url ) ) {
            // token-generating ably endpoint simulation

            $tokenDetails = new TokenDetails( array(
                'token' => 'mock_token_requestToken',
                'issued' => time()*1000,
                'expires' => time()*1000 + 3600*1000,
            ) );
            
            $response = json_encode( $tokenDetails->toArray() );
            
            return array(
                'headers' => 'HTTP/1.1 200 OK'."\n",
                'body' => json_decode ( $response ),
            );
        } else {
            $this->method = $method;
            $this->headers = $headers;
            $this->params = $params;

            return array(
                'headers' => 'HTTP/1.1 200 OK'."\n",
                'body' => (object) array('defaultRoute' => true),
            );
        }
    }
}