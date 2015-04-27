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
     * Init library with a key only
     */
    public function testAuthoriseWithKeyOnly() {
        $ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'key' => self::$testApp->getAppKeyDefault()->string,
        ) ) );

        $this->assertTrue( $ably->auth->isUsingBasicAuth(), 'Expected basic auth to be used' );
    }


    /**
     * Init library with a token only
     */
    public function testAuthoriseWithTokenOnly() {
        $ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'tokenDetails' => new TokenDetails( "this_is_not_really_a_token" ),
        ) ) );

        $this->assertFalse( $ably->auth->isUsingBasicAuth(), 'Expected token auth to be used' );
    }

    /**
     * Init library with a token callback
     */
    public function testAuthoriseWithTokenCallback() {
        $callbackCalled = false;
        $keyName = self::$testApp->getAppKeyDefault()->name;

        $ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'authCallback' => function( $tokenParams ) use( &$callbackCalled, $keyName ) {
                $callbackCalled = true;

                return new TokenRequest( array(
                    'token' => 'this_is_not_really_a_token_request',
                    'timestamp' => time()*1000,
                    'keyName' => $keyName,
                ) );
            }
        ) ) );
        
        // make a call to trigger a token request, catch request exception
        try {
            $ably->auth->authorise();
            $this->fail( 'Expected unsigned fake token to be rejected' );
        } catch (AblyRequestException $e) {
            $this->assertEquals( 40101, $e->getAblyCode(), 'Expected error code 40101' );
        }

        $this->assertTrue( $callbackCalled, 'Token callback not called' );
        $this->assertFalse( $ably->auth->isUsingBasicAuth(), 'Expected token auth to be used' );
    }
    
    /**
     * Init library with an authUrl
     */
    public function testAuthoriseWithAuthUrl() {
        $keyName = self::$testApp->getAppKeyDefault()->name;
        
        $headers = array( 'Test header: yes', 'Another one: no' );
        $params = array( 'keyName' => $keyName, 'test' => 1 );
        $method = 'PUT';

        $ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'authUrl' => 'TEST/tokenRequest',
            'authHeaders' => $headers,
            'authParams' => $params,
            'authMethod' => $method,
            'httpClass' => 'tests\HttpMockAuthTest',
        ) ) );
        
        // make a call to trigger a token request
        $ably->auth->authorise();
        
        $this->assertTrue( is_a( $ably->http, '\tests\HttpMockAuthTest' ) , 'Expected HttpMock class to be used' );
        $this->assertEquals( $headers, $ably->http->headers, 'Expected authHeaders to match' );
        $this->assertEquals( $params, $ably->http->params, 'Expected authParams to match' );
        $this->assertEquals( $method, $ably->http->method, 'Expected authMethod to match' );
        $this->assertEquals( 'mock_token_authurl', $ably->auth->getToken()->token, 'Expected mock token to be used' );
        $this->assertFalse( $ably->auth->isUsingBasicAuth(), 'Expected token auth to be used' );
    }


    /**
     * Init library with a key and clientId; expect token auth to be chosen
     */
    public function testAuthoriseWithKeyAndClientId() {
        $ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'key'      => self::$testApp->getAppKeyDefault()->string,
            'clientId' => 'testClientId',
        ) ) );

        $this->assertFalse( $ably->auth->isUsingBasicAuth(), 'Expected token auth to be used' );
    }

    /**
     * Init library with a token
     */
    public function testAuthoriseWithToken() {
        $ably_for_token = new AblyRest( array_merge( self::$defaultOptions, array(
            'key' => self::$testApp->getAppKeyDefault()->string,
        ) ) );
        $tokenDetails = $ably_for_token->auth->requestToken();

        $this->assertNotNull($tokenDetails->token, 'Expected token id' );

        $ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'tokenDetails' => $tokenDetails,
        ) ) );

        $this->assertFalse( $ably->auth->isUsingBasicAuth(), 'Expected token auth to be used' );
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
                'keyName' => $params['keyName'],
                'mac' => 'not_really_hmac',
            ) );
            
            $response = json_encode( $tokenRequest->toArray() );
            
            return array(
                'headers' => 'HTTP/1.1 200 OK'."\n",
                'body' => json_decode ( $response ),
            );
        } else if ( preg_match( '/\/keys\/[^\/]*\/requestToken$/', $url ) ) {
            $tokenDetails = new TokenDetails( array(
                'token' => 'mock_token_authurl',
                'issued' => time()*1000,
                'expires' => time()*1000 + 3600*1000,
            ) );
            
            $response = json_encode( $tokenDetails->toArray() );
            
            return array(
                'headers' => 'HTTP/1.1 200 OK'."\n",
                'body' => json_decode ( $response ),
            );
        }
        
        echo $url."\n";
        
        return '?';
    }
}