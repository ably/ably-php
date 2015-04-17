<?php
namespace tests;
use Ably\AblyRest;
use Ably\AuthMethod;
use \Exception;

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

        $this->assertEquals( AuthMethod::BASIC, $ably->auth_method(), 'Unexpected Auth method mismatch.' );
    }


    /**
     * Init library with a token only
     */
    public function testAuthoriseWithTokenOnly() {
        $ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'appId'     => self::$testApp->getAppId(),
            'authToken' => "this_is_not_really_a_token",
        ) ) );

        $this->assertEquals( AuthMethod::TOKEN, $ably->auth_method(), 'Unexpected Auth method mismatch.' );
    }

    protected $authinit2_cbCalled = false;
    /**
     * Init library with a token callback
     */
    public function testAuthoriseWithTokenCallback() {
        $callbackCalled = false;

        $ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'appId'        => self::$testApp->getAppId(),
            'authCallback' => function( $params ) use( &$callbackCalled ) {
                $callbackCalled = true;
                return "this_is_not_really_a_token_request";
            }
        ) ) );
        
        // make a call to trigger a token request
        $ably->authorise();

        $this->assertTrue( $callbackCalled, 'Token callback not called' );
        $this->assertEquals( AuthMethod::TOKEN, $ably->auth_method(), 'Unexpected Auth method mismatch.' );
    }


    /**
     * Init library with a key and clientId; expect token auth to be chosen
     */
    public function testAuthoriseWithKeyAndClientId() {
        $ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'key'      => self::$testApp->getAppKeyDefault()->string,
            'clientId' => 'testClientId',
        ) ) );

        $this->assertEquals( AuthMethod::TOKEN, $ably->auth_method(), 'Unexpected Auth method mismatch.' );
    }

    /**
     * Init library with a token
     */
    public function testAuthoriseWithToken() {
        $ably_for_token = new AblyRest( array_merge( self::$defaultOptions, array(
            'key' => self::$testApp->getAppKeyDefault()->string,
        ) ) );
        $token_details = $ably_for_token->request_token();

        $this->assertNotNull($token_details->id, 'Expected token id' );

        $ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'appId'     => self::$testApp->getAppId(),
            'authToken' => $token_details->id,
        ) ) );

        $this->assertEquals( AuthMethod::TOKEN, $ably->auth_method(), 'Unexpected Auth method mismatch.' );
    }
}