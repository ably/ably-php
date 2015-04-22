<?php
namespace tests;
use Ably\AblyRest;
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

        $this->assertTrue( $ably->auth->isUsingBasicAuth(), 'Unexpected Auth method mismatch.' );
    }


    /**
     * Init library with a token only
     */
    public function testAuthoriseWithTokenOnly() {
        $ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'appId' => self::$testApp->getAppId(),
            'tokenDetails' => new TokenDetails( "this_is_not_really_a_token" ),
        ) ) );

        $this->assertFalse( $ably->auth->isUsingBasicAuth(), 'Unexpected Auth method mismatch.' );
    }

    /**
     * Init library with a token callback
     */
    public function testAuthoriseWithTokenCallback() {
        $callbackCalled = false;

        $ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'appId'        => self::$testApp->getAppId(),
            'authCallback' => function( $tokenParams ) use( &$callbackCalled ) {
                $callbackCalled = true;
                return new TokenRequest( array( "fake" => "this_is_not_really_a_token_request" ) );
            }
        ) ) );
        
        // make a call to trigger a token request, catch request exception
        try {
            $ably->auth->authorise();
            $this->fail( 'Expected fake token to be rejected' );
        } catch (AblyRequestException $e) {
            //$this->assertEquals( 404, $e->getCode(), 'Expected error code 404' );
        }

        $this->assertTrue( $callbackCalled, 'Token callback not called' );
        $this->assertFalse( $ably->auth->isUsingBasicAuth(), 'Unexpected Auth method mismatch.' );
    }


    /**
     * Init library with a key and clientId; expect token auth to be chosen
     */
    public function testAuthoriseWithKeyAndClientId() {
        $ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'key'      => self::$testApp->getAppKeyDefault()->string,
            'clientId' => 'testClientId',
        ) ) );

        $this->assertFalse( $ably->auth->isUsingBasicAuth(), 'Unexpected Auth method mismatch.' );
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
            //'key'     => self::$testApp->getAppId(),
            'tokenDetails' => $tokenDetails,
        ) ) );

        $this->assertFalse( $ably->auth->isUsingBasicAuth(), 'Unexpected Auth method mismatch.' );
    }
}